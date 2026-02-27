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
 */
class MigrationRunner implements MigrationRunnerInterface
{
    private MigrationRepositoryInterface $repository;
    private MigrationFileResolver $fileResolver;

    public function __construct(
        MigrationRepositoryInterface $repository,
        MigrationFileResolver $fileResolver,
    ) {
        $this->repository = $repository;
        $this->fileResolver = $fileResolver;
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
            $this->executeMigration($filename);
            $this->repository->recordMigration($filename, $batch);
            $executed[] = $filename;
        }

        return $executed;
    }

    /**
     * Execute a single migration file.
     *
     * @throws \RuntimeException On execution failure
     */
    private function executeMigration(string $filename): void
    {
        $fullPath = $this->fileResolver->getFullPath($filename);

        if (!file_exists($fullPath)) {
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
    }
}
