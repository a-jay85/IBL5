<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Characterization test for migration 134 (engine shadow box-score tables).
 *
 * Grounds the EngineShadowLoader's column mapping: asserts both shadow tables
 * exist with exactly the raw stat columns the engine emits and NO generated
 * columns (calc_*, game_type, season_year) and NO foreign keys — the trimmed,
 * droppable shape PR8 depends on.
 */
#[Group('database')]
final class EngineShadowSchemaTest extends DatabaseTestCase
{
    private const PLAYER_TABLE = 'ibl_box_scores_engine_shadow';
    private const TEAM_TABLE = 'ibl_box_scores_engine_shadow_teams';

    /** Raw stat + identity columns the player shadow table must carry. */
    private const PLAYER_COLUMNS = [
        'game_date', 'visitor_teamid', 'home_teamid', 'game_of_that_day',
        'pid', 'teamid', 'pos',
        'game_min', 'game_2gm', 'game_2ga', 'game_ftm', 'game_fta',
        'game_3gm', 'game_3ga', 'game_orb', 'game_drb', 'game_ast',
        'game_stl', 'game_tov', 'game_blk', 'game_pf',
        'sim_seed', 'sim_game_type',
    ];

    /** Raw stat + identity columns the team shadow table must carry. */
    private const TEAM_COLUMNS = [
        'game_date', 'visitor_teamid', 'home_teamid', 'game_of_that_day', 'teamid',
        'game_2gm', 'game_2ga', 'game_ftm', 'game_fta', 'game_3gm', 'game_3ga',
        'game_orb', 'game_drb', 'game_ast', 'game_stl', 'game_tov', 'game_blk', 'game_pf',
        'visitor_q1_points', 'visitor_q2_points', 'visitor_q3_points', 'visitor_q4_points', 'visitor_ot_points',
        'home_q1_points', 'home_q2_points', 'home_q3_points', 'home_q4_points', 'home_ot_points',
        'sim_seed', 'sim_game_type',
    ];

    /** Generated columns that must NOT leak into the shadow tables. */
    private const FORBIDDEN_COLUMNS = ['calc_points', 'calc_rebounds', 'calc_fg_made', 'game_type', 'season_year', 'name', 'uuid'];

    #[Test]
    public function playerShadowTableHasExpectedRawColumns(): void
    {
        $columns = $this->columnsOf(self::PLAYER_TABLE);
        self::assertNotSame([], $columns, self::PLAYER_TABLE . ' does not exist after migrations');

        foreach (self::PLAYER_COLUMNS as $expected) {
            self::assertArrayHasKey($expected, $columns, "Missing column $expected on " . self::PLAYER_TABLE);
        }
        foreach (self::FORBIDDEN_COLUMNS as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $columns, "Forbidden column $forbidden present on " . self::PLAYER_TABLE);
        }
    }

    #[Test]
    public function teamShadowTableHasExpectedRawColumns(): void
    {
        $columns = $this->columnsOf(self::TEAM_TABLE);
        self::assertNotSame([], $columns, self::TEAM_TABLE . ' does not exist after migrations');

        foreach (self::TEAM_COLUMNS as $expected) {
            self::assertArrayHasKey($expected, $columns, "Missing column $expected on " . self::TEAM_TABLE);
        }
        foreach (self::FORBIDDEN_COLUMNS as $forbidden) {
            self::assertArrayNotHasKey($forbidden, $columns, "Forbidden column $forbidden present on " . self::TEAM_TABLE);
        }
    }

    #[Test]
    public function shadowTablesHaveNoGeneratedColumns(): void
    {
        foreach ([self::PLAYER_TABLE, self::TEAM_TABLE] as $table) {
            foreach ($this->columnsOf($table) as $name => $extra) {
                self::assertStringNotContainsStringIgnoringCase(
                    'GENERATED',
                    $extra,
                    "Column $name on $table is a generated column; shadow tables must store raw values only"
                );
            }
        }
    }

    #[Test]
    public function shadowTablesHaveNoForeignKeys(): void
    {
        foreach ([self::PLAYER_TABLE, self::TEAM_TABLE] as $table) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS cnt FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL"
            );
            self::assertNotFalse($stmt);
            $stmt->bind_param('s', $table);
            $stmt->execute();
            /** @var array{cnt: int}|null $row */
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            self::assertSame(0, (int) ($row['cnt'] ?? -1), "$table must have no foreign keys");
        }
    }

    /**
     * Migration 134 must be idempotent — re-running it is a no-op because both
     * CREATE statements use IF NOT EXISTS. Asserted against the migration source
     * so the test never issues DDL inside the test transaction.
     */
    #[Test]
    public function migrationUsesIfNotExistsForBothTables(): void
    {
        $sql = (string) file_get_contents(__DIR__ . '/../../migrations/134_create_engine_shadow_box_scores.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `' . self::PLAYER_TABLE . '`', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS `' . self::TEAM_TABLE . '`', $sql);
        self::assertStringNotContainsString('DROP TABLE', $sql);
    }

    /**
     * @return array<string, string> column name => EXTRA (e.g. 'STORED GENERATED'), empty array if table absent
     */
    private function columnsOf(string $table): array
    {
        $stmt = $this->db->prepare(
            "SELECT COLUMN_NAME, EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();

        $columns = [];
        while (true) {
            /** @var array{COLUMN_NAME: string, EXTRA: string}|null $row */
            $row = $result->fetch_assoc();
            if (!is_array($row)) {
                break;
            }
            $columns[$row['COLUMN_NAME']] = $row['EXTRA'];
        }
        $stmt->close();

        return $columns;
    }
}
