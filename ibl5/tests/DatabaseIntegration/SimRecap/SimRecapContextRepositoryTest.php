<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\SimRecap;

use JsbParser\TrnFileParser;
use PHPUnit\Framework\Attributes\Group;
use SimRecap\SimRecapContextRepository;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
final class SimRecapContextRepositoryTest extends DatabaseTestCase
{
    private SimRecapContextRepository $repo;

    /** @var array<int,true> pids already inserted into ibl_plr by seedBoxLine() */
    private array $seededPids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seededPids = [];
        $this->repo = new SimRecapContextRepository($this->db);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function seedTeam(int $teamid, string $city, string $name): void
    {
        $this->insertRow('ibl_team_info', [
            'teamid'    => $teamid,
            'team_city' => $city,
            'team_name' => $name,
        ]);
    }

    private function seedSim(int $sim, string $start, string $end): void
    {
        $this->insertRow('ibl_sim_dates', [
            'sim'        => $sim,
            'start_date' => $start,
            'end_date'   => $end,
        ]);
    }

    // ── Tests ──────────────────────────────────────────────────────────────────

    /**
     * Headline bug: a player injured on team A then traded A→B→C must appear
     * in active_injuries with current_teamid = C (ibl_plr.teamid), not A
     * (the from_teamid frozen in the TYPE_INJURY row at injury time).
     */
    public function testInjuredThenTradedPlayerIsAttributedToHisCurrentTeam(): void
    {
        $this->seedTeam(999001, 'Ayes City', 'Ayes');
        $this->seedTeam(999002, 'Bees City', 'Bees');
        $this->seedTeam(999003, 'Cees City', 'Cees');
        $this->seedSim(999001, '2026-01-01', '2026-01-07');
        $this->insertScheduleRow(2026, '2026-01-03', 999001, 110, 999002, 105);
        $this->insertScheduleRow(2026, '2026-01-03', 999002, 98, 999003, 102);
        // Player's current team (ibl_plr.teamid) is team C (999003) after both trades
        $this->insertTestPlayer(999001, 'Kobe Player', ['teamid' => 999003, 'pos' => 'SG']);
        // TYPE_INJURY: from_teamid=999001 is the frozen historical marker (team A at injury time)
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'         => 2026,
            'transaction_month'   => 1,
            'transaction_day'     => 2,
            'transaction_type'    => TrnFileParser::TYPE_INJURY,
            'pid'                 => 999001,
            'from_teamid'         => 999001,
            'injury_games_missed' => 30,
            'injury_description'  => 'Knee',
        ]);
        // Trade A→B
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'       => 2026,
            'transaction_month' => 1,
            'transaction_day'   => 3,
            'transaction_type'  => TrnFileParser::TYPE_TRADE,
            'pid'               => 999001,
            'from_teamid'       => 999001,
            'to_teamid'         => 999002,
            'is_draft_pick'     => 0,
        ]);
        // Trade B→C
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'       => 2026,
            'transaction_month' => 1,
            'transaction_day'   => 3,
            'transaction_type'  => TrnFileParser::TYPE_TRADE,
            'pid'               => 999001,
            'from_teamid'       => 999002,
            'to_teamid'         => 999003,
            'is_draft_pick'     => 0,
        ]);

        $ctx = $this->repo->buildContext(999001);

        // Roster: player must appear under team C (999003), not A (999001)
        $rosterPids = array_column($ctx['roster'][999003] ?? [], 'pid');
        self::assertContains(999001, $rosterPids,
            'Player must be in roster[999003] (current team C)');
        foreach ($ctx['roster'][999003] ?? [] as $entry) {
            if ($entry['pid'] === 999001) {
                self::assertSame(999003, $entry['current_teamid'],
                    'Roster entry current_teamid must be 999003 (ibl_plr.teamid)');
                self::assertNotSame(999001, $entry['current_teamid'],
                    'Roster entry current_teamid must not equal from_teamid (999001)');
            }
        }

        // active_injuries: injury must still be active, attributed to current team C
        $injuryPids = array_column($ctx['active_injuries'], 'pid');
        self::assertContains(999001, $injuryPids,
            'Injury must still be present in active_injuries');
        foreach ($ctx['active_injuries'] as $injury) {
            if ($injury['pid'] === 999001) {
                self::assertSame(999003, $injury['current_teamid'],
                    'active_injuries current_teamid must be 999003 (ibl_plr.teamid)');
                self::assertNotSame(999001, $injury['current_teamid'],
                    'active_injuries current_teamid must not equal from_teamid (999001)');
            }
        }
    }

    /**
     * A player who was never traded keeps his original team in both roster and
     * active_injuries, and must not appear in sim_trades.
     */
    public function testNeverTradedInjuredPlayerKeepsHisTeamAndInjury(): void
    {
        $this->seedTeam(999001, 'Ayes City', 'Ayes');
        $this->seedSim(999002, '2026-01-01', '2026-01-07');
        $this->insertScheduleRow(2026, '2026-01-03', 999001, 100, 999001, 90);
        $this->insertTestPlayer(999002, 'Steady Player', ['teamid' => 999001, 'pos' => 'PF']);
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'         => 2026,
            'transaction_month'   => 1,
            'transaction_day'     => 2,
            'transaction_type'    => TrnFileParser::TYPE_INJURY,
            'pid'                 => 999002,
            'from_teamid'         => 999001,
            'injury_games_missed' => 20,
            'injury_description'  => 'Ankle',
        ]);

        $ctx = $this->repo->buildContext(999002);

        $rosterPids = array_column($ctx['roster'][999001] ?? [], 'pid');
        self::assertContains(999002, $rosterPids,
            'Never-traded player must be in roster[999001]');

        $injuryPids = array_column($ctx['active_injuries'], 'pid');
        self::assertContains(999002, $injuryPids,
            'active_injuries must contain the player');
        foreach ($ctx['active_injuries'] as $injury) {
            if ($injury['pid'] === 999002) {
                self::assertSame(999001, $injury['current_teamid'],
                    'active_injuries current_teamid must match ibl_plr.teamid (999001)');
            }
        }

        $tradePids = array_column($ctx['sim_trades'], 'pid');
        self::assertNotContains(999002, $tradePids,
            'Never-traded player must be absent from sim_trades');
    }

    /**
     * TRANSACTION_DATE_SQL boundary: a Sep–Dec transaction_month uses season_year-1
     * as the calendar year. A season_year=2025, month=11 injury maps to calendar
     * date 2024-11, so it appears in a 2024-11 window but not a 2025-11 window.
     */
    public function testSeptemberToDecemberInjuryUsesSeasonYearMinusOne(): void
    {
        $this->seedTeam(999001, 'Ayes City', 'Ayes');
        // Sim A: 2024-11 window — injury must appear
        $this->seedSim(999003, '2024-11-01', '2024-11-30');
        $this->insertScheduleRow(2025, '2024-11-15', 999001, 100, 999001, 95);
        // Sim B: 2025-11 window — same injury row must NOT appear
        $this->seedSim(999004, '2025-11-01', '2025-11-30');
        $this->insertScheduleRow(2026, '2025-11-15', 999001, 100, 999001, 95);

        $this->insertTestPlayer(999003, 'Nov Player', ['teamid' => 999001, 'pos' => 'C']);
        // season_year=2025, month=11 → TRANSACTION_DATE_SQL → calendar 2024-11-10
        // injury_games_missed=30 → active through 2024-12-10 > 2024-11-30 (in window A)
        // but NOT active relative to 2025-11-30 (2024-12-10 < 2025-11-30, expired)
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'         => 2025,
            'transaction_month'   => 11,
            'transaction_day'     => 10,
            'transaction_type'    => TrnFileParser::TYPE_INJURY,
            'pid'                 => 999003,
            'from_teamid'         => 999001,
            'injury_games_missed' => 30,
            'injury_description'  => 'Back',
        ]);

        // Positive: 2024-11 window — injury must appear
        $ctxA = $this->repo->buildContext(999003);
        $injuryPidsA = array_column($ctxA['active_injuries'], 'pid');
        self::assertContains(999003, $injuryPidsA,
            'Nov injury (season_year=2025) must appear in 2024-11 window');
        foreach ($ctxA['active_injuries'] as $injury) {
            if ($injury['pid'] === 999003) {
                self::assertStringStartsWith('2024-11', $injury['date'],
                    'Injury date must be in 2024-11 (season_year - 1)');
            }
        }

        // Negative: 2025-11 window — same injury must NOT appear
        $ctxB = $this->repo->buildContext(999004);
        $injuryPidsB = array_column($ctxB['active_injuries'], 'pid');
        self::assertNotContains(999003, $injuryPidsB,
            'Nov injury (calendar 2024-11) must be absent from 2025-11 window');
    }

    /**
     * Only trades whose calendar date falls inside [start, end] appear in sim_trades.
     * Per-pid membership assertions used rather than assertCount or exact-set checks
     * to avoid flakiness from pre-existing rows in the shared DB.
     */
    public function testOnlyTradesInsideTheSimWindowAreEmitted(): void
    {
        $this->seedTeam(999001, 'Ayes City', 'Ayes');
        $this->seedTeam(999002, 'Bees City', 'Bees');
        $this->seedSim(999005, '2026-01-01', '2026-01-07');
        $this->insertScheduleRow(2026, '2026-01-04', 999001, 105, 999002, 98);
        $this->insertTestPlayer(999010, 'Inside Player', ['teamid' => 999001, 'pos' => 'PG']);
        $this->insertTestPlayer(999011, 'Before Player', ['teamid' => 999001, 'pos' => 'SG']);
        $this->insertTestPlayer(999012, 'After Player',  ['teamid' => 999002, 'pos' => 'SF']);

        // Inside window: season_year=2026, month=1, day=4 → calendar 2026-01-04
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'       => 2026,
            'transaction_month' => 1,
            'transaction_day'   => 4,
            'transaction_type'  => TrnFileParser::TYPE_TRADE,
            'pid'               => 999010,
            'from_teamid'       => 999001,
            'to_teamid'         => 999002,
            'is_draft_pick'     => 0,
        ]);
        // Before window: season_year=2026, month=12, day=28
        // → TRANSACTION_DATE_SQL: month >= 9 → calendar year = 2026 - 1 = 2025
        // → calendar 2025-12-28 (before 2026-01-01 window start)
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'       => 2026,
            'transaction_month' => 12,
            'transaction_day'   => 28,
            'transaction_type'  => TrnFileParser::TYPE_TRADE,
            'pid'               => 999011,
            'from_teamid'       => 999001,
            'to_teamid'         => 999002,
            'is_draft_pick'     => 0,
        ]);
        // After window: season_year=2026, month=1, day=15 → calendar 2026-01-15
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'       => 2026,
            'transaction_month' => 1,
            'transaction_day'   => 15,
            'transaction_type'  => TrnFileParser::TYPE_TRADE,
            'pid'               => 999012,
            'from_teamid'       => 999002,
            'to_teamid'         => 999001,
            'is_draft_pick'     => 0,
        ]);
        // Draft-pick row inside window: is_draft_pick=1 means pid is a pick id, not a player;
        // the getSimTrades() predicate (is_draft_pick = 0) must exclude this row.
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'       => 2026,
            'transaction_month' => 1,
            'transaction_day'   => 5,
            'transaction_type'  => TrnFileParser::TYPE_TRADE,
            'pid'               => 999013,
            'from_teamid'       => 999001,
            'to_teamid'         => 999002,
            'is_draft_pick'     => 1,
        ]);

        $ctx = $this->repo->buildContext(999005);
        $tradePids = array_column($ctx['sim_trades'], 'pid');

        self::assertContains(999010, $tradePids,
            'Inside-window trade (2026-01-04) must appear in sim_trades');
        self::assertNotContains(999011, $tradePids,
            'Before-window trade (calendar 2025-12-28) must be absent from sim_trades');
        self::assertNotContains(999012, $tradePids,
            'After-window trade (2026-01-15) must be absent from sim_trades');
        self::assertNotContains(999013, $tradePids,
            'Draft-pick row (is_draft_pick=1) inside window must be absent from sim_trades');
    }

    /**
     * Games follow the box-score ordinal (not schedule insert order), carry the
     * schedule scores, and emit a 0 box_id as null. Keys are script-owned g1..gN.
     */
    public function testGamesCarryScheduleScoresAndBoxScoreOrdinal(): void
    {
        $this->seedTeam(999101, 'Ayes City', 'Ayes');
        $this->seedTeam(999102, 'Bees City', 'Bees');
        $this->seedTeam(999103, 'Cees City', 'Cees');
        $this->seedTeam(999104, 'Dees City', 'Dees');
        $this->seedStanding(999103, 'Cees', 40, 20);
        $this->seedSim(999101, '2031-02-01', '2031-02-07');
        // Inserted first (lower schedule id) but carries ordinal 2.
        $this->insertScheduleRow(2031, '2031-02-03', 999101, 100, 999102, 90, 0);
        $this->insertScheduleRow(2031, '2031-02-03', 999103, 80, 999104, 85, 7);
        $this->insertTeamBoxscoreRow('2031-02-03', 'Ayes', 2, 999101, 999102);
        $this->insertTeamBoxscoreRow('2031-02-03', 'Cees', 1, 999103, 999104);

        $games = $this->repo->buildContext(999101)['games'];

        self::assertCount(2, $games);
        self::assertSame('g1', $games[0]['key']);
        self::assertSame('g2', $games[1]['key']);

        self::assertSame(999103, $games[0]['visitor_teamid']);
        self::assertSame(1, $games[0]['game_of_that_day']);
        self::assertSame(7, $games[0]['box_id']);
        self::assertSame(80, $games[0]['visitor_score']);
        self::assertSame(85, $games[0]['home_score']);
        self::assertSame('Cees', $games[0]['visitor_name']);
        self::assertNull($games[0]['home_name']);
        self::assertSame('2031-02-03', $games[0]['game_date']);
        self::assertSame(2031, $games[0]['season_year']);

        self::assertSame(999101, $games[1]['visitor_teamid']);
        self::assertSame(2, $games[1]['game_of_that_day']);
        self::assertNull($games[1]['box_id']);
        self::assertSame(100, $games[1]['visitor_score']);
        self::assertSame(90, $games[1]['home_score']);
    }

    /**
     * Negative path: a played game with no ibl_box_scores_teams row is still
     * emitted, with a null ordinal.
     */
    public function testGameWithoutTeamBoxRowEmitsNullOrdinal(): void
    {
        $this->seedTeam(999111, 'Ayes City', 'Ayes');
        $this->seedTeam(999112, 'Bees City', 'Bees');
        $this->seedSim(999111, '2031-03-01', '2031-03-07');
        $this->insertScheduleRow(2031, '2031-03-03', 999111, 99, 999112, 97);

        $games = $this->repo->buildContext(999111)['games'];

        self::assertCount(1, $games);
        self::assertNull($games[0]['game_of_that_day']);
        self::assertSame(99, $games[0]['visitor_score']);
    }

    /**
     * Boundary: a 0-0 (unplayed) schedule row inside the window is not a game.
     */
    public function testUnplayedScheduleRowIsExcludedFromGames(): void
    {
        $this->seedTeam(999121, 'Ayes City', 'Ayes');
        $this->seedTeam(999122, 'Bees City', 'Bees');
        $this->seedTeam(999123, 'Cees City', 'Cees');
        $this->seedSim(999121, '2031-04-01', '2031-04-07');
        $this->insertScheduleRow(2031, '2031-04-03', 999121, 101, 999122, 99);
        $this->insertScheduleRow(2031, '2031-04-04', 999121, 0, 999123, 0);

        $games = $this->repo->buildContext(999121)['games'];

        self::assertCount(1, $games);
        self::assertSame(999122, $games[0]['home_teamid']);
    }

    /**
     * A side keeps at most three lines, ordered by points; a DNP row (game_min 0)
     * never appears even when it carries the highest points.
     */
    public function testTopPerformersCappedPerSideAndOrderedByPoints(): void
    {
        $date = '2031-05-03';
        $this->seedTeam(999131, 'Ayes City', 'Ayes');
        $this->seedTeam(999132, 'Bees City', 'Bees');
        $this->seedSim(999131, '2031-05-01', '2031-05-07');
        $this->insertScheduleRow(2031, $date, 999131, 100, 999132, 90);
        $this->insertTeamBoxscoreRow($date, 'Ayes', 1, 999131, 999132);
        // Points = 2 * p2m (no FT, no 3s): 8, 20, 12, 16.
        $this->seedBoxLine($date, 200000301, 'Low Scorer', 999131, 999132, 999131, 30, 4);
        $this->seedBoxLine($date, 200000302, 'High Scorer', 999131, 999132, 999131, 30, 10);
        $this->seedBoxLine($date, 200000303, 'Third Scorer', 999131, 999132, 999131, 30, 6);
        $this->seedBoxLine($date, 200000304, 'Second Scorer', 999131, 999132, 999131, 30, 8);
        $this->seedBoxLine($date, 200000305, 'Bench Warmer', 999131, 999132, 999131, 0, 20);
        $this->seedBoxLine($date, 200000306, 'Home Scorer', 999131, 999132, 999132, 25, 5);

        $game = $this->repo->buildContext(999131)['games'][0];

        self::assertCount(3, $game['visitor_top']);
        self::assertSame([20, 16, 12], array_column($game['visitor_top'], 'pts'));
        self::assertSame(200000302, $game['visitor_top'][0]['pid']);
        self::assertSame('High Scorer', $game['visitor_top'][0]['name']);
        self::assertSame(30, $game['visitor_top'][0]['min']);
        self::assertSame(10, $game['visitor_top'][0]['fgm']);
        self::assertSame(10, $game['visitor_top'][0]['fga']);
        self::assertNotContains(200000305, array_column($game['visitor_top'], 'pid'));

        self::assertCount(1, $game['home_top']);
        self::assertSame(200000306, $game['home_top'][0]['pid']);
    }

    /**
     * An injury dated on game A's date for a player who played in A attaches to A
     * only; game B (another date) carries no injuries even though the player played.
     */
    public function testInjuryOnGameDateIsAttachedToThatGameOnly(): void
    {
        $this->seedTeam(999141, 'Ayes City', 'Ayes');
        $this->seedTeam(999142, 'Bees City', 'Bees');
        $this->seedSim(999141, '2031-06-01', '2031-06-07');
        $this->insertScheduleRow(2031, '2031-06-03', 999141, 100, 999142, 90);
        $this->insertScheduleRow(2031, '2031-06-05', 999142, 95, 999141, 97);
        $this->insertTeamBoxscoreRow('2031-06-03', 'Ayes', 1, 999141, 999142);
        $this->insertTeamBoxscoreRow('2031-06-05', 'Bees', 1, 999142, 999141);
        $this->seedBoxLine('2031-06-03', 200000311, 'Hurt Player', 999141, 999142, 999141, 30, 6);
        $this->seedBoxLine('2031-06-03', 200000312, 'Healthy Player', 999141, 999142, 999141, 30, 5);
        $this->seedBoxLine('2031-06-05', 200000311, 'Hurt Player', 999142, 999141, 999141, 20, 3);
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'         => 2031,
            'transaction_month'   => 6,
            'transaction_day'     => 3,
            'transaction_type'    => TrnFileParser::TYPE_INJURY,
            'pid'                 => 200000311,
            'from_teamid'         => 999141,
            'injury_games_missed' => 30,
            'injury_description'  => 'Wrist',
        ]);

        $games = $this->repo->buildContext(999141)['games'];

        self::assertCount(2, $games);
        self::assertSame('2031-06-03', $games[0]['game_date']);
        self::assertSame([200000311], array_column($games[0]['injuries'], 'pid'));
        self::assertSame('Wrist', $games[0]['injuries'][0]['injuryDescription']);
        self::assertSame(30, $games[0]['injuries'][0]['injuryGamesMissed']);
        self::assertSame([], $games[1]['injuries']);
    }

    /**
     * The roster keeps rostered players who played, are injured, or were traded;
     * a bench player with none of those is dropped, and a team left empty is dropped.
     */
    public function testRosterKeepsOnlyPlayedInjuredAndTradedPlayers(): void
    {
        $date = '2031-07-03';
        $this->seedTeam(999151, 'Ayes City', 'Ayes');
        $this->seedTeam(999152, 'Bees City', 'Bees');
        $this->seedTeam(999153, 'Cees City', 'Cees');
        $this->seedSim(999151, '2031-07-01', '2031-07-07');
        $this->insertScheduleRow(2031, $date, 999151, 100, 999152, 90);
        $this->insertScheduleRow(2031, $date, 999153, 88, 999153, 77);
        $this->insertTeamBoxscoreRow($date, 'Ayes', 1, 999151, 999152);
        $this->seedBoxLine($date, 200000321, 'Played Guy', 999151, 999152, 999151, 30, 5);
        // DNP row (game_min 0): a box line exists but he did not play.
        $this->seedBoxLine($date, 200000322, 'Bench Guy', 999151, 999152, 999151, 0, 0);
        $this->insertTestPlayer(200000323, 'Injured Guy', ['teamid' => 999151]);
        $this->insertTestPlayer(200000324, 'Traded Guy', ['teamid' => 999152]);
        $this->insertTestPlayer(200000325, 'Other Bench', ['teamid' => 999152]);
        $this->insertTestPlayer(200000326, 'Lonely Bench', ['teamid' => 999153]);
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'         => 2031,
            'transaction_month'   => 7,
            'transaction_day'     => 2,
            'transaction_type'    => TrnFileParser::TYPE_INJURY,
            'pid'                 => 200000323,
            'from_teamid'         => 999151,
            'injury_games_missed' => 30,
            'injury_description'  => 'Hamstring',
        ]);
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'       => 2031,
            'transaction_month' => 7,
            'transaction_day'   => 4,
            'transaction_type'  => TrnFileParser::TYPE_TRADE,
            'pid'               => 200000324,
            'from_teamid'       => 999151,
            'to_teamid'         => 999152,
            'is_draft_pick'     => 0,
        ]);

        $ctx = $this->repo->buildContext(999151);

        self::assertEqualsCanonicalizing(
            [200000321, 200000323],
            array_column($ctx['roster'][999151] ?? [], 'pid')
        );
        self::assertSame([200000324], array_column($ctx['roster'][999152] ?? [], 'pid'));
        self::assertArrayNotHasKey(999153, $ctx['roster']);
        // The injured non-player keeps his injury row too.
        self::assertContains(200000323, array_column($ctx['active_injuries'], 'pid'));
    }

    /**
     * Standings carry the record for each window team; streak/last10 come from
     * ibl_power and are empty strings for a team with no power row.
     */
    public function testStandingsCarryRecordAndStreakForWindowTeams(): void
    {
        $this->seedTeam(999161, 'Ayes City', 'Ayes');
        $this->seedTeam(999162, 'Bees City', 'Bees');
        $this->seedStanding(999161, 'Ayes', 50, 10);
        $this->seedStanding(999162, 'Bees', 40, 20);
        $this->insertRow('ibl_power', [
            'teamid'      => 999161,
            'last_win'    => 7,
            'last_loss'   => 3,
            'streak_type' => 'W',
            'streak'      => 3,
        ]);
        $this->seedSim(999161, '2031-08-01', '2031-08-07');
        $this->insertScheduleRow(2031, '2031-08-03', 999161, 100, 999162, 90);

        $standings = $this->repo->buildContext(999161)['standings'];

        self::assertSame([999161, 999162], array_column($standings, 'teamid'));
        self::assertSame('Ayes', $standings[0]['name']);
        self::assertSame(50, $standings[0]['wins']);
        self::assertSame(10, $standings[0]['losses']);
        self::assertSame('W3', $standings[0]['streak']);
        self::assertSame('7-3', $standings[0]['last10']);
        self::assertSame(40, $standings[1]['wins']);
        self::assertSame('', $standings[1]['streak']);
        self::assertSame('', $standings[1]['last10']);
    }

    /**
     * An unknown sim ID returns a well-formed eight-key context with empty
     * collections rather than throwing.
     */
    public function testUnknownSimReturnsWellFormedEmptyContextWithoutThrowing(): void
    {
        $ctx = $this->repo->buildContext(999999);

        self::assertSame(999999, $ctx['sim']);
        self::assertNull($ctx['start_date']);
        self::assertNull($ctx['end_date']);
        self::assertSame([], $ctx['roster']);
        self::assertSame([], $ctx['active_injuries']);
        self::assertSame([], $ctx['sim_trades']);
        self::assertSame([], $ctx['games']);
        self::assertSame([], $ctx['standings']);
    }

    // ── Seed helpers (game facts) ──────────────────────────────────────────────

    private function seedStanding(int $teamid, string $name, int $wins, int $losses): void
    {
        $this->insertRow('ibl_standings', [
            'teamid'     => $teamid,
            'team_name'  => $name,
            'wins'       => $wins,
            'losses'     => $losses,
            'conference' => 'Eastern',
            'division'   => 'Atlantic',
        ]);
    }

    /**
     * Seeds the player plus one box-score line. Points = 2 * $twoPointMade
     * (no free throws or threes), so tests control scoring through one number.
     */
    private function seedBoxLine(
        string $date,
        int $pid,
        string $name,
        int $visitorTid,
        int $homeTid,
        int $teamId,
        int $minutes,
        int $twoPointMade,
    ): void {
        if (!isset($this->seededPids[$pid])) {
            $this->insertTestPlayer($pid, $name, ['teamid' => $teamId]);
            $this->seededPids[$pid] = true;
        }
        $this->insertPlayerBoxscoreRow(
            $date,
            $pid,
            $name,
            'PG',
            $visitorTid,
            $homeTid,
            $teamId,
            minutes: $minutes,
            points2m: $twoPointMade,
            points2a: 10,
            ftm: 0,
            fta: 0,
            points3m: 0,
            points3a: 0,
        );
    }
}
