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

    protected function setUp(): void
    {
        parent::setUp();
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
     * An unknown sim ID returns a well-formed six-key context with empty
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
    }
}
