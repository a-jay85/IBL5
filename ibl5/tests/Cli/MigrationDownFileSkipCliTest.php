<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * A `.down.sql` file is the revert partner of an up migration. Every script
 * that walks the migrations directory must skip it, or the revert would run
 * as if it were a forward migration.
 */
#[Group('cli')]
final class MigrationDownFileSkipCliTest extends TestCase
{
    private string $repoRoot;
    private string $tmpDir;
    private string $stubDir;
    private string $migrationsDir;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../..');
        self::assertNotFalse($resolved, 'repo root must resolve');
        $this->repoRoot = $resolved;

        $this->tmpDir = sys_get_temp_dir() . '/mig-down-skip-' . bin2hex(random_bytes(8));
        $this->stubDir = $this->tmpDir . '/stubs';
        $this->migrationsDir = $this->tmpDir . '/migrations';
        mkdir($this->stubDir, 0755, true);
        mkdir($this->migrationsDir, 0755, true);

        file_put_contents($this->migrationsDir . '/01_a.sql', "SELECT 1;\n");
        file_put_contents($this->migrationsDir . '/01_a.down.sql', "-- restores: foo\nSELECT 2;\n");
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    public function testRunMigrationsCiSkipsDownFiles(): void
    {
        $this->writeStub('mysql', "#!/bin/sh\ncat >/dev/null\nexit 0\n");

        $result = $this->runWithStubs(
            'RUN_MIGRATIONS_CI_DIR=' . escapeshellarg($this->migrationsDir)
            . ' bash ' . escapeshellarg($this->repoRoot . '/ibl5/bin/run-migrations-ci') . ' --fake-arg'
        );

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('01_a.sql', $result['output']);
        self::assertStringNotContainsString('01_a.down.sql', $result['output']);
    }

    public function testDbMigrateSkipsDownFiles(): void
    {
        $this->writeStub('docker', "#!/bin/sh\ncat >/dev/null 2>&1 </dev/null\necho 0\nexit 0\n");

        $result = $this->runWithStubs(
            'bash ' . escapeshellarg($this->repoRoot . '/bin/db-migrate')
            . ' fake ' . escapeshellarg($this->migrationsDir)
        );

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('Applying 01_a.sql...', $result['output']);
        self::assertStringNotContainsString('01_a.down.sql', $result['output']);
    }

    public function testReadOnlyGlobSitesSkipDownFiles(): void
    {
        $sites = [
            'ibl5/bin/e2e-local' => 'migrations/*.sql; do',
            'bin/check-old-code-compat' => '"$MIGRATIONS_DIR"/*.sql; do',
            'ibl5/bin/check-seed-id-uniqueness' => "glob('migrations/*.sql')",
        ];

        foreach ($sites as $relPath => $globNeedle) {
            $lines = file($this->repoRoot . '/' . $relPath, FILE_IGNORE_NEW_LINES);
            self::assertIsArray($lines, $relPath);

            $globIndex = null;
            foreach ($lines as $i => $line) {
                if (str_contains($line, $globNeedle)) {
                    $globIndex = $i;
                    break;
                }
            }
            self::assertNotNull($globIndex, "$relPath: glob line not found");

            $following = implode("\n", array_slice($lines, $globIndex + 1, 3));
            self::assertStringContainsString(
                '.down.sql',
                $following,
                "$relPath must skip .down.sql within three lines of its migrations glob"
            );
        }
    }

    private function writeStub(string $name, string $body): void
    {
        $path = $this->stubDir . '/' . $name;
        file_put_contents($path, $body);
        chmod($path, 0755);
    }

    /**
     * @return array{exit: int, output: string}
     */
    private function runWithStubs(string $command): array
    {
        $path = getenv('PATH');
        $output = [];
        $exit = 0;
        exec(
            'cd ' . escapeshellarg($this->repoRoot)
            . ' && PATH=' . escapeshellarg($this->stubDir . ':' . ($path !== false ? $path : ''))
            . ' ' . $command . ' 2>&1',
            $output,
            $exit
        );

        return ['exit' => $exit, 'output' => implode("\n", $output)];
    }

    private function recursiveRm(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->recursiveRm($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
