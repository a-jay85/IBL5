<?php

declare(strict_types=1);

namespace Tests\Migration;

use Migration\MigrationFileResolver;
use PHPUnit\Framework\TestCase;

/**
 * Validates migration file naming conventions and integrity.
 *
 * These tests run without a database connection — they only inspect
 * the filesystem to catch issues before CI or deployment.
 */
final class MigrationFileIntegrityTest extends TestCase
{
    private string $migrationsDir;

    protected function setUp(): void
    {
        $this->migrationsDir = dirname(__DIR__, 2) . '/migrations';
    }

    /**
     * Known duplicate prefixes that exist in the codebase and are intentional.
     * Both files in each pair run safely in lexicographic order.
     *
     * @var list<string>
     */
    private const ALLOWED_DUPLICATE_PREFIXES = ['046'];

    public function testNoDuplicateNumberPrefixes(): void
    {
        $files = glob($this->migrationsDir . '/*.sql');
        self::assertNotFalse($files);
        self::assertNotEmpty($files, 'No .sql migration files found');

        $prefixes = [];

        foreach ($files as $file) {
            $basename = basename($file);

            // Extract numeric prefix (e.g., "001" from "001_add_table.sql", "033b" from "033b_fix.sql")
            if (preg_match('/^(\d{1,3}[a-z]?)_/', $basename, $matches) === 1) {
                $prefix = $matches[1];

                if (in_array($prefix, self::ALLOWED_DUPLICATE_PREFIXES, true)) {
                    continue;
                }

                self::assertArrayNotHasKey(
                    $prefix,
                    $prefixes,
                    sprintf(
                        'Duplicate migration number prefix "%s": "%s" conflicts with "%s"',
                        $prefix,
                        $basename,
                        $prefixes[$prefix] ?? ''
                    )
                );

                $prefixes[$prefix] = $basename;
            }
        }

        // Ensure we actually found numbered migrations
        self::assertNotEmpty($prefixes, 'No numbered migration files found');
    }

    public function testAllMigrationFilesAreNonEmpty(): void
    {
        $files = array_merge(
            glob($this->migrationsDir . '/*.sql') ?: [],
            glob($this->migrationsDir . '/*.php') ?: []
        );

        self::assertNotEmpty($files, 'No migration files found');

        foreach ($files as $file) {
            self::assertFileIsReadable($file);

            $content = file_get_contents($file);
            self::assertNotFalse($content, sprintf('Could not read %s', basename($file)));
            self::assertNotEmpty(
                trim($content),
                sprintf('Migration file "%s" is empty', basename($file))
            );
        }
    }

    public function testMigrationFileResolverReturnsNonEmptyList(): void
    {
        $resolver = new MigrationFileResolver($this->migrationsDir);
        $migrations = $resolver->getAvailableMigrations();

        self::assertNotEmpty($migrations, 'MigrationFileResolver returned no migrations');
        self::assertContains('000_baseline_schema.sql', $migrations, 'Baseline schema must be present');
    }

    public function testBaselineSchemaIsFirstInOrder(): void
    {
        $resolver = new MigrationFileResolver($this->migrationsDir);
        $migrations = $resolver->getAvailableMigrations();

        self::assertSame(
            '000_baseline_schema.sql',
            $migrations[0],
            'Baseline schema must be the first migration in sort order'
        );
    }

    /**
     * Enforce convention: every CHANGE COLUMN IF EXISTS rename should have
     * a corresponding SchemaAssertion for the destination column.
     *
     * This prevents silent no-ops from going undetected (the bug that caused
     * the dc_canPlayInGame production outage).
     *
     * Chain-aware: if a later migration renames the destination to another
     * name (e.g., `dc_canPlayInGame` → `dc_can_play_in_game` in migration
     * 116), the assertion must be for the final name in the chain, not the
     * intermediate one.
     */
    public function testChangeColumnRenamesHaveSchemaAssertions(): void
    {
        $assertionsFile = dirname(__DIR__, 2) . '/config/schema-assertions.php';
        self::assertFileExists($assertionsFile, 'Schema assertions config file must exist');

        /** @var list<\Migration\SchemaAssertion> $assertions */
        $assertions = require $assertionsFile;

        $assertedColumns = [];
        foreach ($assertions as $assertion) {
            $assertedColumns[] = $assertion->toKey();
        }

        $files = glob($this->migrationsDir . '/*.sql') ?: [];
        sort($files);

        // First pass: build a rename chain map keyed by "table.old_column" →
        // "new_column". Scans BOTH `CHANGE COLUMN IF EXISTS` (soft renames
        // subject to this check) and plain `CHANGE COLUMN` (strict renames
        // used once baseline assertions are in place — migration 114/116
        // patterns). Consumer queries walk this map forward.
        //
        // Strategy: split each migration file into `ALTER TABLE ... ;` blocks,
        // then extract the table name and every CHANGE COLUMN pair inside.
        // This handles multi-CHANGE-COLUMN ALTERs (one ALTER, many renames).
        $renameChain = [];
        foreach ($files as $file) {
            $basename = basename($file);
            if ($basename === '000_baseline_schema.sql') {
                continue;
            }
            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }
            if (preg_match_all(
                '/ALTER\s+TABLE\s+[`]?(\w+)[`]?(.*?);/si',
                $content,
                $alterBlocks,
                PREG_SET_ORDER
            ) > 0) {
                foreach ($alterBlocks as $block) {
                    $table = $block[1];
                    $body = $block[2];
                    if (preg_match_all(
                        '/CHANGE\s+COLUMN\s+(?:IF\s+EXISTS\s+)?[`]?(\w+)[`]?\s+[`]?(\w+)[`]?/si',
                        $body,
                        $pairs,
                        PREG_SET_ORDER
                    ) > 0) {
                        foreach ($pairs as $pair) {
                            $oldColumn = $pair[1];
                            $newColumn = $pair[2];
                            if ($oldColumn === $newColumn) {
                                continue;
                            }
                            $renameChain[$table . '.' . $oldColumn] = $newColumn;
                        }
                    }
                }
            }
        }

        $uncovered = [];

        foreach ($files as $file) {
            $basename = basename($file);
            if ($basename === '000_baseline_schema.sql') {
                continue;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                continue;
            }

            // Match: CHANGE COLUMN IF EXISTS `old_name` `new_name`
            // or:    CHANGE COLUMN IF EXISTS old_name new_name
            if (preg_match_all(
                '/ALTER\s+TABLE\s+[`]?(\w+)[`]?\s+.*?CHANGE\s+COLUMN\s+IF\s+EXISTS\s+[`]?\w+[`]?\s+[`]?(\w+)[`]?/si',
                $content,
                $matches,
                PREG_SET_ORDER
            ) > 0) {
                foreach ($matches as $match) {
                    $table = $match[1];
                    $destColumn = $match[2];

                    // Follow the rename chain forward: if a later migration
                    // renamed this destination to another name, assert the
                    // final name. Bounded to avoid infinite loops on a
                    // hypothetical cycle.
                    $finalColumn = $destColumn;
                    for ($hop = 0; $hop < 10; $hop++) {
                        $nextKey = $table . '.' . $finalColumn;
                        if (!isset($renameChain[$nextKey])) {
                            break;
                        }
                        $finalColumn = $renameChain[$nextKey];
                    }

                    $key = $table . '.' . $finalColumn;

                    if (!in_array($key, $assertedColumns, true)) {
                        $uncovered[] = "{$key} (from {$basename})";
                    }
                }
            }
        }

        self::assertSame(
            [],
            $uncovered,
            "CHANGE COLUMN IF EXISTS renames without SchemaAssertions:\n  " .
            implode("\n  ", $uncovered) .
            "\n\nAdd SchemaAssertion entries in config/schema-assertions.php for these columns."
        );
    }
}
