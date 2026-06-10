<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Migration 143 (maintenance-42 — backlog 15.7): ibl_settings.name is renamed to
 * the non-reserved setting_key, the composite PK is rebuilt as (setting_key, league),
 * and the three triggers that read the column (trg_team_identity_sync,
 * trg_gm_tenure_track, trg_season_rollover) are recreated against setting_key.
 *
 * These tests prove the rename round-trips, both ibl_settings-reading triggers still
 * fire post-rename, the old column is truly gone (not aliased), and the PK shape is
 * exactly (setting_key, league).
 */
#[Group('database')]
class SettingsRenameTriggerTest extends DatabaseTestCase
{
    public function testSettingRoundTripsThroughSettingKey(): void
    {
        $this->db->query(
            "REPLACE INTO ibl_settings (setting_key, value, league)"
            . " VALUES ('DB_IntTest_RenameProbe', 'probe_value', 'ibl')"
        );

        $row = $this->db->query(
            "SELECT value FROM ibl_settings WHERE setting_key = 'DB_IntTest_RenameProbe' AND league = 'ibl'"
        )->fetch_assoc();

        self::assertNotNull($row, 'inserted setting not readable via setting_key');
        self::assertSame('probe_value', $row['value']);
    }

    public function testSeasonRolloverTriggerFiresPostRename(): void
    {
        // Updating 'Current Season Ending Year' (seed value 2026) must bulk-insert one
        // ibl_franchise_seasons row per team for the new season via trg_season_rollover.
        $newEndingYear = 2099;
        $newBeginningYear = $newEndingYear - 1;

        self::assertSame(
            0,
            $this->countFranchiseSeasonRows($newEndingYear),
            'precondition: no franchise_seasons rows for the probe year yet'
        );

        $stmt = $this->db->prepare(
            "UPDATE ibl_settings SET value = ? WHERE setting_key = 'Current Season Ending Year' AND league = 'ibl'"
        );
        self::assertNotFalse($stmt);
        $newValue = (string) $newEndingYear;
        $stmt->bind_param('s', $newValue);
        $stmt->execute();
        $stmt->close();

        $teamCount = (int) $this->db->query(
            'SELECT COUNT(*) AS cnt FROM ibl_team_info'
        )->fetch_assoc()['cnt'];

        $inserted = $this->countFranchiseSeasonRows($newEndingYear);
        self::assertSame(
            $teamCount,
            $inserted,
            'trg_season_rollover did not insert one franchise_seasons row per team after the rename'
        );

        $sample = $this->db->query(
            "SELECT season_year FROM ibl_franchise_seasons WHERE season_ending_year = $newEndingYear LIMIT 1"
        )->fetch_assoc();
        self::assertNotNull($sample);
        self::assertSame($newBeginningYear, (int) $sample['season_year']);
    }

    public function testTeamIdentitySyncTriggerFiresPostRename(): void
    {
        // Renaming a team reads 'Current Season Ending Year' (seed 2026) via the
        // recreated trg_team_identity_sync and upserts an ibl_franchise_seasons row.
        $team = $this->db->query(
            'SELECT teamid FROM ibl_team_info WHERE teamid > 0 ORDER BY teamid LIMIT 1'
        )->fetch_assoc();
        self::assertNotNull($team, 'seed has no real teams');
        $teamid = (int) $team['teamid'];

        $newName = 'ZZRenameProbe';
        $stmt = $this->db->prepare('UPDATE ibl_team_info SET team_name = ? WHERE teamid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('si', $newName, $teamid);
        $stmt->execute();
        $stmt->close();

        $synced = $this->db->query(
            "SELECT team_name FROM ibl_franchise_seasons"
            . " WHERE franchise_id = $teamid AND season_ending_year = 2026"
        )->fetch_assoc();
        self::assertNotNull($synced, 'trg_team_identity_sync did not upsert ibl_franchise_seasons');
        self::assertSame($newName, $synced['team_name']);
    }

    public function testOldNameColumnIsGoneNotAliased(): void
    {
        // The column was renamed, not aliased — selecting the old name must error.
        $this->expectException(\mysqli_sql_exception::class);
        $this->expectExceptionMessageMatches('/Unknown column .?name.?/');
        $this->db->query('SELECT name FROM ibl_settings LIMIT 1');
    }

    public function testPrimaryKeyIsSettingKeyLeague(): void
    {
        $cols = [];
        $res = $this->db->query(
            "SELECT COLUMN_NAME, SEQ_IN_INDEX FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ibl_settings' AND INDEX_NAME = 'PRIMARY'
             ORDER BY SEQ_IN_INDEX"
        );
        while ($row = $res->fetch_assoc()) {
            $cols[(int) $row['SEQ_IN_INDEX']] = $row['COLUMN_NAME'];
        }

        self::assertSame(['setting_key', 'league'], array_values($cols));
    }

    private function countFranchiseSeasonRows(int $seasonEndingYear): int
    {
        return (int) $this->db->query(
            "SELECT COUNT(*) AS cnt FROM ibl_franchise_seasons WHERE season_ending_year = $seasonEndingYear"
        )->fetch_assoc()['cnt'];
    }
}
