<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;

final class GoldenSnapshotTraitTest extends TestCase
{
    use GoldenSnapshotTrait;

    private string $tempDirectory;
    private string|false $originalEnv;

    protected function setUp(): void
    {
        $this->originalEnv = getenv('UPDATE_SNAPSHOTS');
        putenv('UPDATE_SNAPSHOTS');
        $this->tempDirectory = sys_get_temp_dir() . '/golden-snapshot-trait-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        putenv($this->originalEnv === false ? 'UPDATE_SNAPSHOTS' : 'UPDATE_SNAPSHOTS=' . $this->originalEnv);

        if (is_dir($this->tempDirectory)) {
            $files = glob($this->tempDirectory . '/*');
            foreach ($files === false ? [] : $files as $file) {
                unlink($file);
            }
            rmdir($this->tempDirectory);
        }
    }

    protected function snapshotDirectory(): string
    {
        return $this->tempDirectory;
    }

    private function seedSnapshot(string $name, string $contents): string
    {
        mkdir($this->tempDirectory, 0755, true);
        $path = $this->tempDirectory . '/' . $name;
        file_put_contents($path, $contents);

        return $path;
    }

    public function testMissingSnapshotFailsWithRegenerationHint(): void
    {
        try {
            $this->assertSnapshotMatches('<p>x</p>', 'a.golden.html');
            self::fail('expected a missing-snapshot failure');
        } catch (\Throwable $e) {
            self::assertStringContainsString('run with UPDATE_SNAPSHOTS=1 to create it', $e->getMessage());
        }

        self::assertFileDoesNotExist($this->tempDirectory . '/a.golden.html');
    }

    public function testExistingMatchingSnapshotPasses(): void
    {
        $path = $this->seedSnapshot('a.golden.html', '<p>x</p>');

        $this->assertSnapshotMatches('<p>x</p>', 'a.golden.html');

        self::assertSame('<p>x</p>', file_get_contents($path));
    }

    public function testMismatchedSnapshotFailsAndLeavesFileUntouched(): void
    {
        $path = $this->seedSnapshot('a.golden.html', 'golden');

        try {
            $this->assertSnapshotMatches('drifted', 'a.golden.html');
            self::fail('expected a snapshot mismatch failure');
        } catch (ExpectationFailedException $e) {
            self::assertStringContainsString('Snapshot mismatch for a.golden.html', $e->getMessage());
            self::assertStringContainsString('UPDATE_SNAPSHOTS=1', $e->getMessage());
        }

        self::assertSame('golden', file_get_contents($path));
    }

    public function testUpdateSnapshotsOneCreatesMissingSnapshotAndDirectory(): void
    {
        putenv('UPDATE_SNAPSHOTS=1');
        self::assertDirectoryDoesNotExist($this->tempDirectory);

        $this->assertSnapshotMatches('<p>new</p>', 'a.golden.html');

        $path = $this->tempDirectory . '/a.golden.html';
        self::assertFileExists($path);
        self::assertSame('<p>new</p>', file_get_contents($path));
    }

    public function testUpdateSnapshotsOneOverwritesDriftedSnapshotWithoutComparing(): void
    {
        putenv('UPDATE_SNAPSHOTS=1');
        $path = $this->seedSnapshot('a.golden.html', 'old');

        $this->assertSnapshotMatches('new', 'a.golden.html');

        self::assertSame('new', file_get_contents($path));
    }

    public function testEmptyUpdateSnapshotsValueDoesNotWrite(): void
    {
        putenv('UPDATE_SNAPSHOTS=');

        try {
            $this->assertSnapshotMatches('<p>x</p>', 'a.golden.html');
            self::fail('expected a missing-snapshot failure');
        } catch (\Throwable $e) {
            self::assertStringContainsString('run with UPDATE_SNAPSHOTS=1 to create it', $e->getMessage());
        }

        self::assertFileDoesNotExist($this->tempDirectory . '/a.golden.html');
    }

    public function testZeroUpdateSnapshotsValueDoesNotWrite(): void
    {
        putenv('UPDATE_SNAPSHOTS=0');

        try {
            $this->assertSnapshotMatches('<p>x</p>', 'a.golden.html');
            self::fail('expected a missing-snapshot failure');
        } catch (\Throwable $e) {
            self::assertStringContainsString('run with UPDATE_SNAPSHOTS=1 to create it', $e->getMessage());
        }

        self::assertFileDoesNotExist($this->tempDirectory . '/a.golden.html');
    }

    public function testDefaultSnapshotDirectoryIsBesideTheUsingTestFile(): void
    {
        $probe = new class ('probe') extends TestCase {
            use GoldenSnapshotTrait;

            public function directory(): string
            {
                return $this->snapshotDirectory();
            }
        };

        self::assertSame(__DIR__ . '/__snapshots__', $probe->directory());
    }
}
