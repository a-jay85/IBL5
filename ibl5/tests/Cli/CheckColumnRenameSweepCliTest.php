<?php

declare(strict_types=1);

namespace Tests\Cli;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

#[Group('cli')]
final class CheckColumnRenameSweepCliTest extends TestCase
{
    private string $scriptPath;
    private string $tmpDir;

    protected function setUp(): void
    {
        $resolved = realpath(__DIR__ . '/../../../bin/check-column-rename-sweep');
        self::assertNotFalse($resolved, 'bin/check-column-rename-sweep must exist');
        $this->scriptPath = $resolved;

        $this->tmpDir = sys_get_temp_dir() . '/col-rename-sweep-test-' . bin2hex(random_bytes(8));
        mkdir($this->tmpDir, 0755, true);

        mkdir($this->tmpDir . '/ibl5/migrations', 0755, true);
        mkdir($this->tmpDir . '/ibl5/scripts', 0755, true);
        mkdir($this->tmpDir . '/ibl5/bin', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveRm($this->tmpDir);
    }

    public function testStaleRefFires(): void
    {
        $this->writeMigration(
            '100_rename_legacyZorp.sql',
            "ALTER TABLE foo CHANGE COLUMN `legacyZorp` `new_zorp` INT;\n"
        );
        file_put_contents($this->tmpDir . '/ibl5/scripts/foo.php', "SELECT legacyZorp FROM foo;\n");
        $colFile = $this->writeColumnsFile(['new_zorp', 'other_col']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('legacyZorp', $result['output']);
        self::assertStringContainsString('100_rename_legacyZorp.sql', $result['output']);
    }

    public function testFalsePositiveFilterSkips(): void
    {
        // Same rename, but old name is still present in the live schema (the
        // name→setting_key shape: 'name' still exists in ibl_plr etc).
        $this->writeMigration(
            '100_rename_legacyZorp.sql',
            "ALTER TABLE foo CHANGE COLUMN `legacyZorp` `new_zorp` INT;\n"
        );
        file_put_contents($this->tmpDir . '/ibl5/scripts/foo.php', "SELECT legacyZorp FROM foo;\n");
        // legacyZorp is still present elsewhere in the schema → FP-filter passes
        $colFile = $this->writeColumnsFile(['legacyZorp', 'new_zorp', 'other_col']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('No stale renamed-column references detected', $result['output']);
    }

    public function testNoStaleRefExitsZero(): void
    {
        $this->writeMigration(
            '100_rename_legacyZorp.sql',
            "ALTER TABLE foo CHANGE COLUMN `legacyZorp` `new_zorp` INT;\n"
        );
        // scripts/ references the NEW name, not the old one
        file_put_contents($this->tmpDir . '/ibl5/scripts/foo.php', "SELECT new_zorp FROM foo;\n");
        $colFile = $this->writeColumnsFile(['new_zorp']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('No stale renamed-column references detected', $result['output']);
    }

    public function testWordBoundaryNoSubstringMatch(): void
    {
        $this->writeMigration(
            '100_rename_legacyZorp.sql',
            "ALTER TABLE foo CHANGE COLUMN `legacyZorp` `new_zorp` INT;\n"
        );
        // Only the superstring appears — word boundary must prevent a match
        file_put_contents(
            $this->tmpDir . '/ibl5/scripts/foo.php',
            "SELECT legacyZorpExtra FROM foo;\n"
        );
        $colFile = $this->writeColumnsFile(['new_zorp']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testPrBodyBypassExitsZero(): void
    {
        $this->writeMigration(
            '100_rename_legacyZorp.sql',
            "ALTER TABLE foo CHANGE COLUMN `legacyZorp` `new_zorp` INT;\n"
        );
        file_put_contents($this->tmpDir . '/ibl5/scripts/foo.php', "SELECT legacyZorp FROM foo;\n");
        $colFile = $this->writeColumnsFile(['new_zorp']);

        $prBody = '<!-- check-column-rename: intentional backward-compat alias still in use -->';
        $result = $this->runScript(['--columns-file=' . $colFile, '--bypass-from-stdin'], $prBody);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('PASS (bypass)', $result['output']);
    }

    public function testShortPrBodyBypassExitsOne(): void
    {
        $this->writeMigration(
            '100_rename_legacyZorp.sql',
            "ALTER TABLE foo CHANGE COLUMN `legacyZorp` `new_zorp` INT;\n"
        );
        file_put_contents($this->tmpDir . '/ibl5/scripts/foo.php', "SELECT legacyZorp FROM foo;\n");
        $colFile = $this->writeColumnsFile(['new_zorp']);

        // Reason shorter than BYPASS_MIN_LENGTH=20 must NOT bypass
        $prBody = '<!-- check-column-rename: too short -->';
        $result = $this->runScript(['--columns-file=' . $colFile, '--bypass-from-stdin'], $prBody);

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('legacyZorp', $result['output']);
    }

    public function testBothColumnSourcesExitsTwo(): void
    {
        $colFile = $this->writeColumnsFile(['new_zorp']);

        $result = $this->runScript([
            '--columns-file=' . $colFile,
            '--db-name=somedb',
        ]);

        self::assertSame(2, $result['exit'], "Output: {$result['output']}");
    }

    public function testHelpFlagExitsZero(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellcmd($this->scriptPath) . ' --help 2>&1', $output, $exit);

        self::assertSame(0, $exit);
        $text = implode("\n", $output);
        self::assertStringContainsString('Usage:', $text);
        self::assertStringContainsString('ibl5/scripts/', $text);
    }

    public function testBogusArgExitsTwo(): void
    {
        $output = [];
        $exit = 0;
        exec(escapeshellcmd($this->scriptPath) . ' --bogus 2>&1', $output, $exit);

        self::assertSame(2, $exit);
        self::assertStringContainsString('unknown flag', implode("\n", $output));
    }

    public function testRenameColumnSyntaxDetected(): void
    {
        // RENAME COLUMN syntax (as used in migration 113+)
        $this->writeMigration(
            '113_rename_rating.sql',
            "ALTER TABLE ibl_plr RENAME COLUMN `r_to` TO `r_tvr`;\n"
        );
        file_put_contents($this->tmpDir . '/ibl5/scripts/ratings.php', "SELECT r_to FROM ibl_plr;\n");
        $colFile = $this->writeColumnsFile(['r_tvr']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('r_to', $result['output']);
    }

    public function testDenylistedWordInCommentNotFlagged(): void
    {
        $this->writeMigration(
            '059_rename_trade_info_from_to.sql',
            "ALTER TABLE trade_info CHANGE COLUMN `from` `origin_team` VARCHAR(50);\n"
        );
        file_put_contents(
            $this->tmpDir . '/ibl5/scripts/foo.php',
            "// import data from the archive\n"
        );
        $colFile = $this->writeColumnsFile(['origin_team']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('No stale renamed-column references detected', $result['output']);
    }

    public function testDenylistedWordAsShellLoopVarNotFlagged(): void
    {
        $this->writeMigration(
            '060_rename_log_line.sql',
            "ALTER TABLE ibl_log CHANGE COLUMN `line` `log_line` TEXT;\n"
        );
        file_put_contents(
            $this->tmpDir . '/ibl5/bin/foo',
            "while IFS= read -r line; do :; done\n"
        );
        $colFile = $this->writeColumnsFile(['log_line']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('No stale renamed-column references detected', $result['output']);
    }

    public function testDenylistedWordAsPhpVarNotFlagged(): void
    {
        $this->writeMigration(
            '061_rename_settings_key.sql',
            "ALTER TABLE ibl_settings CHANGE COLUMN `key` `setting_key` VARCHAR(100);\n"
        );
        file_put_contents(
            $this->tmpDir . '/ibl5/scripts/foo.php',
            "\$key = \$matches[1];\n"
        );
        $colFile = $this->writeColumnsFile(['setting_key']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('No stale renamed-column references detected', $result['output']);
    }

    public function testDenylistedWordInProseStringNotFlagged(): void
    {
        $this->writeMigration(
            '059_rename_trade_info_from_to.sql',
            "ALTER TABLE trade_info CHANGE COLUMN `from` `origin_team` VARCHAR(50);\n"
        );
        file_put_contents(
            $this->tmpDir . '/ibl5/scripts/foo.php',
            "echo 'This script must be run from the command line.';\n"
        );
        $colFile = $this->writeColumnsFile(['origin_team']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('No stale renamed-column references detected', $result['output']);
    }

    public function testDenylistedWordBacktickedIsFlagged(): void
    {
        $this->writeMigration(
            '059_rename_trade_info_from_to.sql',
            "ALTER TABLE trade_info CHANGE COLUMN `from` `origin_team` VARCHAR(50);\n"
        );
        file_put_contents(
            $this->tmpDir . '/ibl5/scripts/foo.php',
            "SELECT `from` FROM trade_info;\n"
        );
        $colFile = $this->writeColumnsFile(['origin_team']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('from', $result['output']);
    }

    public function testDenylistedWordQuotedSubscriptIsFlagged(): void
    {
        $this->writeMigration(
            '059_rename_trade_info_from_to.sql',
            "ALTER TABLE trade_info CHANGE COLUMN `from` `origin_team` VARCHAR(50);\n"
        );
        file_put_contents(
            $this->tmpDir . '/ibl5/scripts/foo.php',
            "echo \$row['from'];\n"
        );
        $colFile = $this->writeColumnsFile(['origin_team']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('from', $result['output']);
    }

    public function testRenameDescribedInSqlCommentIsNotParsed(): void
    {
        // A migration that *describes* a rename in prose performs none. Parsing the
        // comment harvested a phantom `old` -> `new` rename that matched English prose.
        $this->writeMigration(
            '113_rename_rating.sql',
            "-- The guarded form is `RENAME COLUMN IF EXISTS old TO new` followed by a check.\n"
            . "# Also not a rename: CHANGE COLUMN legacyZorp new_zorp\n"
            . "ALTER TABLE ibl_plr RENAME COLUMN `r_to` TO `r_tvr`;\n"
        );
        file_put_contents(
            $this->tmpDir . '/ibl5/scripts/foo.php',
            "echo \$change['old'] . ' -> ' . \$change['new'];  // old path\n"
            . "echo legacyZorp;\n"
        );
        $colFile = $this->writeColumnsFile(['r_tvr']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('No stale renamed-column references detected', $result['output']);
    }

    public function testCaseOnlyRenameIsNotFlagged(): void
    {
        // MySQL column names are case-insensitive, so `Sim` -> `sim` cannot leave a
        // stale reference behind — the live-schema filter must match case-insensitively.
        $this->writeMigration(
            '120_misc_snake_case_cleanup.sql',
            "ALTER TABLE ibl_sim_summaries CHANGE COLUMN `Sim` `sim` INT;\n"
        );
        file_put_contents(
            $this->tmpDir . '/ibl5/scripts/foo.php',
            "echo \"Sim {\$sim} recap is ready for review.\";\n"
        );
        $colFile = $this->writeColumnsFile(['sim', 'other_col']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('No stale renamed-column references detected', $result['output']);
    }

    public function testShellKeywordRenameNotFlaggedAsBareword(): void
    {
        // `do` is a real column rename in migration 113 but also a shell keyword.
        $this->writeMigration(
            '113_rename_rating.sql',
            "ALTER TABLE ibl_plr RENAME COLUMN `do` TO `r_drive_off`;\n"
        );
        file_put_contents(
            $this->tmpDir . '/ibl5/bin/foo',
            "for f in \"\$DIR\"/*; do :; done\n"
        );
        $colFile = $this->writeColumnsFile(['r_drive_off']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('No stale renamed-column references detected', $result['output']);
    }

    public function testShellKeywordRenameStillFlaggedWhenBackticked(): void
    {
        // Denylisting `do` must not blind the check to a genuine SQL reference.
        $this->writeMigration(
            '113_rename_rating.sql',
            "ALTER TABLE ibl_plr RENAME COLUMN `do` TO `r_drive_off`;\n"
        );
        file_put_contents(
            $this->tmpDir . '/ibl5/scripts/ratings.php',
            "\$sql = 'SELECT `do` FROM ibl_plr';\n"
        );
        $colFile = $this->writeColumnsFile(['r_drive_off']);

        $result = $this->runScript(['--columns-file=' . $colFile]);

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('113_rename_rating.sql', $result['output']);
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
        foreach ($args as $arg) {
            $argStr .= ' ' . escapeshellarg($arg);
        }

        $cmd = 'cd ' . escapeshellarg($this->tmpDir) . ' && ';

        if ($stdin !== null) {
            $cmd .= 'printf \'%s\' ' . escapeshellarg($stdin) . ' | ';
        }

        $cmd .= 'bash ' . escapeshellarg($this->scriptPath) . $argStr . ' 2>&1';

        exec($cmd, $output, $exit);

        return ['output' => implode("\n", $output), 'exit' => $exit];
    }

    private function writeMigration(string $filename, string $content): void
    {
        file_put_contents($this->tmpDir . '/ibl5/migrations/' . $filename, $content);
    }

    /** @param list<string> $columns */
    private function writeColumnsFile(array $columns): string
    {
        $path = $this->tmpDir . '/columns.txt';
        file_put_contents($path, implode("\n", $columns) . "\n");
        return $path;
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
