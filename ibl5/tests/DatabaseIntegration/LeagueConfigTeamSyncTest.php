<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Migration 140 (maintenance-28 — backlog 15.19): ibl_league_config gains a
 * teamid surrogate FK -> ibl_team_info.teamid, and trg_team_identity_sync is
 * extended so a team rename rewrites ibl_league_config.team_name for that teamid.
 */
#[Group('database')]
class LeagueConfigTeamSyncTest extends DatabaseTestCase
{
    public function testTeamidColumnAndForeignKey(): void
    {
        $col = $this->db->query(
            "SELECT DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ibl_league_config' AND COLUMN_NAME = 'teamid'"
        )->fetch_assoc();
        self::assertNotNull($col, 'ibl_league_config.teamid not found');
        self::assertSame('int', $col['DATA_TYPE']);
        self::assertSame('YES', $col['IS_NULLABLE']);

        $fk = $this->db->query(
            "SELECT rc.DELETE_RULE, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
             JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
               ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
             WHERE rc.CONSTRAINT_SCHEMA = DATABASE() AND rc.TABLE_NAME = 'ibl_league_config'
               AND rc.CONSTRAINT_NAME = 'fk_league_config_teamid'"
        )->fetch_assoc();
        self::assertNotNull($fk, 'fk_league_config_teamid missing');
        self::assertSame('ibl_team_info', $fk['REFERENCED_TABLE_NAME']);
        self::assertSame('teamid', $fk['REFERENCED_COLUMN_NAME']);
        self::assertSame('SET NULL', $fk['DELETE_RULE']);
    }

    public function testTeamRenamePropagatesToLeagueConfig(): void
    {
        // A real, non-Free-Agents franchise from the seed.
        $team = $this->db->query(
            'SELECT teamid, team_name FROM ibl_team_info WHERE teamid > 0 ORDER BY teamid LIMIT 1'
        )->fetch_assoc();
        self::assertNotNull($team, 'seed has no real teams');
        $teamid = (int) $team['teamid'];
        $oldName = (string) $team['team_name'];
        $newName = 'ZZRenamed';
        self::assertNotSame($newName, $oldName);

        // A league-config row pinned to that team (the denormalized name starts matching).
        $this->insertRow('ibl_league_config', [
            'season_ending_year' => 2999,
            'team_slot' => 99,
            'team_name' => $oldName,
            'teamid' => $teamid,
            'conference' => 'Eastern',
            'division' => 'Atlantic',
            'playoff_qualifiers_per_conf' => 8,
            'playoff_round1_format' => 'bo7',
            'playoff_round2_format' => 'bo7',
            'playoff_round3_format' => 'bo7',
            'playoff_round4_format' => 'bo7',
            'team_count' => 30,
        ]);

        // Rename the team -> trigger should rewrite the denormalized league_config name.
        $stmt = $this->db->prepare('UPDATE ibl_team_info SET team_name = ? WHERE teamid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('si', $newName, $teamid);
        $stmt->execute();
        $stmt->close();

        $synced = $this->db->query(
            "SELECT team_name FROM ibl_league_config WHERE teamid = $teamid AND season_ending_year = 2999"
        )->fetch_assoc();
        self::assertNotNull($synced);
        self::assertSame($newName, $synced['team_name'], 'trigger did not sync ibl_league_config.team_name');
    }
}
