<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\ProjectionFactory;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\LegalityPreFilter;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\TradeCandidate;

final class LegalityPreFilterTest extends TestCase
{
    public function testRosterExactlyFifteenAfterPasses(): void
    {
        $s = self::league(array_fill(0, 14, 100), array_fill(0, 14, 100));
        $c = new TradeCandidate(1, 2, [101], [], [201, 202], []);

        self::assertSame(15, count($s->lineups[0][1]->players) - 1 + 2);
        self::assertNull(LegalityPreFilter::reason($s, $c));
        self::assertTrue(LegalityPreFilter::passes($s, $c));
    }

    public function testRosterSixteenAfterRefused(): void
    {
        $s = self::league(array_fill(0, 15, 100), array_fill(0, 14, 100));
        $c = new TradeCandidate(1, 2, [101], [], [201, 202], []);

        self::assertSame(LegalityPreFilter::ROSTER, LegalityPreFilter::reason($s, $c));
        self::assertFalse(LegalityPreFilter::passes($s, $c));
    }

    public function testHardCapExactlyPasses(): void
    {
        $s = self::league([...array_fill(0, 12, 500), 100], [1000, ...array_fill(0, 10, 100)]);
        self::assertSame(6100, $s->payrollByOffset[0][1]);
        $c = new TradeCandidate(1, 2, [113], [], [201], []);

        self::assertNull(LegalityPreFilter::reason($s, $c));
    }

    public function testHardCapOverByOneRefused(): void
    {
        $s = self::league([...array_fill(0, 12, 500), 100], [1001, ...array_fill(0, 10, 100)]);
        $c = new TradeCandidate(1, 2, [113], [], [201], []);

        self::assertSame(LegalityPreFilter::CAP, LegalityPreFilter::reason($s, $c));
        self::assertFalse(LegalityPreFilter::passes($s, $c));
    }

    public function testPicksMoveNoSalaryAndNoRosterSpot(): void
    {
        $s = self::league([...array_fill(0, 13, 500), 250, 250], array_fill(0, 10, 100));
        self::assertSame(7000, $s->payrollByOffset[0][1]);
        self::assertCount(15, $s->lineups[0][1]->players);
        $c = new TradeCandidate(1, 2, [], [], [], [21]);

        self::assertNull(LegalityPreFilter::reason($s, $c));
    }

    public function testPidNotOnTeamRefused(): void
    {
        $s = self::league(array_fill(0, 10, 100), array_fill(0, 10, 100));
        $c = new TradeCandidate(1, 2, [205], [], [201], []);

        self::assertSame(LegalityPreFilter::OWNERSHIP, LegalityPreFilter::reason($s, $c));
    }

    public function testPickNotOwnedBySideRefused(): void
    {
        $s = self::league(array_fill(0, 10, 100), array_fill(0, 10, 100));
        $c = new TradeCandidate(1, 2, [], [21], [201], []);

        self::assertSame(LegalityPreFilter::OWNERSHIP, LegalityPreFilter::reason($s, $c));
        self::assertFalse(LegalityPreFilter::passes($s, $c));
    }

    public function testInjuredPlayerCountsTowardFifteen(): void
    {
        $s = self::league(array_fill(0, 15, 100), array_fill(0, 10, 100), injuredPid: 115);
        self::assertGreaterThan(0, $s->players[115]->injuredDays);
        $c = new TradeCandidate(1, 2, [], [], [201], []);

        self::assertSame(LegalityPreFilter::ROSTER, LegalityPreFilter::reason($s, $c));
    }

    public function testCapUsesCurrentSeasonPayrollNotNextSeasons(): void
    {
        $s = self::league([...array_fill(0, 12, 500), 100], [1001, ...array_fill(0, 10, 100)], expiring: true);
        self::assertSame(6100, $s->payrollByOffset[0][1]);
        self::assertSame(0, $s->payrollByOffset[1][1]);
        $c = new TradeCandidate(1, 2, [113], [], [201], []);

        self::assertSame(LegalityPreFilter::CAP, LegalityPreFilter::reason($s, $c));
    }

    public function testSideReceivingSeveralPlayersCountsAllTheirSalaries(): void
    {
        $s = self::league([...array_fill(0, 12, 500), 100], [500, 501, ...array_fill(0, 9, 100)]);
        $c = new TradeCandidate(1, 2, [113], [], [201, 202], []);

        self::assertSame(LegalityPreFilter::CAP, LegalityPreFilter::reason($s, $c));
    }

    public function testTeamAbsentFromPayrollTablesStartsFromZeroPayroll(): void
    {
        $s = self::league(array_fill(0, 10, 100), [7001, 100]);
        $c = new TradeCandidate(99, 2, [], [], [201], []);

        self::assertSame(LegalityPreFilter::CAP, LegalityPreFilter::reason($s, $c));
    }

    public function testReceivingWithNothingSentLandsExactlyOnTheCapAndPasses(): void
    {
        $s = self::league(array_fill(0, 13, 500), [500, ...array_fill(0, 9, 100)]);
        self::assertSame(6500, $s->payrollByOffset[0][1]);
        $c = new TradeCandidate(1, 2, [], [], [201], []);

        self::assertNull(LegalityPreFilter::reason($s, $c));
    }

    public function testTeamAbsentFromPayrollTablesReceivingExactlyTheCapPasses(): void
    {
        $s = self::league(array_fill(0, 10, 100), [7000, 100]);
        $c = new TradeCandidate(99, 2, [], [], [201], []);

        self::assertNull(LegalityPreFilter::reason($s, $c));
    }

    public function testRosterIsReadFromTheOffsetZeroLineupOnly(): void
    {
        $s = self::league(array_fill(0, 10, 100), array_fill(0, 10, 100));
        $lineups = $s->lineups;
        unset($lineups[1][1]);
        $s = new LeagueSnapshot(...[...get_object_vars($s), 'lineups' => $lineups]);
        self::assertArrayHasKey(1, $s->lineups[0]);
        $c = new TradeCandidate(1, 2, [101], [], [201], []);

        self::assertNull(LegalityPreFilter::reason($s, $c));
    }

    /**
     * Two teams with the given current-season salaries, one player each.
     *
     * @param list<int> $salariesA
     * @param list<int> $salariesB
     */
    private static function league(array $salariesA, array $salariesB, int $injuredPid = 0, bool $expiring = false): LeagueSnapshot
    {
        return LeagueSnapshotBuilder::build(SnapshotFactory::inputs(2, 1, [
            1 => self::roster(1, $salariesA, $injuredPid, $expiring),
            2 => self::roster(2, $salariesB, $injuredPid, $expiring),
        ]));
    }

    /**
     * @param list<int> $salaries
     * @return list<ProjectionPlayer>
     */
    private static function roster(int $tid, array $salaries, int $injuredPid, bool $expiring = false): array
    {
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];
        $roster = [];
        foreach ($salaries as $i => $salary) {
            $pid = $tid * 100 + $i + 1;
            $roster[] = ProjectionFactory::player(
                pid: $pid,
                tid: $tid,
                pos: $positions[$i % 5],
                salaryByYear: [1 => $salary, 2 => $expiring ? 0 : $salary, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
                injuredDays: $pid === $injuredPid ? 60 : 0,
                dcMinutes: $i < 10 ? 20 : 0,
            );
        }

        return $roster;
    }
}
