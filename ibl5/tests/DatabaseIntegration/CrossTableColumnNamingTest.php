<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Verifies the column renames in migration 114 (Tier 2 cross-table
 * unification): `stats_to` → `stats_tvr` (live turnovers), `r_tga` / `r_tgp`
 * → `r_3ga` / `r_3gp` (3P ratings), and the team-id family unification
 * (`tid`, `teamID`, `TeamID`, `team_id`, `home/visitorTID`, `home/visitorTeamID`,
 * `owner_tid`, `teampick_tid` → `teamid` / `home_teamid` / `visitor_teamid` /
 * `owner_teamid` / `teampick_teamid`).
 *
 * These tests run forever in CI to prevent any future migration from
 * silently re-introducing a divergent column name. A column-missing
 * regression throws during the SELECT, failing the test loudly.
 */
#[Group('database')]
final class CrossTableColumnNamingTest extends DatabaseTestCase
{
    public function testLiveTablesUseStatsTvr(): void
    {
        $r1 = $this->fetchAll("SELECT stats_tvr FROM ibl_plr LIMIT 1");

        $r2 = $this->fetchAll("SELECT stats_tvr FROM ibl_plr_snapshots LIMIT 1");

        $r3 = $this->fetchAll("SELECT stats_tvr FROM ibl_olympics_plr LIMIT 1");

        // If any column is missing, fetchAll() throws and the test fails loudly.

        self::assertIsArray($r1);

        self::assertIsArray($r2);

        self::assertIsArray($r3);
    }

    public function testLiveTablesUseRThreeGaRThreeGp(): void
    {
        $r1 = $this->fetchAll("SELECT r_3ga, r_3gp FROM ibl_plr LIMIT 1");

        $r2 = $this->fetchAll("SELECT r_3ga, r_3gp FROM ibl_plr_snapshots LIMIT 1");

        $r3 = $this->fetchAll("SELECT r_3ga, r_3gp FROM ibl_olympics_plr LIMIT 1");

        $r4 = $this->fetchAll("SELECT r_3ga, r_3gp FROM ibl_draft_class LIMIT 1");

        // If any column is missing, fetchAll() throws and the test fails loudly.

        self::assertIsArray($r1);

        self::assertIsArray($r2);

        self::assertIsArray($r3);

        self::assertIsArray($r4);
    }

    public function testTeamIdFamilyIsUnified(): void
    {
        // Bare `teamid`
        $this->fetchAll("SELECT teamid FROM ibl_plr LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_olympics_plr LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_plr_snapshots LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_draft LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_fa_offers LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_cash_considerations LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_standings LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_olympics_standings LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_power LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_olympics_power LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_rcb_alltime_records LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_rcb_season_records LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_box_scores LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_olympics_box_scores LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_saved_depth_charts LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_olympics_saved_depth_charts LIMIT 1");
        $this->fetchAll("SELECT teamid FROM ibl_plb_snapshots LIMIT 1");

        // Compound `{prefix}_teamid`
        $this->fetchAll("SELECT home_teamid, visitor_teamid FROM ibl_box_scores LIMIT 1");
        $this->fetchAll("SELECT home_teamid, visitor_teamid FROM ibl_box_scores_teams LIMIT 1");
        $this->fetchAll("SELECT home_teamid, visitor_teamid FROM ibl_olympics_box_scores LIMIT 1");
        $this->fetchAll("SELECT home_teamid, visitor_teamid FROM ibl_olympics_box_scores_teams LIMIT 1");
        $rows = $this->fetchAll("SELECT owner_teamid, teampick_teamid FROM ibl_draft_picks LIMIT 1");
        // If any column is missing, fetchAll() throws and the test fails loudly.
        self::assertIsArray($rows);
    }

    public function testCareerStatsUseUnifiedNames(): void
    {
        $r1 = $this->fetchAll("SELECT car_tvr, car_3gm, car_3ga FROM ibl_plr LIMIT 1");

        $r2 = $this->fetchAll("SELECT car_tvr, car_3gm, car_3ga FROM ibl_plr_snapshots LIMIT 1");

        $r3 = $this->fetchAll("SELECT car_tvr, car_3gm, car_3ga FROM ibl_olympics_plr LIMIT 1");

        // If any column is missing, fetchAll() throws and the test fails loudly.

        self::assertIsArray($r1);

        self::assertIsArray($r2);

        self::assertIsArray($r3);
    }

    public function testOldCareerStatNamesAreGone(): void
    {
        $this->assertColumnAbsent('ibl_plr', 'car_to');
        $this->assertColumnAbsent('ibl_plr', 'car_tgm');
        $this->assertColumnAbsent('ibl_plr', 'car_tga');
        $this->assertColumnAbsent('ibl_plr_snapshots', 'car_to');
        $this->assertColumnAbsent('ibl_plr_snapshots', 'car_tgm');
        $this->assertColumnAbsent('ibl_plr_snapshots', 'car_tga');
        $this->assertColumnAbsent('ibl_olympics_plr', 'car_to');
        $this->assertColumnAbsent('ibl_olympics_plr', 'car_tgm');
        $this->assertColumnAbsent('ibl_olympics_plr', 'car_tga');
    }

    public function testPlayerToTeamJoinUsesUnifiedTeamid(): void
    {
        // The whole point of the team-id rename: same column name on both
        // sides of the JOIN, no aliasing required. If this query parses and
        // returns rows for a CI-seeded DB, the rename succeeded end-to-end.
        $rows = $this->fetchAll(
            "SELECT p.pid, p.teamid, t.team_name
             FROM ibl_plr p
             INNER JOIN ibl_team_info t ON p.teamid = t.teamid
             WHERE p.teamid > 0
             LIMIT 5"
        );

        self::assertNotEmpty(
            $rows,
            'Player→team JOIN on unified `teamid` returned no rows. CI seed may be empty, or the rename regressed.',
        );
    }

    public function testOldColumnNamesAreGone(): void
    {
        // Catch any partial migration state where the old column still exists
        // (e.g., a CHANGE COLUMN that was reverted). These should each fail
        // at the SQL layer if the column still exists.
        $this->assertColumnAbsent('ibl_plr', 'tid');
        $this->assertColumnAbsent('ibl_plr', 'stats_to');
        $this->assertColumnAbsent('ibl_plr', 'r_tga');
        $this->assertColumnAbsent('ibl_plr', 'r_tgp');
        $this->assertColumnAbsent('ibl_plr_snapshots', 'tid');
        $this->assertColumnAbsent('ibl_plr_snapshots', 'stats_to');
        $this->assertColumnAbsent('ibl_plr_snapshots', 'r_tga');
        $this->assertColumnAbsent('ibl_plr_snapshots', 'r_tgp');
        $this->assertColumnAbsent('ibl_olympics_plr', 'tid');
        $this->assertColumnAbsent('ibl_olympics_plr', 'stats_to');
        $this->assertColumnAbsent('ibl_olympics_plr', 'r_tga');
        $this->assertColumnAbsent('ibl_olympics_plr', 'r_tgp');
        $this->assertColumnAbsent('ibl_box_scores', 'teamID');
        $this->assertColumnAbsent('ibl_box_scores', 'homeTID');
        $this->assertColumnAbsent('ibl_box_scores', 'visitorTID');
        $this->assertColumnAbsent('ibl_box_scores_teams', 'homeTeamID');
        $this->assertColumnAbsent('ibl_box_scores_teams', 'visitorTeamID');
        $this->assertColumnAbsent('ibl_power', 'TeamID');
        $this->assertColumnAbsent('ibl_olympics_power', 'TeamID');
        $this->assertColumnAbsent('ibl_rcb_alltime_records', 'team_id');
        $this->assertColumnAbsent('ibl_rcb_season_records', 'team_id');
        $this->assertColumnAbsent('ibl_draft_picks', 'owner_tid');
        $this->assertColumnAbsent('ibl_draft_picks', 'teampick_tid');
        $this->assertColumnAbsent('ibl_draft_class', 'tga');
        $this->assertColumnAbsent('ibl_draft_class', 'tgp');
    }

    private function assertColumnAbsent(string $table, string $column): void
    {
        // SHOW COLUMNS LIKE is case-insensitive and treats '_' as a wildcard.
        // Use information_schema with BINARY comparison for exact, case-
        // sensitive lookup so `teamID` ≠ `teamid` and `team_id` ≠ `teamid`.
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS c
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = ?
               AND BINARY COLUMN_NAME = BINARY ?"
        );
        if ($stmt === false) {
            self::fail('Prepare failed: ' . $this->db->error);
        }
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertSame(
            0,
            (int) ($row['c'] ?? 0),
            sprintf(
                'Old column `%s`.`%s` still exists. Migration 114 expected to remove it.',
                $table,
                $column,
            ),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAll(string $sql): array
    {
        $result = $this->db->query($sql);
        if ($result === false || $result === true) {
            self::fail('Query failed: ' . $this->db->error . ' — ' . $sql);
        }
        /** @var list<array<string, mixed>> $rows */
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        return $rows;
    }
}
