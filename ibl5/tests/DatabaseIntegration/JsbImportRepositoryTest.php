<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use JsbParser\JsbImportRepository;

/**
 * Tests JsbImportRepository against real MariaDB — upserts into ibl_hist,
 * ibl_jsb_transactions, ibl_jsb_history, ibl_jsb_allstar_rosters,
 * ibl_jsb_allstar_scores, ibl_rcb_alltime_records, ibl_rcb_season_records.
 *
 * Also covers resolveTeamId() (pure logic) and getPlayerName() (DB read).
 */
class JsbImportRepositoryTest extends DatabaseTestCase
{
    private JsbImportRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new JsbImportRepository($this->db);
    }

    // ── upsertHistRecord ────────────────────────────────────────

    public function testUpsertHistRecordInsertsNewRow(): void
    {
        $this->insertTestPlayer(200050001, 'JSB Hist Player');

        $affected = $this->repo->upsertHistRecord([
            'pid' => 200050001,
            'name' => 'JSB Hist Player',
            'year' => 2099,
            'team' => 'Metros',
            'teamid' => 1,
            'games' => 82,
            'minutes' => 2800,
            'fgm' => 400,
            'fga' => 850,
            'ftm' => 200,
            'fta' => 240,
            'tgm' => 100,
            'tga' => 280,
            'orb' => 50,
            'reb' => 350,
            'ast' => 300,
            'stl' => 80,
            'blk' => 30,
            'tvr' => 120,
            'pf' => 150,
            'pts' => 1100,
        ]);

        self::assertGreaterThanOrEqual(1, $affected);

        $stmt = $this->db->prepare('SELECT pts, games, team FROM ibl_hist WHERE pid = ? AND year = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('ii', $pid, $year);
        $pid = 200050001;
        $year = 2099;
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(1100, $row['pts']);
        self::assertSame(82, $row['games']);
        self::assertSame('Metros', $row['team']);
    }

    public function testUpsertHistRecordUpdatesOnDuplicateKey(): void
    {
        $this->insertTestPlayer(200050002, 'JSB Hist Upd');

        // First insert
        $this->repo->upsertHistRecord([
            'pid' => 200050002,
            'name' => 'JSB Hist Upd',
            'year' => 2099,
            'team' => 'Metros',
            'teamid' => 1,
            'games' => 50,
            'minutes' => 1600,
            'fgm' => 200,
            'fga' => 500,
            'ftm' => 100,
            'fta' => 120,
            'tgm' => 50,
            'tga' => 150,
            'orb' => 30,
            'reb' => 200,
            'ast' => 150,
            'stl' => 40,
            'blk' => 15,
            'tvr' => 60,
            'pf' => 80,
            'pts' => 550,
        ]);

        // Update same pid+name+year with different pts
        $affected = $this->repo->upsertHistRecord([
            'pid' => 200050002,
            'name' => 'JSB Hist Upd',
            'year' => 2099,
            'team' => 'Stars',
            'teamid' => 2,
            'games' => 82,
            'minutes' => 2800,
            'fgm' => 400,
            'fga' => 850,
            'ftm' => 200,
            'fta' => 240,
            'tgm' => 100,
            'tga' => 280,
            'orb' => 50,
            'reb' => 350,
            'ast' => 300,
            'stl' => 80,
            'blk' => 30,
            'tvr' => 120,
            'pf' => 150,
            'pts' => 1100,
        ]);

        self::assertGreaterThanOrEqual(1, $affected);

        $stmt = $this->db->prepare('SELECT pts, team FROM ibl_hist WHERE pid = ? AND year = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('ii', $pid, $year);
        $pid = 200050002;
        $year = 2099;
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(1100, $row['pts']);
        self::assertSame('Stars', $row['team']);
    }

    // ── upsertTransaction ───────────────────────────────────────

    public function testUpsertTransactionInsertsNewRow(): void
    {
        $affected = $this->repo->upsertTransaction([
            'season_year' => 2099,
            'transaction_month' => 1,
            'transaction_day' => 15,
            'transaction_type' => 2,
            'pid' => 0,
            'player_name' => '',
            'from_teamid' => 1,
            'to_teamid' => 2,
            'injury_games_missed' => 0,
            'injury_description' => '',
            'trade_group_id' => 1,
            'is_draft_pick' => 0,
            'draft_pick_year' => 0,
            'source_file' => 'test.trn',
        ]);

        self::assertGreaterThanOrEqual(1, $affected);
    }

    public function testUpsertTransactionUpdatesOnDuplicateKey(): void
    {
        // First insert
        $this->repo->upsertTransaction([
            'season_year' => 2099,
            'transaction_month' => 3,
            'transaction_day' => 10,
            'transaction_type' => 1,
            'pid' => 200050001,
            'player_name' => 'Trade Player',
            'from_teamid' => 1,
            'to_teamid' => 2,
            'injury_games_missed' => 0,
            'injury_description' => '',
            'trade_group_id' => 5,
            'is_draft_pick' => 0,
            'draft_pick_year' => 0,
            'source_file' => 'original.trn',
        ]);

        // Same UNIQUE KEY, different source_file
        $affected = $this->repo->upsertTransaction([
            'season_year' => 2099,
            'transaction_month' => 3,
            'transaction_day' => 10,
            'transaction_type' => 1,
            'pid' => 200050001,
            'player_name' => 'Trade Player',
            'from_teamid' => 1,
            'to_teamid' => 2,
            'injury_games_missed' => 0,
            'injury_description' => '',
            'trade_group_id' => 10,
            'is_draft_pick' => 0,
            'draft_pick_year' => 0,
            'source_file' => 'updated.trn',
        ]);

        self::assertGreaterThanOrEqual(1, $affected);

        $stmt = $this->db->prepare(
            'SELECT source_file, trade_group_id FROM ibl_jsb_transactions
             WHERE season_year = ? AND transaction_month = ? AND transaction_day = ? AND transaction_type = ? AND pid = ? AND from_teamid = ? AND to_teamid = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('iiiiiii', $sy, $tm, $td, $tt, $pid, $from, $to);
        $sy = 2099;
        $tm = 3;
        $td = 10;
        $tt = 1;
        $pid = 200050001;
        $from = 1;
        $to = 2;
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame('updated.trn', $row['source_file']);
        self::assertSame(10, $row['trade_group_id']);
    }

    // ── upsertHistoryRecord ─────────────────────────────────────

    public function testUpsertHistoryRecordInsertsNewRow(): void
    {
        $affected = $this->repo->upsertHistoryRecord([
            'season_year' => 2099,
            'team_name' => 'Metros',
            'teamid' => 1,
            'wins' => 50,
            'losses' => 32,
            'made_playoffs' => 1,
            'playoff_result' => 'Won Championship',
            'playoff_round_reached' => 'championship',
            'won_championship' => 1,
            'source_file' => 'test.his',
        ]);

        self::assertGreaterThanOrEqual(1, $affected);
    }

    public function testUpsertHistoryRecordUpdatesOnDuplicateKey(): void
    {
        // First insert
        $this->repo->upsertHistoryRecord([
            'season_year' => 2099,
            'team_name' => 'Stars',
            'teamid' => 2,
            'wins' => 30,
            'losses' => 52,
            'made_playoffs' => 0,
            'playoff_result' => '',
            'playoff_round_reached' => '',
            'won_championship' => 0,
            'source_file' => 'original.his',
        ]);

        // Same season_year+team_name, update wins
        $this->repo->upsertHistoryRecord([
            'season_year' => 2099,
            'team_name' => 'Stars',
            'teamid' => 2,
            'wins' => 45,
            'losses' => 37,
            'made_playoffs' => 1,
            'playoff_result' => 'Lost First Round',
            'playoff_round_reached' => 'first_round',
            'won_championship' => 0,
            'source_file' => 'updated.his',
        ]);

        $stmt = $this->db->prepare('SELECT wins, source_file FROM ibl_jsb_history WHERE season_year = ? AND team_name = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('is', $sy, $tn);
        $sy = 2099;
        $tn = 'Stars';
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(45, $row['wins']);
        self::assertSame('updated.his', $row['source_file']);
    }

    // ── upsertAllStarRoster ─────────────────────────────────────

    public function testUpsertAllStarRosterInsertsNewRow(): void
    {
        $affected = $this->repo->upsertAllStarRoster([
            'season_year' => 2099,
            'event_type' => 'allstar_1',
            'roster_slot' => 1,
            'pid' => 200050001,
            'player_name' => 'AllStar Player',
        ]);

        self::assertGreaterThanOrEqual(1, $affected);
    }

    // ── upsertAllStarScore ──────────────────────────────────────

    public function testUpsertAllStarScoreInsertsNewRow(): void
    {
        $affected = $this->repo->upsertAllStarScore([
            'season_year' => 2099,
            'contest_type' => 'three_point',
            'round' => 1,
            'participant_slot' => 1,
            'pid' => 200050001,
            'score' => 22,
        ]);

        self::assertGreaterThanOrEqual(1, $affected);
    }

    // ── upsertRcbAlltimeRecord ──────────────────────────────────

    public function testUpsertRcbAlltimeRecordInsertsNewRow(): void
    {
        $affected = $this->repo->upsertRcbAlltimeRecord([
            'scope' => 'league',
            'team_id' => 0,
            'record_type' => 'single_season',
            'stat_category' => 'ppg',
            'ranking' => 99,
            'player_name' => 'RCB Alltime Plyr',
            'car_block_id' => 5,
            'pid' => 200050001,
            'stat_value' => 28.5,
            'stat_raw' => 285,
            'team_of_record' => 1,
            'season_year' => 2099,
            'career_total' => 0,
            'source_file' => 'test.rcb',
        ]);

        self::assertGreaterThanOrEqual(1, $affected);
    }

    // ── upsertRcbSeasonRecord ───────────────────────────────────

    public function testUpsertRcbSeasonRecordInsertsNewRow(): void
    {
        $affected = $this->repo->upsertRcbSeasonRecord([
            'season_year' => 2099,
            'scope' => 'league',
            'team_id' => 0,
            'context' => 'home',
            'stat_category' => 'pts',
            'ranking' => 99,
            'player_name' => 'RCB Season Plyr',
            'player_position' => 'PG',
            'car_block_id' => 5,
            'pid' => 200050001,
            'stat_value' => 52,
            'record_season_year' => 2099,
            'source_file' => 'test.rcb',
        ]);

        self::assertGreaterThanOrEqual(1, $affected);
    }

    // ── resolveTeamIdByName ─────────────────────────────────────

    public function testResolveTeamIdByNameFindsDirectMatch(): void
    {
        // 'Metros' is in the seed (ibl_team_info, teamid=1)
        $result = $this->repo->resolveTeamIdByName('Metros');

        self::assertSame(1, $result);
    }

    public function testResolveTeamIdByNameFindsAlias(): void
    {
        // Rename teamid=10 to 'Sting' within the transaction so the alias
        // 'Hornets' → 'Sting' resolves (CI seed uses 'Spurs' for teamid=10)
        $stmt = $this->db->prepare('UPDATE ibl_team_info SET team_name = ? WHERE teamid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('si', $name, $tid);
        $name = 'Sting';
        $tid = 10;
        $stmt->execute();
        $stmt->close();

        $result = $this->repo->resolveTeamIdByName('Hornets');

        self::assertSame(10, $result);
    }

    public function testResolveTeamIdByNameReturnsNullForUnknown(): void
    {
        $result = $this->repo->resolveTeamIdByName('NoSuchTeam9999');

        self::assertNull($result);
    }

    // ── fetchMaxTradeGroupId ────────────────────────────────────

    public function testFetchMaxTradeGroupIdReturnsIntegerResult(): void
    {
        // Insert a transaction with a known trade_group_id
        $this->repo->upsertTransaction([
            'season_year' => 2099,
            'transaction_month' => 12,
            'transaction_day' => 25,
            'transaction_type' => 1,
            'pid' => 0,
            'player_name' => '',
            'from_teamid' => 1,
            'to_teamid' => 2,
            'injury_games_missed' => 0,
            'injury_description' => '',
            'trade_group_id' => 99999,
            'is_draft_pick' => 0,
            'draft_pick_year' => 0,
            'source_file' => 'test-max.trn',
        ]);

        $result = $this->repo->fetchMaxTradeGroupId();

        self::assertGreaterThanOrEqual(99999, $result);
    }

    // ── resolveTeamId ───────────────────────────────────────────

    public function testResolveTeamIdReturnsValidId(): void
    {
        self::assertSame(5, $this->repo->resolveTeamId(5));
        self::assertSame(0, $this->repo->resolveTeamId(0));
        self::assertSame(28, $this->repo->resolveTeamId(28));
        self::assertNull($this->repo->resolveTeamId(99));
        self::assertNull($this->repo->resolveTeamId(-1));
    }

    // ── getPlayerName ───────────────────────────────────────────

    public function testGetPlayerNameReturnsNameOrNull(): void
    {
        $this->insertTestPlayer(200100010, 'JSB Name Lookup');

        self::assertSame('JSB Name Lookup', $this->repo->getPlayerName(200100010));
        self::assertNull($this->repo->getPlayerName(999999999));
    }
}
