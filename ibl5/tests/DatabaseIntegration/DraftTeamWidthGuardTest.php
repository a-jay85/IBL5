<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Proves the migration-151 apply-time guard for ibl_draft.team varchar(255)→varchar(35).
 *
 * Gate matrix (maintenance-41c / backlog 15.16):
 *  (a)-fail: guard expression raises 1242 on a 36-char row under prod's empty sql_mode
 *  (a)-pass: same guard returns 0 cleanly on ≤35-char (and empty-table) data
 *  (b):      migration source contains the mode-independent UNION idiom BEFORE the ALTER,
 *            no warn-only idiom (STRICT_ALL_TABLES / CAST-as-guard / division-by-zero),
 *            and is information_schema/PREPARE-gated for idempotency
 *  (c):      after bootstrap applies all migrations, columnType('ibl_draft','team') === 'varchar(35)'
 */
#[Group('database')]
final class DraftTeamWidthGuardTest extends DatabaseTestCase
{
    private const MIGRATIONS_DIR = __DIR__ . '/../../migrations';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->query("SET SESSION sql_mode = ''");
    }

    public function testGuardErrorsOnOverlengthRowUnderNonStrictMode(): void
    {
        $this->db->query('CREATE TEMPORARY TABLE _guard_probe (team varchar(255))');

        $overlength = str_repeat('X', 36);
        $stmt = $this->db->prepare('INSERT INTO _guard_probe (team) VALUES (?)');
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $overlength);
        $stmt->execute();
        $stmt->close();

        try {
            $this->db->query(
                'SELECT IF((SELECT MAX(CHAR_LENGTH(team)) FROM _guard_probe) > 35, (SELECT 1 UNION SELECT 2), 0)'
            );
            self::fail('Guard expression should have raised error 1242 on a 36-char row');
        } catch (\mysqli_sql_exception $e) {
            self::assertSame(1242, $e->getCode());
        }
    }

    public function testGuardPassesCleanlyOnInBoundData(): void
    {
        $this->db->query('CREATE TEMPORARY TABLE _guard_probe_clean (team varchar(255))');

        $stmt = $this->db->prepare('INSERT INTO _guard_probe_clean (team) VALUES (?)');
        self::assertNotFalse($stmt);
        $inbound = 'Metros';
        $stmt->bind_param('s', $inbound);
        $stmt->execute();
        $stmt->close();

        $result = $this->db->query(
            'SELECT IF((SELECT MAX(CHAR_LENGTH(team)) FROM _guard_probe_clean) > 35, (SELECT 1 UNION SELECT 2), 0) AS g'
        );
        self::assertNotFalse($result);
        /** @var array{g: int}|null $row */
        $row = $result->fetch_assoc();
        self::assertIsArray($row);
        self::assertSame(0, (int) $row['g']);
    }

    public function testGuardPassesOnEmptyTable(): void
    {
        $this->db->query('CREATE TEMPORARY TABLE _guard_probe_empty (team varchar(255))');

        $result = $this->db->query(
            'SELECT IF((SELECT MAX(CHAR_LENGTH(team)) FROM _guard_probe_empty) > 35, (SELECT 1 UNION SELECT 2), 0) AS g'
        );
        self::assertNotFalse($result);
        /** @var array{g: int}|null $row */
        $row = $result->fetch_assoc();
        self::assertIsArray($row);
        self::assertSame(0, (int) $row['g'], 'NULL MAX on empty table must take the false branch (no false-abort)');
    }

    public function testMigrationSourceContractGuardBeforeAlter(): void
    {
        $filename = '151_downsize_ibl_draft_team.sql';
        $source   = $this->migrationSource($filename);
        $ddl      = $this->migrationDdl($filename);

        // (b)-1: uses the mode-independent UNION idiom
        self::assertStringContainsString(
            '(SELECT 1 UNION SELECT 2)',
            $source,
            'Migration must use the mode-independent UNION-subquery idiom'
        );

        // (b)-2: no warn-only idiom substituted
        self::assertStringNotContainsStringIgnoringCase(
            'STRICT_ALL_TABLES',
            $ddl,
            'Migration must NOT use STRICT_ALL_TABLES (not abort-on-non-strict prod)'
        );

        // (b)-3: guard appears BEFORE the ALTER MODIFY
        $unionPos  = strpos($ddl, 'UNION SELECT');
        $alterPos  = stripos($ddl, 'MODIFY COLUMN `team`');
        self::assertNotFalse($unionPos, 'UNION SELECT not found in migration DDL');
        self::assertNotFalse($alterPos, 'MODIFY COLUMN `team` not found in migration DDL');
        self::assertLessThan(
            $alterPos,
            $unionPos,
            'Guard (UNION SELECT) must appear BEFORE the ALTER MODIFY COLUMN'
        );

        // (b)-4: targets varchar(35)
        self::assertStringContainsStringIgnoringCase(
            'varchar(35)',
            $ddl,
            'Migration ALTER must target varchar(35)'
        );

        // (b)-5: information_schema-gated for idempotency
        self::assertStringContainsStringIgnoringCase(
            'information_schema',
            $ddl,
            'Migration must use information_schema gate for idempotency'
        );
        self::assertStringContainsStringIgnoringCase(
            'PREPARE',
            $ddl,
            'Migration must use PREPARE/EXECUTE/DEALLOCATE for idempotent DDL'
        );
    }

    public function testDraftTeamColumnIsVarchar35(): void
    {
        self::assertSame(
            'varchar(35)',
            $this->columnType('ibl_draft', 'team'),
            'ibl_draft.team must be varchar(35) after migration 151 (backlog 15.16)'
        );
    }

    private function columnType(string $table, string $column): string
    {
        $stmt = $this->db->prepare(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        /** @var array{COLUMN_TYPE: string}|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertIsArray($row, "Column $table.$column not found");

        return $row['COLUMN_TYPE'];
    }

    private function migrationSource(string $file): string
    {
        $path = self::MIGRATIONS_DIR . '/' . $file;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function migrationDdl(string $file): string
    {
        $split = preg_split('/\R/', $this->migrationSource($file));
        $lines = $split === false ? [] : $split;
        $ddl = array_filter(
            $lines,
            static fn (string $line): bool => !str_starts_with(ltrim($line), '--')
        );

        return implode("\n", $ddl);
    }
}
