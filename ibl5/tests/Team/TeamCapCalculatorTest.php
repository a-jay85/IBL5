<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use League\League;
use Season\Season;
use Team\Contracts\TeamQueryRepositoryInterface;
use Team\Team;
use Team\TeamCapCalculator;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;

/**
 * Unit tests for {@see TeamCapCalculator}.
 *
 * The cap-decision and salary-aggregation logic moved here out of
 * TeamQueryRepository. These tests cover the boundary verdicts (at / over /
 * under the hard cap and buyout limit), the per-year salary-cap walk over
 * contracts and cash considerations, and the season-phase rollover — all with
 * controlled doubles so the boundaries are exact rather than seed-dependent.
 *
 * @covers \Team\TeamCapCalculator
 */
class TeamCapCalculatorTest extends TestCase
{
    private MockDatabase $mockDb;

    /** @var TeamQueryRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private TeamQueryRepositoryInterface $stubRepo;

    /** @var BuyoutLedgerRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private BuyoutLedgerRepositoryInterface $stubCash;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->stubRepo = self::createStub(TeamQueryRepositoryInterface::class);
        $this->stubCash = self::createStub(BuyoutLedgerRepositoryInterface::class);
    }

    private function buildCalculator(bool $advancesContractYears = false): TeamCapCalculator
    {
        return new TeamCapCalculator($this->mockDb, $this->stubRepo, $this->stubCash, $this->capPhaseSeason($advancesContractYears));
    }

    private function season(bool $offseason): Season
    {
        $season = self::createStub(Season::class);
        $season->method('isOffseasonPhase')->willReturn($offseason);
        return $season;
    }

    private function capPhaseSeason(bool $advances): Season
    {
        $season = self::createStub(Season::class);
        $season->method('advancesContractYears')->willReturn($advances);
        return $season;
    }

    // ── canAddContractWithoutGoingOverHardCap (boundary) ──────────

    public function testCanAddContractRejectsOverHardCap(): void
    {
        // Empty roster → total committed salary is 0; adding HARD_CAP_MAX + 1
        // projects to HARD_CAP_MAX + 1, just over the cap.
        $this->stubRepo->method('getRosterUnderContractOrderedByName')->willReturn([]);

        self::assertFalse(
            $this->buildCalculator()->canAddContractWithoutGoingOverHardCap(1, League::HARD_CAP_MAX + 1)
        );
    }

    public function testCanAddContractAllowsExactlyAtHardCap(): void
    {
        $this->stubRepo->method('getRosterUnderContractOrderedByName')->willReturn([]);

        // Projected total == HARD_CAP_MAX is inclusive (<=), so this is allowed.
        self::assertTrue(
            $this->buildCalculator()->canAddContractWithoutGoingOverHardCap(1, League::HARD_CAP_MAX)
        );
    }

    public function testCanAddContractAllowsUnderHardCap(): void
    {
        $this->stubRepo->method('getRosterUnderContractOrderedByName')->willReturn([]);

        self::assertTrue(
            $this->buildCalculator()->canAddContractWithoutGoingOverHardCap(1, League::HARD_CAP_MAX - 1)
        );
    }

    public function testCanAddContractCountsExistingCommittedSalary(): void
    {
        // One rostered player committing salary_yr1 = 500 (TestDataFactory default).
        // Adding HARD_CAP_MAX - 500 lands exactly at the cap (allowed); one more
        // dollar goes over.
        $this->stubRepo->method('getRosterUnderContractOrderedByName')
            ->willReturn([TestDataFactory::createPlayer(['pid' => 1, 'cy' => 1, 'salary_yr1' => 500])]);

        $calculator = $this->buildCalculator();

        self::assertTrue($calculator->canAddContractWithoutGoingOverHardCap(1, League::HARD_CAP_MAX - 500));
        self::assertFalse($calculator->canAddContractWithoutGoingOverHardCap(1, League::HARD_CAP_MAX - 499));
    }

    // ── canAddBuyoutWithoutExceedingBuyoutLimit (boundary) ────────

    public function testCanAddBuyoutRejectsOverLimit(): void
    {
        // No existing buyouts → current buyout total is 0. Limit is
        // HARD_CAP_MAX * BUYOUT_PERCENTAGE_MAX.
        $this->stubRepo->method('getBuyouts')->willReturn([]);
        $limit = (int) (League::HARD_CAP_MAX * Team::BUYOUT_PERCENTAGE_MAX);

        self::assertFalse(
            $this->buildCalculator()->canAddBuyoutWithoutExceedingBuyoutLimit(1, $limit + 1, $this->season(false))
        );
    }

    public function testCanAddBuyoutAllowsExactlyAtLimit(): void
    {
        $this->stubRepo->method('getBuyouts')->willReturn([]);
        $limit = (int) (League::HARD_CAP_MAX * Team::BUYOUT_PERCENTAGE_MAX);

        self::assertTrue(
            $this->buildCalculator()->canAddBuyoutWithoutExceedingBuyoutLimit(1, $limit, $this->season(false))
        );
    }

    public function testCanAddBuyoutAllowsUnderLimit(): void
    {
        $this->stubRepo->method('getBuyouts')->willReturn([]);
        $limit = (int) (League::HARD_CAP_MAX * Team::BUYOUT_PERCENTAGE_MAX);

        self::assertTrue(
            $this->buildCalculator()->canAddBuyoutWithoutExceedingBuyoutLimit(1, $limit - 1, $this->season(false))
        );
    }

    // ── getSalaryCapArray (per-year walk) ─────────────────────────

    public function testGetSalaryCapArrayAccumulatesContractSalariesByYear(): void
    {
        $this->stubRepo->method('getRosterUnderContractOrderedByName')->willReturn([
            ['cy' => 1, 'cyt' => 3, 'salary_yr1' => 1000, 'salary_yr2' => 1100, 'salary_yr3' => 1200],
        ]);
        $this->stubCash->method('getTeamCashForSalary')->willReturn([]);

        $result = $this->buildCalculator()->getSalaryCapArray('Test', 1, $this->season(false));

        self::assertSame(['year1' => 1000, 'year2' => 1100, 'year3' => 1200], $result);
    }

    public function testGetSalaryCapArrayAdvancesContractYearInOffseason(): void
    {
        // Same contract, but in the offseason the contract year has rolled over,
        // so year1 should reflect salary_yr2 and year2 salary_yr3.
        $this->stubRepo->method('getRosterUnderContractOrderedByName')->willReturn([
            ['cy' => 1, 'cyt' => 3, 'salary_yr1' => 1000, 'salary_yr2' => 1100, 'salary_yr3' => 1200],
        ]);
        $this->stubCash->method('getTeamCashForSalary')->willReturn([]);

        $result = $this->buildCalculator()->getSalaryCapArray('Test', 1, $this->season(true));

        self::assertSame(['year1' => 1100, 'year2' => 1200], $result);
    }

    public function testGetSalaryCapArraySumsMultiplePlayersIntoSameYear(): void
    {
        $this->stubRepo->method('getRosterUnderContractOrderedByName')->willReturn([
            ['cy' => 1, 'cyt' => 1, 'salary_yr1' => 1000],
            ['cy' => 1, 'cyt' => 1, 'salary_yr1' => 250],
        ]);
        $this->stubCash->method('getTeamCashForSalary')->willReturn([]);

        $result = $this->buildCalculator()->getSalaryCapArray('Test', 1, $this->season(false));

        self::assertSame(['year1' => 1250], $result);
    }

    public function testGetSalaryCapArrayAddsCashConsiderations(): void
    {
        $this->stubRepo->method('getRosterUnderContractOrderedByName')->willReturn([]);
        // Cash row starting at contract year 5 contributes salary_yr5 to year1
        // and salary_yr6 to year2 (walks years 5 and 6 into slots 1 and 2).
        $this->stubCash->method('getTeamCashForSalary')->willReturn([
            ['cy' => 5, 'salary_yr5' => 300, 'salary_yr6' => 400],
        ]);

        $result = $this->buildCalculator()->getSalaryCapArray('Test', 1, $this->season(false));

        self::assertSame(['year1' => 300, 'year2' => 400], $result);
    }

    public function testGetSalaryCapArrayCombinesContractsAndCash(): void
    {
        $this->stubRepo->method('getRosterUnderContractOrderedByName')->willReturn([
            ['cy' => 1, 'cyt' => 2, 'salary_yr1' => 1000, 'salary_yr2' => 1100],
        ]);
        $this->stubCash->method('getTeamCashForSalary')->willReturn([
            ['cy' => 1, 'salary_yr1' => 50, 'salary_yr2' => 60],
        ]);

        $result = $this->buildCalculator()->getSalaryCapArray('Test', 1, $this->season(false));

        // The cash walk advances through all six contract years, so years 3-6
        // are materialized as 0 even though the cash row only funds years 1-2.
        self::assertSame(
            ['year1' => 1050, 'year2' => 1160, 'year3' => 0, 'year4' => 0, 'year5' => 0, 'year6' => 0],
            $result
        );
    }

    public function testGetSalaryCapArrayReturnsEmptyForNoCommitments(): void
    {
        $this->stubRepo->method('getRosterUnderContractOrderedByName')->willReturn([]);
        $this->stubCash->method('getTeamCashForSalary')->willReturn([]);

        self::assertSame([], $this->buildCalculator()->getSalaryCapArray('Test', 1, $this->season(false)));
    }

    // ── getTotalCurrentSeasonSalaries / getTotalNextSeasonSalaries ─

    public function testGetTotalCurrentSeasonSalariesSumsCurrentYearAcrossPlayers(): void
    {
        // cy = 1 → current-season salary is salary_yr1 for each player.
        $rows = [
            TestDataFactory::createPlayer(['pid' => 1, 'cy' => 1, 'salary_yr1' => 500]),
            TestDataFactory::createPlayer(['pid' => 2, 'cy' => 1, 'salary_yr1' => 1000]),
        ];

        self::assertSame(1500, $this->buildCalculator()->getTotalCurrentSeasonSalaries($rows));
    }

    public function testGetTotalCurrentSeasonSalariesIsZeroForEmptyRoster(): void
    {
        self::assertSame(0, $this->buildCalculator()->getTotalCurrentSeasonSalaries([]));
    }

    public function testGetTotalNextSeasonSalariesSumsNextYearAcrossPlayers(): void
    {
        // cy = 1 → next-season salary is salary_yr2 for each player.
        $rows = [
            TestDataFactory::createPlayer(['pid' => 1, 'cy' => 1, 'salary_yr2' => 550]),
            TestDataFactory::createPlayer(['pid' => 2, 'cy' => 1, 'salary_yr2' => 1200]),
        ];

        self::assertSame(1750, $this->buildCalculator()->getTotalNextSeasonSalaries($rows));
    }

    public function testGetTotalNextSeasonSalariesIsZeroForEmptyRoster(): void
    {
        self::assertSame(0, $this->buildCalculator()->getTotalNextSeasonSalaries([]));
    }

    // ── Phase-aware aggregates (advances=true) ─────────────────────

    public function testGetTotalCurrentSeasonSalariesUsesPhaseAwareSalaryWhenAdvancing(): void
    {
        // advances=true: cy=1 → current basis shifts to yr2.
        $rows = [
            TestDataFactory::createPlayer(['pid' => 1, 'cy' => 1, 'salary_yr2' => 800]),
            TestDataFactory::createPlayer(['pid' => 2, 'cy' => 1, 'salary_yr2' => 1200]),
        ];

        self::assertSame(2000, $this->buildCalculator(true)->getTotalCurrentSeasonSalaries($rows));
    }

    public function testGetTotalNextSeasonSalariesUsesPhaseAwareSalaryWhenAdvancing(): void
    {
        // advances=true: cy=1 → next basis shifts to yr3.
        $rows = [
            TestDataFactory::createPlayer(['pid' => 1, 'cy' => 1, 'salary_yr3' => 900]),
            TestDataFactory::createPlayer(['pid' => 2, 'cy' => 1, 'salary_yr3' => 1100]),
        ];

        self::assertSame(2000, $this->buildCalculator(true)->getTotalNextSeasonSalaries($rows));
    }

    public function testSalaryAggregatesStayOneYearApartInOffseason(): void
    {
        // advances=true, cy=1: current=yr2=900, next=yr3=1200.
        $rows = [
            TestDataFactory::createPlayer(['pid' => 1, 'cy' => 1, 'salary_yr2' => 900, 'salary_yr3' => 1200]),
        ];
        $calculator = $this->buildCalculator(true);

        $current = $calculator->getTotalCurrentSeasonSalaries($rows);
        $next = $calculator->getTotalNextSeasonSalaries($rows);

        self::assertSame(900, $current);
        self::assertSame(1200, $next);
        self::assertGreaterThan($current, $next);
    }

    public function testCanAddContractHardCapVerdictFlipsBetweenPhases(): void
    {
        // cy=1, salary_yr1=500 (in-season basis), salary_yr2=900 (advancing basis).
        // contractValue = HARD_CAP_MAX - 700, so:
        //   in-season:  500 + (HARD_CAP_MAX - 700) = HARD_CAP_MAX - 200 → fits
        //   advancing:  900 + (HARD_CAP_MAX - 700) = HARD_CAP_MAX + 200 → over
        $this->stubRepo->method('getRosterUnderContractOrderedByName')
            ->willReturn([TestDataFactory::createPlayer(['pid' => 1, 'cy' => 1, 'salary_yr1' => 500, 'salary_yr2' => 900])]);

        $contractValue = League::HARD_CAP_MAX - 700;

        self::assertTrue($this->buildCalculator(false)->canAddContractWithoutGoingOverHardCap(1, $contractValue));
        self::assertFalse($this->buildCalculator(true)->canAddContractWithoutGoingOverHardCap(1, $contractValue));
    }

    public function testInjectedSeasonPreventsInternalSeasonConstruction(): void
    {
        // When a Season is injected, TeamCapCalculator must NOT construct a new Season
        // internally (which would fire a DB settings query). Asserting the DB log stays
        // empty proves no internal construction happened.
        $rows = [TestDataFactory::createPlayer(['pid' => 1, 'cy' => 1, 'salary_yr1' => 500])];
        $calculator = $this->buildCalculator(true);

        $calculator->getTotalCurrentSeasonSalaries($rows);
        $calculator->getTotalNextSeasonSalaries($rows);

        self::assertSame([], $this->mockDb->getExecutedQueries());
    }

    public function testTotalCurrentSeasonSalariesIsZeroForEmptyRosterInBothPhases(): void
    {
        self::assertSame(0, $this->buildCalculator(false)->getTotalCurrentSeasonSalaries([]));
        self::assertSame(0, $this->buildCalculator(true)->getTotalCurrentSeasonSalaries([]));
    }
}
