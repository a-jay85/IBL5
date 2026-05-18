<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use JsbParser\JsbImportRepository;
use JsbParser\JsbImportService;
use JsbParser\PlayerIdResolver;
use JsbParser\RcbFileParser;

/**
 * Tests JsbImportRepository against real MariaDB — upserts into ibl_hist,
 * ibl_jsb_transactions, ibl_jsb_history, ibl_jsb_allstar_rosters,
 * ibl_jsb_allstar_scores, ibl_rcb_alltime_records, ibl_rcb_season_records.
 *
 * Also covers resolveTeamId() (pure logic) and getPlayerName() (DB read).
 */
#[Group('database')]
class JsbImportRepositoryTest extends DatabaseTestCase
{
    private JsbImportRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new JsbImportRepository($this->db);
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

    // ── replaceRcbAlltimeRecords ──────────────────────────────────

    public function testReplaceRcbAlltimeRecordsInsertsAllRows(): void
    {
        $records = [];
        for ($i = 1; $i <= 3; $i++) {
            $records[] = [
                'scope' => 'league',
                'teamid' => 0,
                'record_type' => 'single_season',
                'stat_category' => 'ppg',
                'ranking' => 90 + $i,
                'player_name' => "Test Player $i",
                'car_block_id' => $i,
                'pid' => null,
                'stat_value' => 20.0 + $i,
                'stat_raw' => 200 + $i,
                'team_of_record' => 1,
                'season_year' => 2099,
                'career_total' => null,
                'source_file' => 'test.rcb',
            ];
        }

        $inserted = $this->repo->replaceRcbAlltimeRecords($records);

        self::assertSame(3, $inserted);
    }

    public function testReplaceRcbAlltimeRecordsPrunesPreviousRows(): void
    {
        $seedRecords = [];
        for ($rank = 1; $rank <= 10; $rank++) {
            $seedRecords[] = [
                'scope' => 'team',
                'teamid' => 4,
                'record_type' => 'single_season',
                'stat_category' => 'ppg',
                'ranking' => $rank,
                'player_name' => "Old Plyr $rank",
                'car_block_id' => $rank,
                'pid' => null,
                'stat_value' => 20.0,
                'stat_raw' => 200,
                'team_of_record' => 4,
                'season_year' => 2099,
                'career_total' => null,
                'source_file' => 'old.rcb',
            ];
        }
        $this->repo->replaceRcbAlltimeRecords($seedRecords);

        $newRecords = [[
            'scope' => 'team',
            'teamid' => 4,
            'record_type' => 'single_season',
            'stat_category' => 'ppg',
            'ranking' => 1,
            'player_name' => 'New Rank One',
            'car_block_id' => 1,
            'pid' => null,
            'stat_value' => 30.0,
            'stat_raw' => 300,
            'team_of_record' => 4,
            'season_year' => 2099,
            'career_total' => null,
            'source_file' => 'new.rcb',
        ]];
        $this->repo->replaceRcbAlltimeRecords($newRecords);

        $stmt = $this->db->prepare("SELECT COUNT(*) AS cnt FROM ibl_rcb_alltime_records");
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        self::assertNotNull($row);
        self::assertSame(1, (int) $row['cnt']);
    }

    public function testReplaceRcbAlltimeRecordsIsAtomic(): void
    {
        $seedRecords = [[
            'scope' => 'league',
            'teamid' => 0,
            'record_type' => 'single_season',
            'stat_category' => 'ppg',
            'ranking' => 99,
            'player_name' => 'Seed Player',
            'car_block_id' => 1,
            'pid' => null,
            'stat_value' => 25.0,
            'stat_raw' => 250,
            'team_of_record' => 1,
            'season_year' => 2099,
            'career_total' => null,
            'source_file' => 'seed.rcb',
        ]];
        $this->repo->replaceRcbAlltimeRecords($seedRecords);

        $badRecords = [[
            'scope' => 'league',
            'teamid' => 0,
            'record_type' => 'single_season',
            'stat_category' => 'ppg',
            'ranking' => 1,
            'player_name' => 'Good Row',
            'car_block_id' => 1,
            'pid' => null,
            'stat_value' => 30.0,
            'stat_raw' => 300,
            'team_of_record' => 1,
            'season_year' => 2099,
            'career_total' => null,
            'source_file' => 'bad.rcb',
        ], [
            'scope' => 'league',
            'teamid' => 0,
            'record_type' => 'single_season',
            'stat_category' => 'ppg',
            'ranking' => 1,
            'player_name' => 'Duplicate Rank',
            'car_block_id' => 2,
            'pid' => null,
            'stat_value' => 35.0,
            'stat_raw' => 350,
            'team_of_record' => 1,
            'season_year' => 2099,
            'career_total' => null,
            'source_file' => 'bad.rcb',
        ]];

        try {
            $this->repo->replaceRcbAlltimeRecords($badRecords);
            self::fail('Expected exception for duplicate ranking');
        } catch (\RuntimeException) {
            // expected
        }

        $stmt = $this->db->prepare(
            "SELECT player_name FROM ibl_rcb_alltime_records
             WHERE scope = 'league' AND teamid = 0 AND record_type = 'single_season'
               AND stat_category = 'ppg' AND ranking = 99"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        self::assertNotNull($row, 'Seed data survives failed replace (rollback worked)');
        self::assertSame('Seed Player', $row['player_name']);
    }

    // ── replaceRcbSeasonRecords ─────────────────────────────────

    public function testReplaceRcbSeasonRecordsScopedToSeasonYear(): void
    {
        $records2024 = [];
        for ($i = 1; $i <= 3; $i++) {
            $records2024[] = [
                'season_year' => 2098,
                'scope' => 'league',
                'teamid' => 0,
                'context' => 'home',
                'stat_category' => 'pts',
                'ranking' => $i,
                'player_name' => "Player24 $i",
                'player_position' => 'PG',
                'car_block_id' => $i,
                'pid' => null,
                'stat_value' => 50 + $i,
                'record_season_year' => 2098,
                'source_file' => 'test.rcb',
            ];
        }
        $this->repo->replaceRcbSeasonRecords(2098, $records2024);

        $records2025 = [[
            'season_year' => 2099,
            'scope' => 'league',
            'teamid' => 0,
            'context' => 'home',
            'stat_category' => 'pts',
            'ranking' => 1,
            'player_name' => 'Player25',
            'player_position' => 'SG',
            'car_block_id' => 1,
            'pid' => null,
            'stat_value' => 60,
            'record_season_year' => 2099,
            'source_file' => 'test.rcb',
        ]];
        $this->repo->replaceRcbSeasonRecords(2099, $records2025);

        $newRecords2098 = [[
            'season_year' => 2098,
            'scope' => 'league',
            'teamid' => 0,
            'context' => 'home',
            'stat_category' => 'pts',
            'ranking' => 1,
            'player_name' => 'NewPlayer24',
            'player_position' => 'PF',
            'car_block_id' => 10,
            'pid' => null,
            'stat_value' => 70,
            'record_season_year' => 2098,
            'source_file' => 'test.rcb',
        ]];
        $this->repo->replaceRcbSeasonRecords(2098, $newRecords2098);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt FROM ibl_rcb_season_records WHERE season_year = 2098"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        self::assertNotNull($row);
        self::assertSame(1, (int) $row['cnt'], '2098 replaced to 1 row');

        $stmt2 = $this->db->prepare(
            "SELECT COUNT(*) AS cnt FROM ibl_rcb_season_records WHERE season_year = 2099"
        );
        self::assertNotFalse($stmt2);
        $stmt2->execute();
        $row2 = $stmt2->get_result()->fetch_assoc();
        self::assertNotNull($row2);
        self::assertSame(1, (int) $row2['cnt'], '2099 unchanged');
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
        $stmt->bind_param('si', $name, $teamid);
        $name = 'Sting';
        $teamid = 10;
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

    // ── End-to-end RCB replace integration ─────────────────────

    public function testCurrentSeasonRcbImportPrunesAllPreviousAlltimeRows(): void
    {
        $seedRecords = [];
        for ($teamid = 1; $teamid <= 3; $teamid++) {
            for ($rank = 1; $rank <= 5; $rank++) {
                $seedRecords[] = [
                    'scope' => 'team',
                    'teamid' => $teamid,
                    'record_type' => 'single_season',
                    'stat_category' => 'ppg',
                    'ranking' => $rank,
                    'player_name' => "Stale T{$teamid}R{$rank}",
                    'car_block_id' => $rank,
                    'pid' => null,
                    'stat_value' => 20.0,
                    'stat_raw' => 200,
                    'team_of_record' => $teamid,
                    'season_year' => 2090,
                    'career_total' => null,
                    'source_file' => 'stale.rcb',
                ];
            }
        }
        $this->repo->replaceRcbAlltimeRecords($seedRecords);

        $rcbData = $this->buildMinimalRcbData();
        $resolver = new PlayerIdResolver($this->db);
        $service = new JsbImportService($this->repo, $resolver);

        $result = $service->processRcbData($rcbData, 2026, 'current-season', includeAlltime: true);

        self::assertSame(0, $result->errors);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt FROM ibl_rcb_alltime_records
             WHERE source_file = 'stale.rcb'"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        self::assertNotNull($row);
        self::assertSame(0, (int) $row['cnt'], 'All stale alltime rows pruned');
    }

    public function testHistoricalRcbBulkImportDoesNotTouchAlltimeTable(): void
    {
        $seedRecords = [];
        for ($rank = 1; $rank <= 5; $rank++) {
            $seedRecords[] = [
                'scope' => 'league',
                'teamid' => 0,
                'record_type' => 'single_season',
                'stat_category' => 'ppg',
                'ranking' => $rank,
                'player_name' => "Keep Plyr $rank",
                'car_block_id' => $rank,
                'pid' => null,
                'stat_value' => 25.0,
                'stat_raw' => 250,
                'team_of_record' => 1,
                'season_year' => 2099,
                'career_total' => null,
                'source_file' => 'keep.rcb',
            ];
        }
        $this->repo->replaceRcbAlltimeRecords($seedRecords);

        $rcbData = $this->buildMinimalRcbData();
        $resolver = new PlayerIdResolver($this->db);
        $service = new JsbImportService($this->repo, $resolver);

        $result = $service->processRcbData($rcbData, 2007, '06-07', includeAlltime: false);

        self::assertSame(0, $result->errors);

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt FROM ibl_rcb_alltime_records
             WHERE source_file = 'keep.rcb'"
        );
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        self::assertNotNull($row);
        self::assertSame(5, (int) $row['cnt'], 'Alltime rows untouched by historical import');

        $stmt2 = $this->db->prepare(
            "SELECT COUNT(*) AS cnt FROM ibl_rcb_season_records WHERE season_year = 2007"
        );
        self::assertNotFalse($stmt2);
        $stmt2->execute();
        $row2 = $stmt2->get_result()->fetch_assoc();
        self::assertNotNull($row2);
        self::assertGreaterThan(0, (int) $row2['cnt'], 'Season records for 2007 were inserted');
    }

    /**
     * @return string Valid .rcb file data with one alltime and one season entry
     */
    private function buildMinimalRcbData(): string
    {
        $alltimeEntry = str_pad('Stephen Curry', 33, ' ', STR_PAD_LEFT)
            . str_pad('3851', 5, ' ', STR_PAD_LEFT)
            . str_pad('3611', 6, ' ', STR_PAD_LEFT)
            . str_pad('19', 2, ' ', STR_PAD_LEFT)
            . str_pad('2005', 4);
        $blankAlltime = str_repeat(' ', RcbFileParser::ALLTIME_ENTRY_SIZE);
        $alltimeLine0 = $alltimeEntry . str_repeat($blankAlltime, RcbFileParser::ENTRIES_PER_ALLTIME_LINE - 1);
        $blankAlltimeLine = str_repeat($blankAlltime, RcbFileParser::ENTRIES_PER_ALLTIME_LINE);
        $alltimeLines = [$alltimeLine0];
        for ($i = 1; $i < RcbFileParser::ALLTIME_LINE_COUNT; $i++) {
            $alltimeLines[] = $blankAlltimeLine;
        }

        $seasonEntry = str_pad('PG Stephen Curry', 33, ' ', STR_PAD_LEFT)
            . str_pad('3851', 5, ' ', STR_PAD_LEFT)
            . str_pad('73', 3, ' ', STR_PAD_LEFT)
            . str_pad('2006', 4)
            . str_repeat(' ', 45);
        $blankSeason = str_repeat(' ', RcbFileParser::SEASON_ENTRY_SIZE);
        $seasonLine0 = $seasonEntry . str_repeat($blankSeason, RcbFileParser::ENTRIES_PER_SEASON_LINE - 1);
        $blankSeasonLine = str_repeat($blankSeason, RcbFileParser::ENTRIES_PER_SEASON_LINE);
        $seasonLines = [$seasonLine0];
        for ($i = 1; $i < RcbFileParser::SEASON_LINE_COUNT; $i++) {
            $seasonLines[] = $blankSeasonLine;
        }

        $trailing = [str_repeat(' ', 110), str_repeat(' ', 56), str_repeat(' ', 22)];
        return implode("\r\n", array_merge($alltimeLines, $seasonLines, $trailing)) . "\r\n";
    }
}
