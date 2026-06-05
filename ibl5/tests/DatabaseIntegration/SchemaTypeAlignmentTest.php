<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Post-impl verification for maintenance-27 (backlog 15.4 / 15.5 / 15.20 / 15.22).
 *
 * Two concerns:
 *  - Type alignment (migration 135): the boolean-intent columns are tinyint(1)
 *    and the box-score `pos` columns are ENUM, matching the canonical player
 *    tables.
 *  - Idempotency (migrations 113 / 117 / 125): the rewritten migrations use
 *    re-runnable constructs (RENAME COLUMN IF EXISTS / ADD INDEX IF NOT EXISTS)
 *    and no bare CHANGE COLUMN / bare ADD INDEX. Asserted against the migration
 *    source — re-running DDL inside the test transaction would auto-commit and
 *    break isolation, so the source is the contract (same pattern as
 *    EngineShadowSchemaTest::migrationUsesIfNotExistsForBothTables).
 */
#[Group('database')]
final class SchemaTypeAlignmentTest extends DatabaseTestCase
{
    private const MIGRATIONS_DIR = __DIR__ . '/../../migrations';

    /**
     * Boolean-intent columns that must be tinyint(1) after migration 135.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function booleanColumnProvider(): array
    {
        return [
            'career_avgs.retired'                 => ['ibl_olympics_career_avgs', 'retired'],
            'career_totals.retired'               => ['ibl_olympics_career_totals', 'retired'],
            'team_info.has_mle'                   => ['ibl_team_info', 'has_mle'],
            'team_info.has_lle'                   => ['ibl_team_info', 'has_lle'],
            'team_info.used_extension_this_chunk' => ['ibl_team_info', 'used_extension_this_chunk'],
            'team_info.used_extension_this_season' => ['ibl_team_info', 'used_extension_this_season'],
        ];
    }

    #[DataProvider('booleanColumnProvider')]
    public function testBooleanColumnsAreTinyint1(string $table, string $column): void
    {
        self::assertSame(
            'tinyint(1)',
            $this->columnType($table, $column),
            "$table.$column should be tinyint(1) after migration 135 (backlog 15.4/15.5)"
        );
    }

    /**
     * The box-score `pos` columns must be the same ENUM as ibl_plr.pos
     * (backlog 15.20). Both halves of the Olympics parity pair move together.
     *
     * @return array<string, array{0: string}>
     */
    public static function posTableProvider(): array
    {
        return [
            'ibl_box_scores'          => ['ibl_box_scores'],
            'ibl_olympics_box_scores' => ['ibl_olympics_box_scores'],
        ];
    }

    #[DataProvider('posTableProvider')]
    public function testBoxScorePosIsEnum(string $table): void
    {
        self::assertSame(
            "enum('PG','SG','SF','PF','C','G','F','GF','')",
            $this->columnType($table, 'pos'),
            "$table.pos should be the canonical position ENUM after migration 135 (backlog 15.20)"
        );
    }

    /**
     * OlympicsSchemaParityTest already guards column-NAME parity; this asserts
     * the dual ENUM ALTER kept both box-score tables' `pos` types in lockstep.
     */
    #[Test]
    public function bothBoxScorePosColumnsShareTheSameType(): void
    {
        self::assertSame(
            $this->columnType('ibl_box_scores', 'pos'),
            $this->columnType('ibl_olympics_box_scores', 'pos'),
            'pos type must match across the box_scores parity pair'
        );
    }

    /**
     * Migrations 113 and 117 must be re-runnable: pure renames expressed as
     * RENAME COLUMN IF EXISTS, with no bare CHANGE COLUMN (which errors on the
     * second apply because the old column no longer exists).
     *
     * @return array<string, array{0: string}>
     */
    public static function renameMigrationProvider(): array
    {
        return [
            'migration_113' => ['113_rename_reserved_word_rating_columns.sql'],
            'migration_117' => ['117_snake_case_team_info_columns.sql'],
        ];
    }

    #[DataProvider('renameMigrationProvider')]
    public function testRenameMigrationsAreIdempotent(string $file): void
    {
        $ddl = $this->migrationDdl($file);

        self::assertStringContainsStringIgnoringCase(
            'RENAME COLUMN IF EXISTS',
            $ddl,
            "$file must use RENAME COLUMN IF EXISTS for re-runnability"
        );
        self::assertStringNotContainsStringIgnoringCase(
            'CHANGE COLUMN',
            $ddl,
            "$file must not use bare CHANGE COLUMN (fails on re-apply)"
        );
        // Every MODIFY that restores a post-rename type must be guarded with
        // IF EXISTS, so a partial re-run cannot error on a missing column.
        self::assertSame(
            $this->countCi($ddl, 'MODIFY COLUMN'),
            $this->countCi($ddl, 'MODIFY COLUMN IF EXISTS'),
            "$file MODIFY clauses must all be guarded with IF EXISTS"
        );
    }

    #[Test]
    public function indexMigrationIsIdempotent(): void
    {
        $ddl = $this->migrationDdl('125_add_franchise_seasons_composite_index.sql');

        self::assertStringContainsStringIgnoringCase(
            'ADD INDEX IF NOT EXISTS',
            $ddl,
            '125 must use ADD INDEX IF NOT EXISTS for re-runnability'
        );
    }

    #[Test]
    public function typeAlignmentMigrationIsIdempotentAndFailsLoud(): void
    {
        $ddl = $this->migrationDdl('135_align_boolean_and_position_column_types.sql');

        // MODIFY COLUMN is naturally re-runnable; CHANGE COLUMN would not be.
        self::assertStringContainsStringIgnoringCase('MODIFY COLUMN', $ddl);
        self::assertStringNotContainsStringIgnoringCase('CHANGE COLUMN', $ddl);
        // STRICT_ALL_TABLES makes the pos ENUM cast error on dirty data instead
        // of silently truncating an out-of-range position to ''.
        self::assertStringContainsString('STRICT_ALL_TABLES', $ddl);
    }

    private function countCi(string $haystack, string $needle): int
    {
        return substr_count(strtoupper($haystack), strtoupper($needle));
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

    /**
     * Migration source with `--` comment lines stripped, so DDL-pattern
     * assertions are not fooled by prose that mentions the old constructs
     * (e.g. a comment explaining the former `CHANGE COLUMN`).
     */
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
