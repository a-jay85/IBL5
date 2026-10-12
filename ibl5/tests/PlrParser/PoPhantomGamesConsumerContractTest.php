<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\TestCase;

/**
 * Locks the po_phantom_games consumer contract (backlog#916).
 *
 * Any reader of po_stats_gm under classes/ or modules/ must apply the signed
 * po_phantom_games correction. The box-score playoff views must never subtract
 * it, because Season1993PhantomRepair already deleted the phantom box rows.
 * See the "po_phantom_games contract" section in docs/DATABASE_GUIDE.md.
 */
final class PoPhantomGamesConsumerContractTest extends TestCase
{
    private const SCAN_ROOTS = ['classes', 'modules'];

    /** Files that WRITE po_stats_gm (parser + snapshot repository). Raw PLR count by contract. */
    private const WRITER_ALLOWLIST = [
        'classes/PlrParser/PlrParserRepository.php',
        'classes/PlrParser/PlrParserService.php',
    ];

    /** Box-score-derived views: the 1993 phantom rows are already deleted there (Season1993PhantomRepair). */
    private const BOX_SCORE_PLAYOFF_VIEWS = ['ibl_playoff_stats', 'ibl_playoff_career_totals'];

    private const READ_TOKEN = 'po_stats_gm';
    private const CORRECTION_TOKEN = 'po_phantom_games';
    private const SIGNED_CAST_PATTERN = '/CAST\s*\(.*\bAS\s+SIGNED\b/is';

    public function testProductionReadersApplyPhantomCorrection(): void
    {
        $base = $this->repoBase();

        $violations = self::findUncorrectedReaders($base, self::SCAN_ROOTS, self::WRITER_ALLOWLIST);

        self::assertSame(
            [],
            $violations,
            "po_stats_gm read without the signed po_phantom_games correction:\n" . implode("\n", $violations)
        );
    }

    public function testWriterAllowlistEntriesStillExist(): void
    {
        $base = $this->repoBase();
        foreach (self::WRITER_ALLOWLIST as $relative) {
            self::assertFileExists($base . '/' . $relative, 'Stale writer allowlist entry: ' . $relative);
        }
    }

    public function testPlayoffViewsDoNotSubtractPhantomGames(): void
    {
        $schema = file_get_contents($this->repoBase() . '/docs/schema/current-schema.sql');
        self::assertIsString($schema);

        $violations = self::findViewViolations($schema, self::BOX_SCORE_PLAYOFF_VIEWS);

        self::assertSame([], $violations, implode("\n", $violations));
    }

    public function testScannerFlagsReaderWithoutCorrection(): void
    {
        $dir = $this->makeFixtureRoot();
        try {
            mkdir($dir . '/modules/Planted', 0777, true);
            file_put_contents(
                $dir . '/modules/Planted/Reader.php',
                "<?php\n\$sql = \"SELECT po_stats_gm FROM ibl_plr_snapshots\";\n"
            );

            $violations = self::findUncorrectedReaders($dir, self::SCAN_ROOTS, []);

            self::assertCount(1, $violations);
            self::assertStringContainsString('modules/Planted/Reader.php', $violations[0]);
        } finally {
            self::removeDir($dir);
        }
    }

    public function testScannerFlagsReaderWithUnsignedSubtraction(): void
    {
        $dir = $this->makeFixtureRoot();
        try {
            file_put_contents(
                $dir . '/classes/PlantedUnsigned.php',
                "<?php\n\$sql = \"SELECT po_stats_gm - po_phantom_games FROM ibl_plr_snapshots\";\n"
            );

            $violations = self::findUncorrectedReaders($dir, self::SCAN_ROOTS, []);

            self::assertCount(1, $violations);
            self::assertStringContainsString('classes/PlantedUnsigned.php', $violations[0]);
        } finally {
            self::removeDir($dir);
        }
    }

    public function testScannerAcceptsReaderWithSignedCastCorrection(): void
    {
        $dir = $this->makeFixtureRoot();
        try {
            file_put_contents(
                $dir . '/classes/PlantedSigned.php',
                "<?php\n\$sql = \"SELECT CAST(po_stats_gm AS SIGNED) - CAST(po_phantom_games AS SIGNED) AS games"
                . " FROM ibl_plr_snapshots\";\n"
            );

            self::assertSame([], self::findUncorrectedReaders($dir, self::SCAN_ROOTS, []));
        } finally {
            self::removeDir($dir);
        }
    }

    public function testScannerSkipsAllowlistedWriter(): void
    {
        $dir = $this->makeFixtureRoot();
        try {
            mkdir($dir . '/classes/PlrParser', 0777, true);
            file_put_contents(
                $dir . '/classes/PlrParser/PlrParserService.php',
                "<?php\n\$row['po_stats_gm'] = \$gp;\n"
            );

            self::assertSame(
                [],
                self::findUncorrectedReaders($dir, self::SCAN_ROOTS, ['classes/PlrParser/PlrParserService.php'])
            );
            self::assertCount(1, self::findUncorrectedReaders($dir, self::SCAN_ROOTS, []));
        } finally {
            self::removeDir($dir);
        }
    }

    public function testScannerIgnoresCommentOnlyMention(): void
    {
        $dir = $this->makeFixtureRoot();
        try {
            file_put_contents(
                $dir . '/classes/PlantedComments.php',
                "<?php\n/**\n * Reads po_stats_gm for the playoffs.\n */\n\$x = 1; // po_stats_gm\n"
            );

            self::assertSame([], self::findUncorrectedReaders($dir, self::SCAN_ROOTS, []));
        } finally {
            self::removeDir($dir);
        }
    }

    public function testViewGuardFlagsPhantomSubtractionInPlayoffView(): void
    {
        $schema = "/*!50001 VIEW `ibl_playoff_stats` AS select count(0) - sum(s.po_phantom_games) AS games from x */;\n"
            . "/*!50001 VIEW `ibl_playoff_career_totals` AS select count(0) AS games from x */;";

        self::assertSame(
            ['ibl_playoff_stats: box-score view subtracts po_phantom_games (double correction)'],
            self::findViewViolations($schema, self::BOX_SCORE_PLAYOFF_VIEWS)
        );
    }

    public function testViewGuardFailsWhenViewDefinitionMissing(): void
    {
        $schema = "/*!50001 VIEW `ibl_playoff_stats` AS select count(0) AS games from x */;";

        self::assertSame(
            ['ibl_playoff_career_totals: definition not found in schema dump'],
            self::findViewViolations($schema, self::BOX_SCORE_PLAYOFF_VIEWS)
        );
    }

    private function repoBase(): string
    {
        $base = realpath(__DIR__ . '/../..');
        self::assertIsString($base);
        self::assertDirectoryExists($base . '/classes');

        return $base;
    }

    private function makeFixtureRoot(): string
    {
        $dir = sys_get_temp_dir() . '/po-phantom-contract-' . bin2hex(random_bytes(4));
        mkdir($dir . '/classes', 0777, true);
        mkdir($dir . '/modules', 0777, true);

        return $dir;
    }

    /**
     * Files that read po_stats_gm without the signed po_phantom_games correction.
     * File-level on purpose: the read and the correction sit in one SQL string
     * in every plausible reader, and line-level pairing would reject a
     * multi-line query.
     *
     * @param list<string> $roots
     * @param list<string> $writerAllowlist
     * @return list<string>
     */
    private static function findUncorrectedReaders(string $base, array $roots, array $writerAllowlist): array
    {
        $violations = [];
        foreach ($roots as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base . '/' . $root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($base) + 1);
                if (in_array($relative, $writerAllowlist, true)) {
                    continue;
                }
                $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
                self::assertIsArray($lines);
                $codeLines = [];
                foreach ($lines as $line) {
                    if (self::isCommentLine($line)) {
                        continue;
                    }
                    $codeLines[] = preg_replace('~//.*$~', '', $line) ?? $line;
                }
                $code = implode("\n", $codeLines);
                if (!str_contains($code, self::READ_TOKEN)) {
                    continue;
                }
                $compliant = str_contains($code, self::CORRECTION_TOKEN)
                    && preg_match(self::SIGNED_CAST_PATTERN, $code) === 1;
                if (!$compliant) {
                    $violations[] = $relative
                        . ': reads po_stats_gm without CAST(... AS SIGNED) po_phantom_games correction';
                }
            }
        }
        sort($violations);

        return $violations;
    }

    /**
     * @param list<string> $viewNames
     * @return list<string>
     */
    private static function findViewViolations(string $schemaSql, array $viewNames): array
    {
        $violations = [];
        foreach ($viewNames as $name) {
            $matched = preg_match_all(
                '/VIEW `' . preg_quote($name, '/') . '` AS (.*?)\*\/;/s',
                $schemaSql,
                $matches
            );
            if ($matched === false || $matched === 0) {
                $violations[] = $name . ': definition not found in schema dump';
                continue;
            }
            foreach ($matches[1] as $body) {
                if (str_contains($body, self::CORRECTION_TOKEN)) {
                    $violations[] = $name . ': box-score view subtracts po_phantom_games (double correction)';
                    break;
                }
            }
        }

        return $violations;
    }

    /** Whole-line comments, including docblock continuation lines. */
    private static function isCommentLine(string $line): bool
    {
        $trimmed = ltrim($line);

        return $trimmed !== '' && (
            str_starts_with($trimmed, '*')
            || str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '#')
            || str_starts_with($trimmed, '/*')
        );
    }

    private static function removeDir(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $entry) {
            if (!$entry instanceof \SplFileInfo) {
                continue;
            }
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($dir);
    }
}
