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
}
