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

    public function testUntaggedInlineBypassIsRejected(): void
    {
        $sql = "-- destructive-migration: dropping unused legacy column after data migration confirmed\n";
        $sql .= "ALTER TABLE foo DROP COLUMN bar;\n";
        $this->writeMigration('109_bypassed.sql', $sql);
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('destructive-migration[<trigger>', $result['output']);
        self::assertStringContainsString('untagged', $result['output']);
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

    public function testUntaggedPrBodyBypassIsRejected(): void
    {
        $this->writeMigration('111_pr_bypass.sql', "ALTER TABLE foo DROP COLUMN bar;\n");
        $this->runInDir('git add -A');

        $prBody = '<!-- destructive-migration: removing deprecated column after successful data migration -->';
        $result = $this->runScript(['--bypass-from-stdin'], $prBody);

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('PR-body', $result['output']);
        self::assertStringContainsString('drop-column', $result['output']);
        // The marker-error must tell the author the required tagged form.
        self::assertStringContainsString('untagged bypass marker; use -- destructive-migration[<trigger>]', $result['output']);
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

    public function testChangeRenameExitsOne(): void
    {
        $this->writeMigration('114_change_rename.sql', "ALTER TABLE foo CHANGE COLUMN a b INT;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('rename-column', $result['output']);
    }

    public function testKeywordsInsideStringLiteralsNotFlagged(): void
    {
        $this->writeMigration(
            '114_string_literals.sql',
            "UPDATE foo SET note = 'change player names' WHERE id = 1;\n"
            . "UPDATE foo SET note = 'rename to new, modify x NOT NULL' WHERE id = 2;\n"
            . "UPDATE foo SET note = 'we drop index idx_a later' WHERE id = 3;\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testMultiLineAlterClausesAreFlagged(): void
    {
        $this->writeMigration(
            '114_multi_line.sql',
            "ALTER TABLE foo\n    CHANGE COLUMN `a` `b` INT,\n    DROP INDEX idx_a;\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('rename-column', $result['output']);
        self::assertStringContainsString('drop-index', $result['output']);
    }

    public function testChangeSameNameIsNotRenameColumn(): void
    {
        $this->writeMigration('115_change_same.sql', "ALTER TABLE foo CHANGE `a` `A` VARCHAR(100) DEFAULT NULL;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringNotContainsString('rename-column', $result['output']);
    }

    public function testModifyNotNullNoDefaultExitsOne(): void
    {
        $this->writeMigration('116_modify_nn.sql', "ALTER TABLE foo MODIFY COLUMN bar INT NOT NULL;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('tighten-not-null', $result['output']);
        self::assertStringContainsString('bypass marker', $result['output']);
    }

    public function testChangeSameNameNotNullNoDefaultExitsOne(): void
    {
        $this->writeMigration('117_change_nn.sql', "ALTER TABLE foo CHANGE bar bar INT NOT NULL;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('tighten-not-null', $result['output']);
        self::assertStringNotContainsString('rename-column', $result['output']);
    }

    public function testModifyNotNullWithDefaultExitsZero(): void
    {
        $this->writeMigration('118_modify_nn_default.sql', "ALTER TABLE foo MODIFY bar INT NOT NULL DEFAULT 0;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testModifyWithoutNotNullExitsZero(): void
    {
        $this->writeMigration('119_modify_null.sql', "ALTER TABLE foo MODIFY bar VARCHAR(100) NULL;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testDropIndexExitsOne(): void
    {
        $this->writeMigration('120_drop_index.sql', "ALTER TABLE foo DROP INDEX idx_bar;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('drop-index', $result['output']);
    }

    public function testDropKeyStandaloneExitsOne(): void
    {
        $this->writeMigration('121_drop_key.sql', "DROP INDEX `idx_bar` ON foo;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('drop-index', $result['output']);
    }

    public function testDropIndexWithReaddSameNameSuppressed(): void
    {
        $this->writeMigration('122_reindex.sql', "ALTER TABLE foo DROP INDEX idx_bar;\nALTER TABLE foo ADD UNIQUE INDEX idx_bar (bar);\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testDropIndexWithCreateIndexSameNameSuppressed(): void
    {
        $this->writeMigration('123_reindex_create.sql', "DROP INDEX idx_bar ON foo;\nCREATE UNIQUE INDEX idx_bar ON foo (bar, baz);\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testDropIndexWithDifferentNameAddStillExitsOne(): void
    {
        $this->writeMigration('124_reindex_other.sql', "ALTER TABLE foo DROP INDEX idx_bar;\nALTER TABLE foo ADD INDEX idx_other (bar);\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('drop-index', $result['output']);
    }

    public function testDropPrimaryAndForeignKeyNotFlagged(): void
    {
        $this->writeMigration('125_drop_pk_fk.sql', "ALTER TABLE foo DROP PRIMARY KEY;\nALTER TABLE foo DROP FOREIGN KEY fk_bar;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testRenameTableExitsOne(): void
    {
        $this->writeMigration('126_rename_table.sql', "RENAME TABLE foo TO bar;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('rename-table', $result['output']);
    }

    public function testAlterTableRenameToExitsOne(): void
    {
        $this->writeMigration('127_alter_rename_to.sql', "ALTER TABLE foo RENAME TO bar;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('rename-table', $result['output']);
    }

    public function testRenameColumnNotReportedAsRenameTable(): void
    {
        $this->writeMigration('128_rename_col_only.sql', "ALTER TABLE foo RENAME COLUMN a TO b;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('rename-column', $result['output']);
        self::assertStringNotContainsString('rename-table', $result['output']);
    }

    public function testRenameIndexNotReportedAsRenameTable(): void
    {
        $this->writeMigration('129_rename_index.sql', "ALTER TABLE foo RENAME INDEX a TO b;\nALTER TABLE foo RENAME KEY c TO d;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringNotContainsString('rename-table', $result['output']);
    }

    public function testInlineBypassCoversNewPatterns(): void
    {
        $this->writeMigration('130_bypass_new.sql', "-- destructive-migration[rename-column,tighten-not-null,drop-index,rename-table]: type change only, column was already NOT NULL\nALTER TABLE foo MODIFY bar INT NOT NULL;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('PASS (bypass)', $result['output']);
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

    // ---- Phase 3: wrapper onto the engine -------------------------------

    public function testTrueMultiLineDropColumnIsFlagged(): void
    {
        $this->writeMigration('201_multi_line_drop.sql', "ALTER TABLE t\n  DROP COLUMN c;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[drop-column]', $result['output']);
    }

    public function testUnchangedOldStatementIsNotRescanned(): void
    {
        $this->writeMigration('201_a.sql', "ALTER TABLE t DROP COLUMN c;\n");
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "old destructive migration"');
        $baseSha = $this->headSha();

        $this->writeMigration('201_a.sql', "ALTER TABLE t DROP COLUMN c;\nINSERT INTO t (c) VALUES (1);\n");
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "append an insert"');

        $result = $this->runScript(['--since=' . $baseSha]);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testAddedLineInsideOldMultiLineStatementIsScanned(): void
    {
        $this->writeMigration('202_a.sql', "ALTER TABLE t\n  ADD COLUMN a INT DEFAULT 0;\n");
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "old multi-line statement"');
        $baseSha = $this->headSha();

        $this->writeMigration('202_a.sql', "ALTER TABLE t\n  DROP COLUMN b,\n  ADD COLUMN a INT DEFAULT 0;\n");
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "insert a drop clause"');

        $result = $this->runScript(['--since=' . $baseSha]);

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[drop-column]', $result['output']);
    }

    public function testMissingPython3ExitsTwo(): void
    {
        $binDir = $this->tmpDir . '/pathbin';
        mkdir($binDir, 0755, true);
        foreach (['bash', 'git', 'cat', 'mktemp', 'grep', 'sed', 'rm', 'dirname', 'awk'] as $tool) {
            $resolved = trim((string) shell_exec('command -v ' . escapeshellarg($tool)));
            self::assertNotSame('', $resolved, "$tool must be on the host PATH");
            symlink($resolved, $binDir . '/' . $tool);
        }

        $this->writeMigration('203_clean.sql', "SELECT 1;\n");
        $this->runInDir('git add -A');

        $output = [];
        $exit = 0;
        exec(
            'cd ' . escapeshellarg($this->tmpDir)
            . ' && env PATH=' . escapeshellarg($binDir)
            . ' ' . escapeshellarg($binDir . '/bash')
            . ' ' . escapeshellarg($this->scriptPath) . ' --staged 2>&1',
            $output,
            $exit
        );

        self::assertSame(2, $exit, 'Output: ' . implode("\n", $output));
        self::assertStringContainsString('python3', implode("\n", $output));
    }

    public function testFullScanFlagsCommittedFileWithoutDiff(): void
    {
        $this->writeMigration('204_committed.sql', "ALTER TABLE t DROP COLUMN c;\n");
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "committed destructive migration"');

        $result = $this->runScript(['--full-scan']);

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[drop-column]', $result['output']);
    }

    public function testFullScanRejectsSinceCombination(): void
    {
        $result = $this->runScript(['--full-scan', '--since=HEAD']);

        self::assertSame(2, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('--full-scan takes no range and no stdin', $result['output']);
    }

    public function testSchemaFlagRejectsMissingFile(): void
    {
        $result = $this->runScript(['--schema=' . $this->tmpDir . '/does-not-exist.sql']);

        self::assertSame(2, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('schema file not found', $result['output']);
    }

    public function testSchemaFlagRejectsEmptyValue(): void
    {
        $result = $this->runScript(['--schema=']);

        self::assertSame(2, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('--schema requires a path', $result['output']);
    }

    public function testSchemaFlagIsHonouredByFullScan(): void
    {
        $narrowHit = '[narrow-type] ibl5/migrations/034_add_column_comments.sql:422';

        // Frozen schema: ibl_settings.`value` exists, so the hit is absent.
        self::assertStringNotContainsString($narrowHit, $this->runRepoFullScan()['output']);

        // Renamed column: the old type can no longer be resolved, so the hit appears.
        $frozen = file_get_contents(self::frozenSchemaPath());
        self::assertNotFalse($frozen);
        $needle = "  `value` varchar(128) NOT NULL COMMENT 'Setting value',";
        self::assertStringContainsString($needle, $frozen);
        $renamed = $this->tmpDir . '/renamed.schema';
        file_put_contents($renamed, str_replace($needle, "  `setting_value` varchar(128) NOT NULL COMMENT 'Setting value',", $frozen));

        $result = $this->execRepoFullScan($renamed);

        self::assertStringContainsString($narrowHit, $result['output']);
    }

    public function testSelfTestFlagExitsZero(): void
    {
        $result = $this->runScript(['--self-test']);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertMatchesRegularExpression('/\d+ cases, 0 failures/', $result['output']);
    }

    // ---- Phase 4: new triggers ------------------------------------------

    public function testDeleteWithoutWhereIsFlagged(): void
    {
        $this->writeMigration('210_delete.sql', "DELETE FROM t\n;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[delete-no-where]', $result['output']);
    }

    public function testDeleteWithWhereIsClean(): void
    {
        $this->writeMigration('211_delete_where.sql', "DELETE FROM t\nWHERE id = 1;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testDeleteWhereOnlyInCommentStillFlagged(): void
    {
        $this->writeMigration('212_delete_comment.sql', "DELETE FROM t; -- where id = 1\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[delete-no-where]', $result['output']);
    }

    public function testUpdateWithoutWhereIsFlagged(): void
    {
        $this->writeMigration('213_update.sql', "UPDATE t SET a = 1;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[update-no-where]', $result['output']);
    }

    public function testUpdateJoinWithoutWhereIsFlagged(): void
    {
        $this->writeMigration('214_update_join.sql', "UPDATE a JOIN b ON a.id = b.id\nSET a.x = b.x;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[update-no-where]', $result['output']);
    }

    public function testUpdateWithWhereIsClean(): void
    {
        $this->writeMigration('215_update_where.sql', "UPDATE t SET a = 1 WHERE id = 2;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testInsertOnDuplicateKeyUpdateIsClean(): void
    {
        $this->writeMigration('216_upsert.sql', "INSERT INTO t (id, a) VALUES (1, 2) ON DUPLICATE KEY UPDATE a = 2;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testNarrowTypeFromSchemaIsFlagged(): void
    {
        $this->writeSchema("CREATE TABLE `t` (\n  `c` varchar(64) NOT NULL,\n  PRIMARY KEY (`c`)\n) ENGINE=InnoDB;\n");
        $this->writeMigration('220_narrow.sql', "ALTER TABLE t MODIFY c VARCHAR(32) NULL;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[narrow-type]', $result['output']);
    }

    public function testWidenTypeFromSchemaIsClean(): void
    {
        $this->writeSchema("CREATE TABLE `t` (\n  `c` varchar(32) NOT NULL,\n  PRIMARY KEY (`c`)\n) ENGINE=InnoDB;\n");
        $this->writeMigration('221_widen.sql', "ALTER TABLE t MODIFY c VARCHAR(64) NULL;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testNarrowTypeUnresolvedFailsClosed(): void
    {
        $this->writeSchema("CREATE TABLE `t` (\n  `a` int(11) NOT NULL,\n  PRIMARY KEY (`a`)\n) ENGINE=InnoDB;\n");
        $this->writeMigration('222_unresolved.sql', "ALTER TABLE t MODIFY c VARCHAR(32) NULL;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('unresolved', $result['output']);
    }

    public function testPreparedNarrowTypeInMigration151IsFlagged(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/migrations/151_downsize_ibl_draft_team.sql');
        self::assertNotFalse($src);
        $setLine = null;
        foreach (explode("\n", $src) as $index => $line) {
            if (str_contains($line, 'SET @alter_sql')) {
                $setLine = $index + 1;
                break;
            }
        }
        self::assertNotNull($setLine, 'SET @alter_sql not found in migration 151');

        $this->writeSchema("CREATE TABLE `ibl_draft` (\n  `team` varchar(255) NOT NULL DEFAULT '',\n  PRIMARY KEY (`team`)\n) ENGINE=InnoDB;\n");
        $this->writeMigration('151_downsize_ibl_draft_team.sql', $src);
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertStringNotContainsString('Traceback', $result['output']);
        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[narrow-type]', $result['output']);
        self::assertStringContainsString("151_downsize_ibl_draft_team.sql:{$setLine} ", $result['output']);
    }

    public function testPreparedDropColumnReportedAtSetLine(): void
    {
        $this->writeMigration(
            '230_prep_drop.sql',
            "-- header\nSET @s = IF(@n = 1,\n  'ALTER TABLE foo DROP COLUMN bar',\n  'SELECT 1');\nPREPARE st FROM @s;\nEXECUTE st;\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertStringNotContainsString('Traceback', $result['output']);
        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[drop-column]', $result['output']);
        self::assertStringContainsString('230_prep_drop.sql:2 ', $result['output']);
        self::assertStringNotContainsString('230_prep_drop.sql:5 ', $result['output']);
    }

    public function testPreparedSelectOnlyBranchesAreClean(): void
    {
        $this->writeMigration(
            '231_prep_select.sql',
            "SET @s = IF(@n = 1, 'SELECT 1', 'SELECT 2');\nPREPARE st FROM @s;\nEXECUTE st;\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertStringNotContainsString('Traceback', $result['output']);
        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('No destructive migration patterns detected', $result['output']);
    }

    public function testPreparedNonDdlSetLiteralIsClean(): void
    {
        $this->writeMigration(
            '232_prep_nonddl.sql',
            "SET @msg = 'SHOW TABLES';\nPREPARE st FROM @msg;\nEXECUTE st;\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertStringNotContainsString('Traceback', $result['output']);
        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testPrepareFromUnsetVariableIsClean(): void
    {
        // An unresolved variable must produce no virtual statement. A fallback
        // to statements[-1] would scan an unrelated statement and report a false hit.
        $this->writeMigration('233_prep_unset.sql', "PREPARE st FROM @never_set;\nEXECUTE st;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertStringNotContainsString('Traceback', $result['output']);
        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testPreparedPrepareLineOnlyEditIsNotRescanned(): void
    {
        $base = "SET @s = IF(@n = 1,\n  'ALTER TABLE foo DROP COLUMN bar',\n  'SELECT 1');\nPREPARE st FROM @s;\nEXECUTE st;\n";
        $this->writeMigration('234_prep_range.sql', $base);
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "base"');

        $this->writeMigration(
            '234_prep_range.sql',
            "SET @s = IF(@n = 1,\n  'ALTER TABLE foo DROP COLUMN bar',\n  'SELECT 1');\nPREPARE st2 FROM @s;\nEXECUTE st2;\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertStringNotContainsString('Traceback', $result['output']);
        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testPreparedSetLiteralEditIsRescanned(): void
    {
        $base = "SET @s = IF(@n = 1,\n  'ALTER TABLE foo DROP COLUMN bar',\n  'SELECT 1');\nPREPARE st FROM @s;\nEXECUTE st;\n";
        $this->writeMigration('235_prep_range.sql', $base);
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "base"');

        $this->writeMigration(
            '235_prep_range.sql',
            "SET @s = IF(@n = 1,\n  'ALTER TABLE foo DROP COLUMN baz',\n  'SELECT 1');\nPREPARE st FROM @s;\nEXECUTE st;\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertStringNotContainsString('Traceback', $result['output']);
        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[drop-column]', $result['output']);
    }

    public function testPreparedDropColumnSuppressedByTaggedMarker(): void
    {
        $this->writeMigration(
            '236_prep_marker.sql',
            "-- destructive-migration[drop-column]: guarded by information_schema check before the prepared ALTER runs\n"
            . "SET @s = IF(@n = 1, 'ALTER TABLE foo DROP COLUMN bar', 'SELECT 1');\nPREPARE st FROM @s;\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertStringNotContainsString('Traceback', $result['output']);
        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('PASS (bypass)', $result['output']);
    }

    public function testNarrowTypeResolvedFromSameFileAddColumn(): void
    {
        $this->writeMigration(
            '223_same_file_narrow.sql',
            "ALTER TABLE t ADD COLUMN c VARCHAR(64) NULL;\nALTER TABLE t MODIFY c VARCHAR(32) NULL;\n"
        );
        $this->runInDir('git add -A');

        $flagged = $this->runScript();

        self::assertSame(1, $flagged['exit'], "Output: {$flagged['output']}");
        self::assertStringContainsString('[narrow-type]', $flagged['output']);

        unlink($this->tmpDir . '/ibl5/migrations/223_same_file_narrow.sql');
        $this->writeMigration(
            '224_same_file_widen.sql',
            "ALTER TABLE t ADD COLUMN c VARCHAR(32) NULL;\nALTER TABLE t MODIFY c VARCHAR(64) NULL;\n"
        );
        $this->runInDir('git add -A');

        $clean = $this->runScript();

        self::assertSame(0, $clean['exit'], "Output: {$clean['output']}");
    }

    public function testIntDisplayWidthChangeIsClean(): void
    {
        $this->writeSchema("CREATE TABLE `t` (\n  `c` int(11) NOT NULL,\n  PRIMARY KEY (`c`)\n) ENGINE=InnoDB;\n");
        $this->writeMigration('225_int_width.sql', "ALTER TABLE t MODIFY c INT NULL;\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testDropRecreateExistingFromSchemaIsFlagged(): void
    {
        $this->writeSchema("CREATE TABLE `x` (\n  `id` int(11) NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n");
        $this->writeMigration('230_recreate_schema.sql', "DROP TABLE IF EXISTS x;\nCREATE TABLE x (id INT);\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[drop-recreate-existing]', $result['output']);
        self::assertStringNotContainsString('[drop-table]', $result['output']);
    }

    public function testDropRecreateExistingFromEarlierMigrationIsFlagged(): void
    {
        $this->writeMigration('100_create_x.sql', "CREATE TABLE x (id INT);\n");
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "create x"');

        $this->writeMigration('101_recreate_x.sql', "DROP TABLE IF EXISTS x;\nCREATE TABLE x (id INT);\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[drop-recreate-existing]', $result['output']);
    }

    public function testDropRecreateNewTableStaysSuppressed(): void
    {
        $this->writeMigration('231_recreate_new.sql', "DROP TABLE IF EXISTS brand_new;\nCREATE TABLE brand_new (id INT);\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testDropRecreateIgnoresLaterNumberedMigration(): void
    {
        $this->writeMigration('102_create_x.sql', "CREATE TABLE x (id INT);\n");
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "create x later"');

        $this->writeMigration('101_recreate_x.sql', "DROP TABLE IF EXISTS x;\nCREATE TABLE x (id INT);\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    // ---- Phase 5: PHP migrations ----------------------------------------

    public function testPhpHeredocDropColumnIsFlagged(): void
    {
        $this->writeMigration(
            '240_heredoc.php',
            "<?php\n\$db->query(<<<SQL\nALTER TABLE t\n  DROP COLUMN c;\nSQL\n);\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[drop-column]', $result['output']);
        self::assertMatchesRegularExpression('/\.php:\d+/', $result['output']);
    }

    public function testPhpNowdocDeleteWithoutWhereIsFlagged(): void
    {
        $this->writeMigration(
            '241_nowdoc.php',
            "<?php\n\$db->query(<<<'SQL'\nDELETE FROM t;\nSQL\n);\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[delete-no-where]', $result['output']);
    }

    public function testPhpPreparedDeleteWithWhereIsClean(): void
    {
        $this->writeMigration(
            '242_prepared.php',
            "<?php\n\$stmt = \$db->prepare('DELETE FROM t WHERE id = ?');\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testPhpConcatenatedDeleteWithWhereIsClean(): void
    {
        $this->writeMigration(
            '243_concat.php',
            "<?php\n\$db->query('DELETE FROM `' . \$table . '` WHERE id = ' . \$id);\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testPhpBareActionWordsAreClean(): void
    {
        $this->writeMigration('244_bare_words.php', "<?php\n\$a = 'update';\n\$b = 'delete';\n");
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testPhpCommentedSqlIsClean(): void
    {
        $this->writeMigration(
            '245_commented.php',
            "<?php\n// DROP TABLE t\n/* TRUNCATE TABLE t */\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testPhpOnlyTouchedFragmentIsScanned(): void
    {
        $this->writeMigration('246_fragments.php', "<?php\n\$db->query('DELETE FROM a');\n");
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "old php migration"');
        $baseSha = $this->headSha();

        $this->writeMigration(
            '246_fragments.php',
            "<?php\n\$db->query('DELETE FROM a');\n\$db->query('DELETE FROM b WHERE id = 1');\n"
        );
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "add clean php fragment"');

        $result = $this->runScript(['--since=' . $baseSha]);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    // ---- Phase 6: tagged bypass markers ---------------------------------

    public function testTaggedInlineBypassExitsZero(): void
    {
        $sql = "-- destructive-migration[drop-column]: dropping unused legacy column after data migration confirmed\n";
        $sql .= "ALTER TABLE foo DROP COLUMN bar;\n";
        $this->writeMigration('250_tagged.sql', $sql);
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('suppressed [drop-column]', $result['output']);
        self::assertStringContainsString('PASS (bypass)', $result['output']);
    }

    public function testTaggedPrBodyBypassExitsZero(): void
    {
        $this->writeMigration('251_tagged_pr.sql', "ALTER TABLE foo DROP COLUMN bar;\n");
        $this->runInDir('git add -A');

        $prBody = '<!-- destructive-migration[drop-column]: removing deprecated column after successful data migration -->';
        $result = $this->runScript(['--bypass-from-stdin'], $prBody);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('suppressed [drop-column]', $result['output']);
    }

    public function testTaggedMarkerSuppressesOnlyNamedTrigger(): void
    {
        $sql = "-- destructive-migration[drop-column]: dropping unused legacy column after data migration confirmed\n";
        $sql .= "ALTER TABLE foo DROP COLUMN bar;\n";
        $sql .= "TRUNCATE TABLE foo;\n";
        $this->writeMigration('252_partial.sql', $sql);
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[truncate]', $result['output']);
        self::assertStringContainsString('suppressed [drop-column]', $result['output']);
    }

    public function testMultiTagMarkerSuppressesEachNamedTrigger(): void
    {
        $sql = "-- destructive-migration[drop-column,truncate]: dropping and emptying legacy structures after cutover\n";
        $sql .= "ALTER TABLE foo DROP COLUMN bar;\n";
        $sql .= "TRUNCATE TABLE foo;\n";
        $this->writeMigration('253_multi_tag.sql', $sql);
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('suppressed [drop-column]', $result['output']);
        self::assertStringContainsString('suppressed [truncate]', $result['output']);
    }

    public function testUnknownTagInMarkerIsRejected(): void
    {
        $sql = "-- destructive-migration[drop-colum]: dropping unused legacy column after data migration confirmed\n";
        $sql .= "ALTER TABLE foo DROP COLUMN bar;\n";
        $this->writeMigration('254_unknown_tag.sql', $sql);
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('unknown trigger', $result['output']);
    }

    public function testShortTaggedReasonIsIgnored(): void
    {
        $sql = "-- destructive-migration[drop-column]: too short\n";
        $sql .= "ALTER TABLE foo DROP COLUMN bar;\n";
        $this->writeMigration('255_short_tagged.sql', $sql);
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('shorter than 20', $result['output']);
        self::assertStringContainsString('[drop-column]', $result['output']);
    }

    public function testPrBodyMarkerSuppressesAcrossFiles(): void
    {
        $this->writeMigration('256_pr_a.sql', "ALTER TABLE foo DROP COLUMN bar;\n");
        $this->writeMigration('257_pr_b.sql', "ALTER TABLE baz DROP COLUMN qux;\n");
        $this->runInDir('git add -A');

        $prBody = '<!-- destructive-migration[drop-column]: removing deprecated columns after successful data migration -->';
        $result = $this->runScript(['--bypass-from-stdin'], $prBody);

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('256_pr_a.sql', $result['output']);
        self::assertStringContainsString('257_pr_b.sql', $result['output']);
    }

    public function testFullScanHonoursLegacyUntaggedMarker(): void
    {
        $parentSha = $this->headSha();

        $sql = "-- destructive-migration: dropping unused legacy column after data migration confirmed\n";
        $sql .= "ALTER TABLE foo DROP COLUMN bar;\n";
        $this->writeMigration('258_legacy.sql', $sql);
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "legacy untagged marker"');

        $full = $this->runScript(['--full-scan']);

        self::assertSame(0, $full['exit'], "Output: {$full['output']}");
        self::assertStringContainsString('suppressed', $full['output']);

        $since = $this->runScript(['--since=' . $parentSha]);

        self::assertSame(1, $since['exit'], "Output: {$since['output']}");
        self::assertStringContainsString('untagged', $since['output']);
    }

    public function testPhpCommentMarkerIsHonoured(): void
    {
        $this->writeMigration(
            '259_php_marker.php',
            "<?php\n// destructive-migration[delete-no-where]: wiping the scratch table is intentional here\n"
            . "\$db->query(<<<SQL\nDELETE FROM t;\nSQL\n);\n"
        );
        $this->runInDir('git add -A');

        $result = $this->runScript();

        self::assertSame(0, $result['exit'], "Output: {$result['output']}");
    }

    public function testMarkerOnUnchangedLineIsIgnoredInDiffMode(): void
    {
        $marker = "-- destructive-migration[drop-column]: dropping unused legacy column after data migration confirmed\n";
        $this->writeMigration('260_old_marker.sql', $marker);
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "marker only"');
        $baseSha = $this->headSha();

        $this->writeMigration('260_old_marker.sql', $marker . "ALTER TABLE t DROP COLUMN c;\n");
        $this->runInDir('git add -A');
        $this->runInDir('git commit -m "add destructive statement"');

        $result = $this->runScript(['--since=' . $baseSha]);

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertStringContainsString('[drop-column]', $result['output']);
    }

    /**
     * Runs --full-scan over the real ibl5/migrations and compares the
     * `[tag] file:line` tokens with the pinned golden. Files numbered above
     * the golden's pinned-through header are ignored, so a new migration
     * never breaks this test. The scan resolves narrow-type old types from the
     * frozen destructive-migration-full-scan.schema fixture, not the live
     * current-schema.sql, so a column rename in current-schema.sql never breaks
     * it. Regenerate the schema fixture and the golden together.
     */
    public function testFullScanMatchesGoldenFixture(): void
    {
        $golden = $this->readGolden('destructive-migration-full-scan.golden');
        $result = $this->runRepoFullScan();

        self::assertSame(1, $result['exit'], "Output: {$result['output']}");
        self::assertMatchesRegularExpression(
            '#suppressed \[[a-z-]+\] x\d+ in ibl5/migrations/143_#',
            $result['output'],
        );

        $live = [];
        foreach (explode("\n", $result['output']) as $line) {
            if (preg_match('#^  (\[[a-z-]+\]) (ibl5/migrations/([^:]+)):(\d+)(?: |$)#', $line, $m) !== 1) {
                continue;
            }
            if (preg_match('/^(\d+)/', $m[3], $n) === 1 && (int) $n[1] > $golden['pinned']) {
                continue;
            }
            $live[] = $m[1] . ' ' . $m[2] . ':' . $m[4];
        }
        sort($live, SORT_STRING);

        self::assertSame($golden['body'], $live);
    }

    /**
     * Every (tag, file) pair the master v1 engine reported must appear in the
     * full-scan golden, except pairs declared as v1 per-line false positives.
     */
    public function testV1HitsAreSubsetOfFullScanGolden(): void
    {
        $full = [];
        foreach ($this->readGolden('destructive-migration-full-scan.golden')['body'] as $line) {
            [$tag, $location] = explode(' ', $line, 2);
            $file = substr($location, 0, (int) strrpos($location, ':'));
            $full[trim($tag, '[]') . ' ' . $file] = true;
        }

        $v1 = $this->readGolden('destructive-migration-v1-hits.golden');
        self::assertNotSame([], $v1['body']);
        self::assertLessThanOrEqual(3, count($v1['falsePositives']));

        $missing = [];
        foreach ($v1['body'] as $pair) {
            if (!isset($full[$pair]) && !in_array($pair, $v1['falsePositives'], true)) {
                $missing[] = $pair;
            }
        }

        self::assertSame([], $missing, 'v1 hits missing from the full-scan golden');
    }

    /**
     * Guards against a golden regenerated from a broken engine. The plan's
     * prototype bounds (delete-no-where >= 100, update-no-where in >= 9 files)
     * did not survive re-derivation: the corpus holds 6 DELETE statements with
     * no WHERE and 12 such UPDATEs across the 6 files named below, and every
     * one of them is flagged.
     */
    public function testFullScanSanityBounds(): void
    {
        $output = $this->runRepoFullScan()['output'];

        self::assertGreaterThanOrEqual(6, preg_match_all('/^  \[delete-no-where\] /m', $output));
        self::assertSame(0, preg_match_all('/^  \[drop-recreate-existing\] /m', $output));

        preg_match_all('#^  \[update-no-where\] ibl5/migrations/(\d+)_#m', $output, $m);
        $files = array_values(array_unique($m[1]));
        foreach (['009', '038', '039', '040', '090', '106'] as $prefix) {
            self::assertContains($prefix, $files, "update-no-where expected in migration $prefix");
        }
    }

    /** @var array{output: string, exit: int}|null */
    private static ?array $repoFullScan = null;

    /**
     * @return array{output: string, exit: int}
     */
    private function runRepoFullScan(): array
    {
        if (self::$repoFullScan === null) {
            self::$repoFullScan = $this->execRepoFullScan(self::frozenSchemaPath());
        }

        return self::$repoFullScan;
    }

    private static function frozenSchemaPath(): string
    {
        return __DIR__ . '/fixtures/destructive-migration-full-scan.schema';
    }

    /**
     * @return array{output: string, exit: int}
     */
    private function execRepoFullScan(string $schemaPath): array
    {
        $output = [];
        $exit = 0;
        $root = dirname(__DIR__, 3);
        exec(
            'cd ' . escapeshellarg($root) . ' && bash ' . escapeshellarg($this->scriptPath)
                . ' --full-scan --schema=' . escapeshellarg($schemaPath) . ' 2>&1',
            $output,
            $exit,
        );

        return ['output' => implode("\n", $output), 'exit' => $exit];
    }

    /**
     * @return array{pinned: int, body: list<string>, falsePositives: list<string>}
     */
    private function readGolden(string $name): array
    {
        $lines = file(__DIR__ . '/fixtures/' . $name, FILE_IGNORE_NEW_LINES);
        self::assertNotFalse($lines, "fixture $name must exist");

        $pinned = PHP_INT_MAX;
        $body = [];
        $falsePositives = [];
        foreach ($lines as $line) {
            if (preg_match('/^# pinned-through: (\d+)/', $line, $m) === 1) {
                $pinned = (int) $m[1];
            } elseif (preg_match('/^# v1-false-positive: (\S+) (\S+)/', $line, $m) === 1) {
                $falsePositives[] = $m[1] . ' ' . $m[2];
            } elseif ($line !== '' && !str_starts_with($line, '#')) {
                $body[] = $line;
            }
        }

        return ['pinned' => $pinned, 'body' => $body, 'falsePositives' => $falsePositives];
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

    private function writeSchema(string $sql): void
    {
        $dir = $this->tmpDir . '/ibl5/docs/schema';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/current-schema.sql', $sql);
    }

    private function headSha(): string
    {
        $raw = shell_exec('cd ' . escapeshellarg($this->tmpDir) . ' && git rev-parse HEAD');

        return trim($raw !== false && $raw !== null ? $raw : '');
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
