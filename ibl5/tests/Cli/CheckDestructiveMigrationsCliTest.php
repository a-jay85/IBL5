<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class CheckDestructiveMigrationsCliTest extends TestCase
{
    private string $scriptPath;
    private string $tmpDir;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/check-destructive-migrations');
        self::assertNotFalse($resolved, 'bin/check-destructive-migrations must exist');
        $this->scriptPath = $resolved;

        $this->tmpDir = sys_get_temp_dir() . '/destr-mig-test-' . bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0755, true);

        $this->runInDir('git init -b main');
        $this->runInDir('git config user.email "test@test.com"');
        $this->runInDir('git config user.name "Test"');

        mkdir($this->tmpDir . '/ibl5/migrations', 0755, true);
        file_put_contents($this->tmpDir . '/ibl5/migrations/.gitkeep', '');
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "initial"');
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    public function testCleanMigrationExitsZero(): void
    {
        $this->writeMigration('100_clean.sql', "ALTER TABLE foo ADD COLUMN bar VARCHAR(50) DEFAULT NULL;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('No destructive migration patterns detected', $result['output']);
    }

    public function testDropColumnExitsOne(): void
    {
        $this->writeMigration('101_drop_col.sql', "ALTER TABLE foo DROP COLUMN bar;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('drop-column', $result['output']);
        self::assertStringContainsString('101_drop_col.sql', $result['output']);
    }

    public function testDropTableExitsOne(): void
    {
        $this->writeMigration('102_drop_tbl.sql', "DROP TABLE foo;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('drop-table', $result['output']);
    }

    public function testDropIfExistsWithRecreateSuppressed(): void
    {
        $sql = "DROP TABLE IF EXISTS `foo`;\nCREATE TABLE `foo` (id INT PRIMARY KEY);\n";
        $this->writeMigration('103_recreate.sql', $sql);
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testDropIfExistsWithoutRecreateExitsOne(): void
    {
        $this->writeMigration('104_drop_only.sql', "DROP TABLE IF EXISTS `foo`;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('drop-table', $result['output']);
    }

    public function testTruncateExitsOne(): void
    {
        $this->writeMigration('105_truncate.sql', "TRUNCATE TABLE foo;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('truncate', $result['output']);
    }

    public function testRenameColumnExitsOne(): void
    {
        $this->writeMigration('106_rename.sql', "ALTER TABLE foo RENAME COLUMN a TO b;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('rename-column', $result['output']);
    }

    public function testAddNotNullNoDefaultExitsOne(): void
    {
        $this->writeMigration('107_notnull.sql', "ALTER TABLE foo ADD COLUMN bar INT NOT NULL;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('add-not-null-no-default', $result['output']);
    }

    public function testAddNotNullWithDefaultExitsZero(): void
    {
        $this->writeMigration('108_notnull_default.sql', "ALTER TABLE foo ADD COLUMN bar INT NOT NULL DEFAULT 0;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testInlineBypassExitsZero(): void
    {
        $sql = "-- destructive-migration: dropping unused legacy column after data migration confirmed\n";
        $sql .= "ALTER TABLE foo DROP COLUMN bar;\n";
        $this->writeMigration('109_bypassed.sql', $sql);
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('PASS (bypass)', $result['output']);
    }

    public function testShortInlineBypassExitsOne(): void
    {
        $sql = "-- destructive-migration: short\n";
        $sql .= "ALTER TABLE foo DROP COLUMN bar;\n";
        $this->writeMigration('110_short_bypass.sql', $sql);
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('drop-column', $result['output']);
    }

    public function testPrBodyBypassExitsZero(): void
    {
        $this->writeMigration('111_pr_bypass.sql', "ALTER TABLE foo DROP COLUMN bar;\n");
        $this->runInDir('git add -A');

        $prBody = '<!-- destructive-migration: removing deprecated column after successful data migration -->';
        $result = $this->runScript(['--bypass-from-stdin'], $prBody);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('PASS (bypass)', $result['output']);
    }

    public function testShortPrBodyBypassExitsOne(): void
    {
        $this->writeMigration('112_short_pr.sql', "ALTER TABLE foo DROP COLUMN bar;\n");
        $this->runInDir('git add -A');

        $prBody = '<!-- destructive-migration: too short -->';
        $result = $this->runScript(['--bypass-from-stdin'], $prBody);

        self::assertSame(1, $result['exit']);
        self::assertStringContainsString('drop-column', $result['output']);
    }

    public function testBaselineFileAlwaysSkipped(): void
    {
        file_put_contents(
            $this->tmpDir . '/ibl5/migrations/000_baseline_schema.sql',
            "DROP TABLE IF EXISTS foo;\nCREATE TABLE foo (id INT);\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testStagedAndSinceModesAgree(): void
    {
        $this->writeMigration('113_staged_since.sql', "ALTER TABLE foo DROP COLUMN bar;\n");
        $this->runInDir('git add -A');

        $stagedResult = $this->runScript(['--staged']);
        self::assertSame(1, $stagedResult['exit'], 'staged mode should detect');

        $rawSha = shell_exec('git -C ' . escapeshellarg($this->tmpDir) . ' rev-parse HEAD');
        $baseSha = trim($rawSha !== false && $rawSha !== null ? $rawSha : '');

        $this->runInDir('git commit -m "add destructive migration"');

        $sinceResult = $this->runScript(['--since=' . $baseSha]);
        self::assertSame(1, $sinceResult['exit'], 'since mode should detect');

        self::assertStringContainsString('drop-column', $stagedResult['output']);
        self::assertStringContainsString('drop-column', $sinceResult['output']);
    }

    public function testHelpFlagExitsZero(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellcmd($this->scriptPath) . ' --help 2>&1', $output, $exit);

        self::assertSame(0, $exit);
        $text = implode("\n", $output);
        self::assertStringContainsString('Usage:', $text);
        self::assertStringContainsString('NOT scanned', $text);
    }

    public function testBogusArgExitsTwo(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellcmd($this->scriptPath) . ' --bogus 2>&1', $output, $exit);

        self::assertSame(2, $exit);
        self::assertStringContainsString('unknown flag', implode("\n", $output));
    }

    /**
     * @param list<string> $args
     * @return array{output: string, exit: int}
     */
    private function runScript(array $args = [], ?string $stdin = null): array
    {
        $output = [];
        $exit = 0;

        $argStr = '';
        if ($args === []) {
            $argStr = ' --staged';
        }
        foreach ($args as $arg) {
            $argStr .= ' ' . escapeshellarg($arg);
        }

        $cmd = 'cd ' . escapeshellarg($this->tmpDir) . ' && ';

        if ($stdin !== null) {
            $cmd .= 'echo ' . escapeshellarg($stdin) . ' | ';
        }

        $cmd .= 'bash ' . escapeshellarg($this->scriptPath) . $argStr . ' 2>&1';

        exec($cmd, $output, $exit);

        return ['output' => implode("\n", $output), 'exit' => $exit];
    }

    private function writeMigration(string $filename, string $content): void
    {
        file_put_contents($this->tmpDir . '/ibl5/migrations/' . $filename, $content);
    }

    private function runInDir(string $cmd): void
    {
        exec('cd ' . escapeshellarg($this->tmpDir) . ' && ' . $cmd . ' 2>&1');
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
