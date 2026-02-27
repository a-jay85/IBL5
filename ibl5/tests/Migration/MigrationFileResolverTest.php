<?php

declare(strict_types=1);

namespace Tests\Migration;

use Migration\MigrationFileResolver;
use PHPUnit\Framework\TestCase;

final class MigrationFileResolverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ibl5_migration_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testEmptyDirectoryReturnsEmptyArray(): void
    {
        $resolver = new MigrationFileResolver($this->tempDir);
        $this->assertSame([], $resolver->getAvailableMigrations());
    }

    public function testNonExistentDirectoryReturnsEmptyArray(): void
    {
        $resolver = new MigrationFileResolver($this->tempDir . '/nonexistent');
        $this->assertSame([], $resolver->getAvailableMigrations());
    }

    public function testExcludesNonMigrationFiles(): void
    {
        touch($this->tempDir . '/README.md');
        touch($this->tempDir . '/notes.txt');
        touch($this->tempDir . '/backup.bak');

        $resolver = new MigrationFileResolver($this->tempDir);
        $this->assertSame([], $resolver->getAvailableMigrations());
    }

    public function testIncludesSqlAndPhpFiles(): void
    {
        touch($this->tempDir . '/001_create_table.sql');
        touch($this->tempDir . '/migrate_data.php');

        $resolver = new MigrationFileResolver($this->tempDir);
        $result = $resolver->getAvailableMigrations();

        $this->assertCount(2, $result);
        $this->assertContains('001_create_table.sql', $result);
        $this->assertContains('migrate_data.php', $result);
    }

    public function testSortOrderNumberedBeforeNonNumbered(): void
    {
        touch($this->tempDir . '/add_column.sql');
        touch($this->tempDir . '/001_first.sql');
        touch($this->tempDir . '/002_second.sql');

        $resolver = new MigrationFileResolver($this->tempDir);
        $result = $resolver->getAvailableMigrations();

        $this->assertSame([
            '001_first.sql',
            '002_second.sql',
            'add_column.sql',
        ], $result);
    }

    public function testSortOrderTimestampedAfterNonNumbered(): void
    {
        touch($this->tempDir . '/fix_data.sql');
        touch($this->tempDir . '/20260226_120000_new_table.sql');
        touch($this->tempDir . '/003_third.sql');

        $resolver = new MigrationFileResolver($this->tempDir);
        $result = $resolver->getAvailableMigrations();

        $this->assertSame([
            '003_third.sql',
            'fix_data.sql',
            '20260226_120000_new_table.sql',
        ], $result);
    }

    public function testNaturalSortWithinCategory(): void
    {
        touch($this->tempDir . '/009_ninth.sql');
        touch($this->tempDir . '/010_tenth.sql');
        touch($this->tempDir . '/1_first.sql');
        touch($this->tempDir . '/2_second.sql');

        $resolver = new MigrationFileResolver($this->tempDir);
        $result = $resolver->getAvailableMigrations();

        $this->assertSame([
            '1_first.sql',
            '2_second.sql',
            '009_ninth.sql',
            '010_tenth.sql',
        ], $result);
    }

    public function testGetFullPathReturnsCorrectPath(): void
    {
        $resolver = new MigrationFileResolver($this->tempDir);
        $this->assertSame(
            $this->tempDir . '/001_test.sql',
            $resolver->getFullPath('001_test.sql'),
        );
    }

    public function testGetFullPathHandlesTrailingSlash(): void
    {
        $resolver = new MigrationFileResolver($this->tempDir . '/');
        $this->assertSame(
            $this->tempDir . '/001_test.sql',
            $resolver->getFullPath('001_test.sql'),
        );
    }

    public function testMixedExtensions(): void
    {
        touch($this->tempDir . '/001_schema.sql');
        touch($this->tempDir . '/migrate_data.php');
        touch($this->tempDir . '/20260301_000000_future.sql');
        touch($this->tempDir . '/README.md');

        $resolver = new MigrationFileResolver($this->tempDir);
        $result = $resolver->getAvailableMigrations();

        $this->assertSame([
            '001_schema.sql',
            'migrate_data.php',
            '20260301_000000_future.sql',
        ], $result);
    }
}
