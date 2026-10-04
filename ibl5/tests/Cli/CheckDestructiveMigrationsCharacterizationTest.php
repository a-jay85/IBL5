<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Characterization suite pinning the current behavior of
 * bin/check-destructive-migrations, one pattern at a time.
 *
 * The master engine matches line by line, so every fixture here keeps each
 * trigger clause whole on one physical line while the STATEMENT may span
 * several lines. Shapes where the keyword pair is split across lines
 * (`ALTER TABLE t` on line 1, `DROP COLUMN c` on line 2) do not match on
 * master; those belong to the new-engine positive tests, never to this file.
 * Do not "fix" the fixtures to flag them here.
 *
 * Assertions cover only the exit code and the bracketed pattern tag, never the
 * `file:line` suffix or the echoed statement text.
 */
#[Group('cli')]
final class CheckDestructiveMigrationsCharacterizationTest extends TestCase
{
    private string $scriptPath;
    private string $tmpDir;
    private int $migrationSeq = 200;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/check-destructive-migrations');
        self::assertNotFalse($resolved, 'bin/check-destructive-migrations must exist');
        $this->scriptPath = $resolved;

        $this->tmpDir = sys_get_temp_dir() . '/destr-mig-char-' . bin2hex(random_bytes(8));
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

    public function testCharDropColumnSingleLine(): void
    {
        $this->assertFlags("ALTER TABLE ibl_x DROP COLUMN legacy_flag;\n", 'drop-column');
    }

    public function testCharDropColumnMultiLineStatement(): void
    {
        $this->assertFlags(
            "ALTER TABLE ibl_x DROP COLUMN legacy_flag,\n"
            . "  ADD COLUMN new_flag TINYINT NOT NULL DEFAULT 0;\n",
            'drop-column',
        );
    }

    public function testCharDropTableSingleLine(): void
    {
        $this->assertFlags("DROP TABLE ibl_scratch;\n", 'drop-table');
    }

    public function testCharDropTableMultiLineStatement(): void
    {
        $this->assertFlags("DROP TABLE\n  ibl_scratch;\n", 'drop-table');
    }

    public function testCharDropTableIfExistsRecreateSuppressed(): void
    {
        $this->assertClean(
            "DROP TABLE IF EXISTS ibl_scratch;\n"
            . "CREATE TABLE ibl_scratch (\n"
            . "  id INT NOT NULL\n"
            . ");\n",
        );
    }

    public function testCharTruncateSingleAndMultiLine(): void
    {
        $this->assertFlags("TRUNCATE TABLE ibl_scratch;\n", 'truncate');
        $this->assertFlags("TRUNCATE TABLE\n  ibl_scratch;\n", 'truncate');
    }

    public function testCharRenameColumnViaRenameColumn(): void
    {
        $this->assertFlags("ALTER TABLE ibl_x RENAME COLUMN old_name TO new_name;\n", 'rename-column');
        $this->assertFlags(
            "ALTER TABLE ibl_x RENAME COLUMN old_name TO new_name,\n"
            . "  ADD INDEX idx_new (new_name);\n",
            'rename-column',
        );
    }

    public function testCharRenameColumnViaChangeContinuationLine(): void
    {
        $this->assertFlags(
            "ALTER TABLE ibl_x\n"
            . "  CHANGE old_name new_name VARCHAR(32) NOT NULL DEFAULT '';\n",
            'rename-column',
        );
    }

    public function testCharAddNotNullNoDefaultSingleAndContinuation(): void
    {
        $this->assertFlags("ALTER TABLE ibl_x ADD COLUMN c INT NOT NULL;\n", 'add-not-null-no-default');
        $this->assertFlags("ALTER TABLE ibl_x\n  ADD COLUMN c INT NOT NULL;\n", 'add-not-null-no-default');
    }

    public function testCharAddNotNullWithDefaultIsClean(): void
    {
        $this->assertClean("ALTER TABLE ibl_x ADD COLUMN c INT NOT NULL DEFAULT 0;\n");
    }

    public function testCharTightenNotNullModifyAndSameNameChange(): void
    {
        $this->assertFlags("ALTER TABLE ibl_x MODIFY c INT NOT NULL;\n", 'tighten-not-null');
        $this->assertFlags("ALTER TABLE ibl_x\n  MODIFY COLUMN c INT NOT NULL;\n", 'tighten-not-null');
        $this->assertFlags("ALTER TABLE ibl_x\n  CHANGE c c INT NOT NULL;\n", 'tighten-not-null');
    }

    public function testCharTightenNotNullWithDefaultIsClean(): void
    {
        $this->assertClean("ALTER TABLE ibl_x CHANGE c c INT NOT NULL DEFAULT 0;\n");
    }

    public function testCharDropIndexSingleAndContinuation(): void
    {
        $this->assertFlags("ALTER TABLE ibl_x DROP INDEX idx_old;\n", 'drop-index');
        $this->assertFlags("ALTER TABLE ibl_x\n  DROP KEY idx_old;\n", 'drop-index');
    }

    public function testCharDropIndexReaddSuppressed(): void
    {
        $this->assertClean(
            "ALTER TABLE ibl_x DROP INDEX idx_old;\n"
            . "ALTER TABLE ibl_x ADD INDEX idx_old (c, d);\n",
        );
    }

    public function testCharRenameTableBothForms(): void
    {
        $this->assertFlags("RENAME TABLE ibl_a TO ibl_b;\n", 'rename-table');
        $this->assertFlags("ALTER TABLE ibl_a\n  RENAME TO ibl_b;\n", 'rename-table');
    }

    /**
     * Master strips string literals only for the CHANGE, MODIFY, DROP INDEX
     * and RENAME TO checks (DROP TABLE and TRUNCATE inside a string still fire
     * there), so this fixture uses those clause keywords.
     */
    public function testCharKeywordsInStringLiteralIsClean(): void
    {
        $this->assertClean(
            "INSERT INTO ibl_log (msg) VALUES ('later, change old_name new_name, modify c INT NOT NULL, drop index idx_old');\n"
        );
    }

    /**
     * Stages $sql as its own migration file and asserts the script exits 1
     * and prints the bracketed $tag.
     */
    private function assertFlags(string $sql, string $tag): void
    {
        $this->stageMigration($sql, $tag);

        $result = $this->runScript(['--staged']);

        self::assertSame(1, $result['exit'], "Expected exit 1 for [$tag]. Output: {$result['output']}");
        self::assertTrue(
            str_contains($result['output'], "[$tag]"),
            "Expected [$tag] in output. Output: {$result['output']}",
        );
    }

    /**
     * Stages $sql as its own migration file and asserts the script exits 0
     * with the clean-pass line.
     */
    private function assertClean(string $sql): void
    {
        $this->stageMigration($sql, 'clean');

        $result = $this->runScript(['--staged']);

        self::assertSame(0, $result['exit'], "Expected exit 0. Output: {$result['output']}");
        self::assertTrue(
            str_contains($result['output'], 'No destructive migration patterns detected.'),
            "Expected clean-pass line. Output: {$result['output']}",
        );
    }

    /**
     * Each call stages only its own file: previously staged migrations are
     * committed first so a later run cannot see them.
     */
    private function stageMigration(string $sql, string $tag): void
    {
        $this->runInDir('git add -A');
        $this->runInDir('git commit --allow-empty -q -m "prev"');
        $this->migrationSeq++;
        $this->writeMigration($this->migrationSeq . '_' . $tag . '.sql', $sql);
        $this->runInDir('git add -A');
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
