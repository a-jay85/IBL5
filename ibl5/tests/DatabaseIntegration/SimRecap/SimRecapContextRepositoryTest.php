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

    private function seedSim(int $sim, string $start, string $end): void
    {
        $this->insertRow('ibl_sim_dates', [
            'sim'        => $sim,
            'start_date' => $start,
            'end_date'   => $end,
        ]);
    }

    // ── Tests ──────────────────────────────────────────────────────────────────

    public function testBuildContextRosterContainsPlayersForTeamsInWindow(): void
    {
        $this->seedSim(999001, '2026-01-01', '2026-01-07');
        $this->insertScheduleRow(2026, '2026-01-05', 11, 100, 12, 90);
        $this->insertTestPlayer(999001, 'Player One', ['teamid' => 11, 'pos' => 'PG']);

        $ctx = $this->repo->buildContext(999001);

        self::assertSame(999001, $ctx['sim']);
        self::assertSame('2026-01-01', $ctx['start_date']);
        self::assertSame('2026-01-07', $ctx['end_date']);
        self::assertArrayHasKey(11, $ctx['roster']);

        $team11 = $ctx['roster'][11];
        $pids = array_column($team11, 'pid');
        self::assertContains(999001, $pids);

        $player = $team11[array_search(999001, $pids, true)];
        self::assertSame('Player One', $player['name']);
        self::assertSame('PG', $player['pos']);
        self::assertSame(11, $player['current_teamid']);
    }

    public function testBuildContextReturnsActiveInjuryInWindow(): void
    {
        $this->seedSim(999002, '2026-01-01', '2026-01-07');
        $this->insertScheduleRow(2026, '2026-01-05', 11, 100, 12, 90);
        $this->insertTestPlayer(999002, 'Injured Player', ['teamid' => 11]);

        // Injury on 2026-01-03, 20 games missed → still active on 2026-01-07
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'          => 2026,
            'transaction_month'    => 1,
            'transaction_day'      => 3,
            'transaction_type'     => TrnFileParser::TYPE_INJURY,
            'pid'                  => 999002,
            'injury_games_missed'  => 20,
            'injury_description'   => 'Knee',
        ]);

        $ctx = $this->repo->buildContext(999002);

        self::assertNotEmpty($ctx['active_injuries']);
        $pids = array_column($ctx['active_injuries'], 'pid');
        self::assertContains(999002, $pids);
    }

    public function testBuildContextReturnsTradesInWindow(): void
    {
        $this->seedSim(999003, '2026-01-01', '2026-01-07');
        $this->insertScheduleRow(2026, '2026-01-05', 11, 100, 12, 90);
        $this->insertTestPlayer(999003, 'Trade Player', ['teamid' => 11]);

        // Trade on 2026-01-04 (within window)
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'       => 2026,
            'transaction_month' => 1,
            'transaction_day'   => 4,
            'transaction_type'  => TrnFileParser::TYPE_TRADE,
            'pid'               => 999003,
            'from_teamid'       => 5,
            'to_teamid'         => 11,
            'is_draft_pick'     => 0,
        ]);

        $ctx = $this->repo->buildContext(999003);

        self::assertCount(1, $ctx['sim_trades']);
        self::assertSame(999003, $ctx['sim_trades'][0]['pid']);
        self::assertSame('2026-01-04', $ctx['sim_trades'][0]['trade_date']);
    }

    public function testBuildContextExcludesTradesOutsideWindow(): void
    {
        $this->seedSim(999004, '2026-01-01', '2026-01-07');
        $this->insertScheduleRow(2026, '2026-01-05', 11, 100, 12, 90);

        // Trade on 2026-01-15 (outside window)
        $this->insertRow('ibl_jsb_transactions', [
            'season_year'       => 2026,
            'transaction_month' => 1,
            'transaction_day'   => 15,
            'transaction_type'  => TrnFileParser::TYPE_TRADE,
            'pid'               => 999004,
            'is_draft_pick'     => 0,
        ]);

        $ctx = $this->repo->buildContext(999004);

        self::assertSame([], $ctx['sim_trades']);
    }

    public function testUnknownSimReturnsWellFormedEmptyContext(): void
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
