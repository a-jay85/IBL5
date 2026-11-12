<?php

declare(strict_types=1);

namespace Migration;

use Migration\Contracts\MigrationRepositoryInterface;
use Migration\Contracts\MigrationRunnerInterface;

/**
 * MigrationRunner - Orchestrates execution of pending database migrations
 *
 * Compares available migration files against the migrations tracking table
 * to determine which are pending, then executes them in order.
 *
 * SQL migrations are executed via MigrationRepository::executeRawSql().
 * PHP migrations are executed via a subprocess to guard against exit()/die().
 * Successful PHP-migration output is passed to an optional sink closure.
 */
class MigrationRunner implements MigrationRunnerInterface
{
    private MigrationRepositoryInterface $repository;
    private MigrationFileResolver $fileResolver;

    /** @var (\Closure(string, string): void)|null */
    private ?\Closure $phpOutputSink;

    /**
     * @param (\Closure(string, string): void)|null $phpOutputSink Receives
     *        (filename, combined stdout+stderr) after each PHP migration that
     *        exits 0 with non-empty output. Null discards the output.
     */
    public function __construct(
        MigrationRepositoryInterface $repository,
        MigrationFileResolver $fileResolver,
        ?\Closure $phpOutputSink = null,
    ) {
        $this->repository = $repository;
        $this->fileResolver = $fileResolver;
        $this->phpOutputSink = $phpOutputSink;
    }

    /**
     * Format PHP-migration output for a CLI log: every line prefixed with
     * four spaces and the bracketed migration filename, newline-terminated.
     */
    public static function formatPhpOutput(string $filename, string $output): string
    {
        $formatted = '';
        foreach (explode("\n", $output) as $line) {
            $formatted .= "    [{$filename}] {$line}\n";
        }

        return $formatted;
    }

    /**
     * @see MigrationRunnerInterface::getPending()
     */
    public function getPending(): array
    {
        $available = $this->fileResolver->getAvailableMigrations();
        $ran = $this->repository->getRanMigrations();
        $ranLookup = array_flip($ran);

        return array_values(
            array_filter(
                $available,
                static fn(string $file): bool => !isset($ranLookup[$file]),
            ),
        );
    }

    /**
     * @see MigrationRunnerInterface::runPending()
     */
    public function runPending(): array
    {
        $pending = $this->getPending();

        if ($pending === []) {
            return [];
        }

        $batch = $this->repository->getNextBatchNumber();
        $executed = [];

        foreach ($pending as $filename) {
            $down = $this->readDownPartner($filename);
            $this->executeMigration($filename);
            if ($down === null) {
                $this->repository->recordMigration($filename, $batch);
            } else {
                $this->repository->recordMigrationWithDown($filename, $batch, $down['sql'], $down['sha']);
            }
            $executed[] = $filename;
        }

        return $executed;
    }

    /**
     * Read the paired .down.sql file and the up file's checksum, before the up runs.
     *
     * Every refusal throws before the migration changes any data.
     *
     * @return array{sql: string, sha: string}|null Null when the migration has no down file
     * @throws \RuntimeException When the pair cannot be stored safely
     */
    private function readDownPartner(string $filename): ?array
    {
        $downName = $this->fileResolver->getDownFilename($filename);

        if ($downName === null) {
            return null;
        }

        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== 'sql') {
            throw new \RuntimeException(
                "Down files pair with .sql migrations only: {$downName}",
            );
        }

        if (!$this->repository->supportsDownSql()) {
            throw new \RuntimeException(
                "Cannot store down SQL for {$filename}: the migrations table has no down_sql column.",
            );
        }

        $downSql = @file_get_contents($this->fileResolver->getFullPath($downName));

        if ($downSql === false || trim($downSql) === '') {
            throw new \RuntimeException(
                "Down file is empty or unreadable: {$downName}",
            );
        }

        $nonDml = self::findNonDmlStatement($downSql);

        if ($nonDml !== null) {
            throw new \RuntimeException(
                "Down file must hold only INSERT, UPDATE or DELETE: {$downName} ({$nonDml})",
            );
        }

        $sha = @hash_file('sha256', $this->fileResolver->getFullPath($filename));

        if ($sha === false) {
            throw new \RuntimeException(
                "Failed to checksum migration file: {$filename}",
            );
        }

        return ['sql' => $downSql, 'sha' => $sha];
    }

    /**
     * Find the first statement in a down-SQL text that is not INSERT, UPDATE or DELETE.
     *
     * Whole-line `--` comments are removed, then the text is split on every `;`.
     * A `;` inside a string literal causes a false refusal, so down files keep
     * `;` out of literals.
     *
     * @return string|null Null when every statement is DML, else the first 60
     *         characters of the offending fragment ('(no statements)' when empty)
     */
    public static function findNonDmlStatement(string $sql): ?string
    {
        $stripped = preg_replace('/^\s*--.*$/m', '', $sql);
        $text = $stripped ?? $sql;

        $fragments = [];
        foreach (explode(';', $text) as $fragment) {
            $fragment = trim($fragment);
            if ($fragment !== '') {
                $fragments[] = $fragment;
            }
        }

        if ($fragments === []) {
            return '(no statements)';
        }

        foreach ($fragments as $fragment) {
            if (preg_match('/^(insert|update|delete)\b/i', $fragment) !== 1) {
                return substr($fragment, 0, 60);
            }
        }

        return null;
    }

    /**
     * @see MigrationRunnerInterface::getVanishedUndoPlan()
     */
    public function getVanishedUndoPlan(): array
    {
        if (!$this->repository->supportsDownSql()) {
            return [];
        }

        $rows = $this->repository->getAppliedDownStates();

        $first = null;
        foreach ($rows as $index => $row) {
            if ($row['hasDown'] && $this->isVanished($row['migration'])) {
                $first = $index;
                break;
            }
        }

        if ($first === null) {
            return [];
        }

        $hashes = $this->currentUpHashes();
        $plan = [];

        foreach (array_slice($rows, $first) as $row) {
            $name = $row['migration'];

            if (!$this->isVanished($name)) {
                throw new \RuntimeException(
                    "Cannot auto-revert: {$name} was applied after a reverted migration and its file still exists.",
                );
            }

            if (!$row['hasDown']) {
                throw new \RuntimeException(
                    "Cannot auto-revert: {$name} is missing from the checkout and has no stored down SQL.",
                );
            }

            $sha = $row['upSha256'];

            if ($sha === null || $sha === '') {
                throw new \RuntimeException(
                    "Cannot auto-revert: {$name} has no stored checksum.",
                );
            }

            if (isset($hashes[$sha])) {
                throw new \RuntimeException(
                    "Cannot auto-revert: {$name} has the same contents as {$hashes[$sha]}. Restore the old file name.",
                );
            }

            $plan[] = ['id' => $row['id'], 'migration' => $name];
        }

        return array_reverse($plan);
    }

    /**
     * @see MigrationRunnerInterface::undoVanished()
     */
    public function undoVanished(): array
    {
        $plan = $this->getVanishedUndoPlan();

        $texts = [];
        foreach ($plan as $row) {
            $name = $row['migration'];
            $downSql = $this->repository->getStoredDownSql($row['id']);

            if ($downSql === null || trim($downSql) === '') {
                throw new \RuntimeException("No stored down SQL for {$name}.");
            }

            $nonDml = self::findNonDmlStatement($downSql);

            if ($nonDml !== null) {
                throw new \RuntimeException(
                    "Stored down SQL for {$name} is not DML-only ({$nonDml}).",
                );
            }

            $texts[] = $downSql;
        }

        $undone = [];
        foreach ($plan as $index => $row) {
            $this->repository->undoMigration($row['id'], $texts[$index]);
            $undone[] = $row['migration'];
        }

        return $undone;
    }

    /**
     * Whether a migration's file is gone from the migrations directory.
     */
    private function isVanished(string $name): bool
    {
        return !file_exists($this->fileResolver->getFullPath($name));
    }

    /**
     * Map the SHA-256 of every current migration file to its file name.
     *
     * @return array<string, string> Hex digest => file name
     * @throws \RuntimeException When a file cannot be hashed
     */
    private function currentUpHashes(): array
    {
        $hashes = [];

        foreach ($this->fileResolver->getAvailableMigrations() as $name) {
            $hash = @hash_file('sha256', $this->fileResolver->getFullPath($name));

            if ($hash === false) {
                throw new \RuntimeException(
                    "Failed to checksum migration file: {$name}",
                );
            }

            $hashes[$hash] = $name;
        }

        return $hashes;
    }

    /**
     * Execute a single migration file.
     *
     * @throws \RuntimeException On execution failure
     */
    private function executeMigration(string $filename): void
    {
        $fullPath = $this->fileResolver->getFullPath($filename);

        if ($this->isVanished($filename)) {
            throw new \RuntimeException(
                "Migration file not found: {$filename}",
            );
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($extension === 'sql') {
            $this->executeSqlMigration($fullPath, $filename);
        } elseif ($extension === 'php') {
            $this->executePhpMigration($fullPath, $filename);
        } else {
            throw new \RuntimeException(
                "Unsupported migration file type: {$filename}",
            );
        }
    }

    /**
     * Execute a .sql migration file by reading its contents and running via multi_query.
     */
    private function executeSqlMigration(string $fullPath, string $filename): void
    {
        $sql = file_get_contents($fullPath);

        if ($sql === false) {
            throw new \RuntimeException(
                "Failed to read migration file: {$filename}",
            );
        }

        $this->repository->executeRawSql($sql);
    }

    /**
     * Execute a .php migration file via subprocess.
     *
     * PHP migrations are run in a subprocess to guard against exit()/die()
     * calls within the migration file (e.g., migrate_gm_awards.php).
     */
    private function executePhpMigration(string $fullPath, string $filename): void
    {
        $escapedPath = escapeshellarg($fullPath);
        $output = [];
        $exitCode = 0;

        exec("php {$escapedPath} 2>&1", $output, $exitCode);

        if ($exitCode !== 0) {
            $outputStr = implode("\n", $output);
            throw new \RuntimeException(
                "PHP migration failed ({$filename}): exit code {$exitCode}\n{$outputStr}",
            );
        }

        if ($this->phpOutputSink !== null && $output !== []) {
            ($this->phpOutputSink)($filename, implode("\n", $output));
        }
    }
}
