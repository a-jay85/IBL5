<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Exercises the destructive-migration block of bin/pre-commit-hook inside a
 * scratch git repo. The scratch repo has no origin remote, so the ADR gate and
 * the doc-bump gate self-skip exactly as on a fetch-less clone.
 */
#[Group('cli')]
final class PreCommitHookDestructiveScanTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $repoRoot = realpath(__DIR__ . '/../../..');
        self::assertNotFalse($repoRoot, 'repo root must resolve');

        $this->tmpDir = sys_get_temp_dir() . '/precommit-destr-test-' . bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0755, true);

        $this->runInDir('git init -b main');
        $this->runInDir('git config user.email "test@test.com"');
        $this->runInDir('git config user.name "Test"');

        mkdir($this->tmpDir . '/bin/lib', 0755, true);
        foreach (
            [
                'bin/pre-commit-hook',
                'bin/check-destructive-migrations',
                'bin/lib/destructive-migration-scan.py',
                'bin/lib/shell-scripts.sh',
            ] as $rel
        ) {
            $src = $repoRoot . '/' . $rel;
            self::assertFileExists($src);
            $dest = $this->tmpDir . '/' . $rel;
            copy($src, $dest);
            $mode = fileperms($src);
            self::assertNotFalse($mode);
            chmod($dest, $mode & 0777);
        }

        mkdir($this->tmpDir . '/ibl5/migrations', 0755, true);
        file_put_contents($this->tmpDir . '/ibl5/migrations/.gitkeep', '');
        $this->runInDir('git add ibl5/migrations/.gitkeep');
        $this->runInDir('git commit -m "initial"');
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    public function testHookBlocksStagedDestructiveMigration(): void
    {
        $this->stageFile('ibl5/migrations/201_x.sql', "DROP TABLE t;\n");

        $result = $this->runHook();

        self::assertSame(1, $result['exit'], "stdout: {$result['stdout']}\nstderr: {$result['stderr']}");
        self::assertStringContainsString('[drop-table]', $result['stdout']);
        self::assertStringContainsString('destructive-migration[', $result['stdout']);
    }

    public function testHookPassesBenignMigration(): void
    {
        $this->stageFile('ibl5/migrations/201_x.sql', "INSERT INTO t (a) VALUES (1);\n");

        $result = $this->runHook();

        self::assertSame(0, $result['exit'], "stdout: {$result['stdout']}\nstderr: {$result['stderr']}");
    }

    public function testHookPassesPhpMigrationWithWhere(): void
    {
        $php = "<?php\n\$db->prepare('DELETE FROM t WHERE id = ?');\n";
        $this->stageFile('ibl5/migrations/201_x.php', $php);

        $result = $this->runHook();

        self::assertSame(0, $result['exit'], "stdout: {$result['stdout']}\nstderr: {$result['stderr']}");
    }

    public function testHookBlocksPhpHeredocDropColumn(): void
    {
        $php = "<?php\n\$sql = <<<SQL\nALTER TABLE t DROP COLUMN c;\nSQL;\n\$db->exec(\$sql);\n";
        $this->stageFile('ibl5/migrations/201_x.php', $php);

        $result = $this->runHook();

        self::assertSame(1, $result['exit'], "stdout: {$result['stdout']}\nstderr: {$result['stderr']}");
        self::assertStringContainsString('[drop-column]', $result['stdout']);
    }

    public function testHookSkipsLoudlyWithoutPython3(): void
    {
        $this->stageFile('ibl5/migrations/201_x.sql', "DROP TABLE t;\n");

        $binDir = $this->tmpDir . '/path-bin';
        mkdir($binDir, 0755, true);
        foreach (['bash', 'git', 'grep', 'sed', 'cat', 'dirname', 'mktemp', 'rm', 'awk', 'xargs'] as $tool) {
            $target = $this->findOnPath($tool);
            self::assertNotNull($target, "{$tool} must be on PATH to build the restricted PATH");
            symlink($target, $binDir . '/' . $tool);
        }

        $result = $this->runHook($binDir);

        self::assertSame(0, $result['exit'], "stdout: {$result['stdout']}\nstderr: {$result['stderr']}");
        self::assertStringContainsString('SKIPPED destructive migration scan', $result['stderr']);
    }

    public function testHookIgnoresNonMigrationFiles(): void
    {
        $this->stageFile('notes.txt', "DROP TABLE t;\n");

        $result = $this->runHook();

        self::assertSame(0, $result['exit'], "stdout: {$result['stdout']}\nstderr: {$result['stderr']}");
        self::assertStringNotContainsString('destructive', $result['stdout']);
    }

    public function testExcludeFromSkipsListedStagedMigration(): void
    {
        $this->stageFile('ibl5/migrations/201_x.sql', "DROP TABLE t;\n");
        $excludeFile = $this->writeExcludeFile("ibl5/migrations/201_x.sql\n");

        $result = $this->runScan(['--staged', '--exclude-from=' . $excludeFile]);

        self::assertSame(0, $result['exit'], "stdout: {$result['stdout']}\nstderr: {$result['stderr']}");
        self::assertStringNotContainsString('[drop-table]', $result['stdout']);
    }

    public function testExcludeFromStillScansUnlistedMigration(): void
    {
        $this->stageFile('ibl5/migrations/201_x.sql', "DROP TABLE t;\n");
        $excludeFile = $this->writeExcludeFile("ibl5/migrations/202_other.sql\n\n");

        $result = $this->runScan(['--staged', '--exclude-from=' . $excludeFile]);

        self::assertSame(1, $result['exit'], "stdout: {$result['stdout']}\nstderr: {$result['stderr']}");
        self::assertStringContainsString('ibl5/migrations/201_x.sql', $result['stdout']);
        self::assertStringContainsString('[drop-table]', $result['stdout']);
    }

    public function testExcludeFromMissingFileExitsTwo(): void
    {
        $this->stageFile('ibl5/migrations/201_x.sql', "DROP TABLE t;\n");
        $missing = $this->tmpDir . '/nonexistent-exclude-list';

        $result = $this->runScan(['--staged', '--exclude-from=' . $missing]);

        self::assertSame(2, $result['exit'], "stdout: {$result['stdout']}\nstderr: {$result['stderr']}");
        self::assertStringContainsString('cannot read --exclude-from file', $result['stderr']);
        self::assertStringContainsString($missing, $result['stderr']);
    }

    public function testExcludeFromSpaceFormIsRejected(): void
    {
        $this->stageFile('ibl5/migrations/201_x.sql', "DROP TABLE t;\n");
        $excludeFile = $this->writeExcludeFile("ibl5/migrations/201_x.sql\n");

        $result = $this->runScan(['--staged', '--exclude-from', $excludeFile]);

        self::assertSame(2, $result['exit'], "stdout: {$result['stdout']}\nstderr: {$result['stderr']}");
        self::assertStringContainsString('unknown flag: --exclude-from', $result['stderr']);
    }

    /**
     * Write an --exclude-from list to an absolute path outside the scratch work tree.
     */
    private function writeExcludeFile(string $content): string
    {
        $path = $this->tmpDir . '/.git/test-exclude-list';
        file_put_contents($path, $content);
        return $path;
    }

    /**
     * Run ./bin/check-destructive-migrations with $args from the scratch root.
     *
     * @param list<string> $args
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function runScan(array $args): array
    {
        $proc = proc_open(
            array_merge(['./bin/check-destructive-migrations'], $args),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->tmpDir
        );
        self::assertIsResource($proc);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        return [
            'exit' => $exit,
            'stdout' => $stdout === false ? '' : $stdout,
            'stderr' => $stderr === false ? '' : $stderr,
        ];
    }

    /**
     * Run ./bin/pre-commit-hook from the scratch root.
     *
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function runHook(?string $path = null): array
    {
        $env = null;
        if ($path !== null) {
            $env = ['PATH' => $path, 'HOME' => $this->tmpDir];
        }

        $proc = proc_open(
            ['./bin/pre-commit-hook'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->tmpDir,
            $env
        );
        self::assertIsResource($proc);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);

        return [
            'exit' => $exit,
            'stdout' => $stdout === false ? '' : $stdout,
            'stderr' => $stderr === false ? '' : $stderr,
        ];
    }

    private function findOnPath(string $tool): ?string
    {
        $path = getenv('PATH');
        if ($path === false) {
            return null;
        }
        foreach (explode(':', $path) as $dir) {
            $candidate = $dir . '/' . $tool;
            if ($dir !== '' && is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function stageFile(string $relPath, string $content): void
    {
        $full = $this->tmpDir . '/' . $relPath;
        $dir = dirname($full);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($full, $content);
        $this->runInDir('git add ' . escapeshellarg($relPath));
    }

    private function runInDir(string $cmd): void
    {
        exec('cd ' . escapeshellarg($this->tmpDir) . ' && ' . $cmd . ' 2>&1');
    }

    private function recursiveRm(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
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
            if (is_dir($path) && !is_link($path)) {
                $this->recursiveRm($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
