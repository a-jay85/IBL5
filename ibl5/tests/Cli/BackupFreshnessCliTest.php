<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class BackupFreshnessCliTest extends TestCase
{
    private string $scriptPath;
    private string $fixtureDir;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/check-backup-freshness');
        self::assertNotFalse($resolved, 'bin/check-backup-freshness must exist');
        $this->scriptPath = $resolved;

        $this->fixtureDir = sys_get_temp_dir() . '/backup-freshness-test-' . bin2hex(random_bytes(4));
        mkdir($this->fixtureDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->fixtureDir)) {
            $this->recursiveRm($this->fixtureDir);
        }
    }

    public function testFreshSnapshotExitsZero(): void
    {
        touch($this->fixtureDir . '/backup.sql.gz');

        $result = $this->runScript(backupDir: $this->fixtureDir);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('OK', $result['output']);
    }

    public function testStaleSnapshotExitsOneWithActionableMessage(): void
    {
        $file = $this->fixtureDir . '/old.sql.gz';
        touch($file, time() - (48 * 3600));

        $result = $this->runScript(backupDir: $this->fixtureDir, maxAgeHours: 26);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('trigger a manual snapshot', strtolower($result['output']));
    }

    public function testMissingDirectoryExitsOne(): void
    {
        $result = $this->runScript(backupDir: '/nonexistent/path/does-not-exist');

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('backup directory not found', $result['output']);
    }

    public function testEmptyDirectoryExitsOne(): void
    {
        $result = $this->runScript(backupDir: $this->fixtureDir);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('no backup files found', $result['output']);
    }

    public function testSkipBypassExitsZeroWithWarning(): void
    {
        $file = $this->fixtureDir . '/old.sql.gz';
        touch($file, time() - (48 * 3600));

        $result = $this->runScript(backupDir: $this->fixtureDir, skip: true);

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('WARNING', $result['output']);
        self::assertStringContainsString('bypassed', $result['output']);
    }

    public function testMaxAgeOverrideHonored(): void
    {
        $file = $this->fixtureDir . '/recent-ish.sql.gz';
        touch($file, time() - (48 * 3600));

        $result = $this->runScript(backupDir: $this->fixtureDir, maxAgeHours: 72);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('OK', $result['output']);
    }

    public function testHelpExitsZero(): void
    {
        $result = $this->runRaw('--help');

        self::assertSame(0, $result['exit']);
        self::assertStringContainsString('BACKUP_DIR', $result['output']);
        self::assertStringContainsString('BACKUP_MAX_AGE_HOURS', $result['output']);
        self::assertStringContainsString('SKIP_BACKUP_CHECK', $result['output']);
    }

    public function testMissingBackupDirEnvExitsTwo(): void
    {
        $result = $this->runRaw('');

        self::assertSame(2, $result['exit']);
        self::assertStringContainsString('BACKUP_DIR is required', $result['output']);
    }

    public function testRecognizesDirectoryEntriesNotJustFiles(): void
    {
        $subdir = $this->fixtureDir . '/2024-01-15';
        mkdir($subdir, 0755, true);
        touch($subdir);

        $result = $this->runScript(backupDir: $this->fixtureDir);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('OK', $result['output']);
    }

    /**
     * @return array{output: string, exit: int}
     */
    private function runScript(
        string $backupDir,
        int $maxAgeHours = 26,
        bool $skip = false,
    ): array {
        $env = sprintf('BACKUP_DIR=%s BACKUP_MAX_AGE_HOURS=%d', escapeshellarg($backupDir), $maxAgeHours);
        if ($skip) {
            $env .= ' SKIP_BACKUP_CHECK=1';
        }

        $cmd = sprintf('%s %s 2>&1', $env, escapeshellcmd($this->scriptPath));
        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);

        return ['output' => implode("\n", $output), 'exit' => $exit];
    }

    /**
     * @return array{output: string, exit: int}
     */
    private function runRaw(string $args): array
    {
        $cmd = escapeshellcmd($this->scriptPath) . ' ' . $args . ' 2>&1';
        $output = [];
        $exit = 0;
        exec($cmd, $output, $exit);

        return ['output' => implode("\n", $output), 'exit' => $exit];
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
