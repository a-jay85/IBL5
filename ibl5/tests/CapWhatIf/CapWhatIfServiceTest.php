<?php

declare(strict_types=1);

namespace Tests\CapWhatIf;

use CapWhatIf\CapWhatIfService;
use League\League;
use PHPUnit\Framework\TestCase;
use Season\Season;
use Team\Contracts\TeamQueryRepositoryInterface;
use Team\TeamCapCalculator;
use Tests\WideUnit\Mocks\MockDatabase;
use Trading\Contracts\BuyoutLedgerRepositoryInterface;

/**
 * Unit tests for {@see CapWhatIfService}.
 *
 * The service is wired with the REAL {@see TeamCapCalculator} over a stubbed
 * roster repository (and empty cash), so the baseline/scenario numbers exercise
 * the league's authoritative cap walk rather than a re-stubbed echo. Controlled
 * rows mirror the seeded Metros actives (`ci-seed.sql:205-224`) but are exact
 * and immune to seed size.
 *
 * @covers \CapWhatIf\CapWhatIfService
 */
class CapWhatIfServiceTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    /**
     * Build a service whose roster query returns the given rows, costed by the
     * real cap calculator with empty cash considerations.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function buildService(array $rows): CapWhatIfService
    {
        /** @var TeamQueryRepositoryInterface&\PHPUnit\Framework\MockObject\Stub $stubRepo */
        $stubRepo = self::createStub(TeamQueryRepositoryInterface::class);
        $stubRepo->method('getRosterUnderContractOrderedByName')->willReturn($rows);

        /** @var BuyoutLedgerRepositoryInterface&\PHPUnit\Framework\MockObject\Stub $stubCash */
        $stubCash = self::createStub(BuyoutLedgerRepositoryInterface::class);
        $stubCash->method('getTeamCashForSalary')->willReturn([]);

        $calculator = new TeamCapCalculator($this->mockDb, $stubRepo, $stubCash);

        return new CapWhatIfService($this->mockDb, $stubRepo, $calculator);
    }

    private function season(bool $offseason): Season
    {
        $season = self::createStub(Season::class);
        $season->method('isOffseasonPhase')->willReturn($offseason);
        return $season;
    }

    /**
     * Seeded-Metros-mirroring active roster: pid 1 (yr1=800, yr2=880),
     * pid 2 (yr1=600, yr2=660). year1 total 1400, year2 total 1540.
     *
     * @return list<array<string, mixed>>
     */
    private function metrosRoster(): array
    {
        return [
            ['pid' => 1, 'name' => 'Player One', 'cy' => 1, 'cyt' => 3, 'salary_yr1' => 800, 'salary_yr2' => 880],
            ['pid' => 2, 'name' => 'Player Two', 'cy' => 1, 'cyt' => 2, 'salary_yr1' => 600, 'salary_yr2' => 660],
        ];
    }

    public function testBaselineReproducesRealCap(): void
    {
        $result = $this->buildService($this->metrosRoster())
            ->computeScenario('Metros', 1, $this->season(false), null, 0, 0);

        self::assertSame(1400, $result['baseline']['spent']['year1']);
        self::assertSame(1540, $result['baseline']['spent']['year2']);
        self::assertSame(League::HARD_CAP_MAX - 1400, $result['baseline']['space']['year1']);
        self::assertSame(5600, $result['baseline']['space']['year1']);
    }

    public function testWaiveReducesYearTotalsByThatPlayersSalary(): void
    {
        $service = $this->buildService($this->metrosRoster());

        $waiveOne = $service->computeScenario('Metros', 1, $this->season(false), 1, 0, 0);
        self::assertSame(1400 - 800, $waiveOne['scenario']['spent']['year1']);
        self::assertSame(6400, $waiveOne['scenario']['space']['year1']);
        self::assertSame('Player One', $waiveOne['waivedName']);

        $waiveTwo = $service->computeScenario('Metros', 1, $this->season(false), 2, 0, 0);
        self::assertSame(1400 - 600, $waiveTwo['scenario']['spent']['year1']);
        self::assertSame('Player Two', $waiveTwo['waivedName']);
    }

    public function testAddSigningRaisesEachYearByFlatSalary(): void
    {
        $result = $this->buildService($this->metrosRoster())
            ->computeScenario('Metros', 1, $this->season(false), null, 3, 1000);

        self::assertSame(1400 + 1000, $result['scenario']['spent']['year1']);
        self::assertSame(1540 + 1000, $result['scenario']['spent']['year2']);
        self::assertSame(0 + 1000, $result['scenario']['spent']['year3']);
        self::assertSame(0, $result['scenario']['spent']['year4']);
    }

    public function testCombinedWaiveAndAddComputesNetPerYear(): void
    {
        $result = $this->buildService($this->metrosRoster())
            ->computeScenario('Metros', 1, $this->season(false), 1, 2, 500);

        self::assertSame(1400 - 800 + 500, $result['scenario']['spent']['year1']);
        self::assertSame('Player One', $result['waivedName']);
    }

    public function testSyntheticSigningLandsFirstYearInYear1RegularPhase(): void
    {
        $result = $this->buildService($this->metrosRoster())
            ->computeScenario('Metros', 1, $this->season(false), null, 2, 500);

        $delta = $result['scenario']['spent']['year1'] - $result['baseline']['spent']['year1'];
        self::assertSame(500, $delta);
    }

    public function testSyntheticSigningLandsFirstYearInYear1OffseasonPhase(): void
    {
        $result = $this->buildService($this->metrosRoster())
            ->computeScenario('Metros', 1, $this->season(true), null, 2, 500);

        $delta = $result['scenario']['spent']['year1'] - $result['baseline']['spent']['year1'];
        self::assertSame(500, $delta);
    }

    public function testWaivePidNotOnRosterIsNoOp(): void
    {
        $result = $this->buildService($this->metrosRoster())
            ->computeScenario('Metros', 1, $this->season(false), 999999, 0, 0);

        self::assertSame($result['baseline']['spent'], $result['scenario']['spent']);
        self::assertNull($result['waivedName']);
    }

    public function testZeroYearsOrZeroSalaryAddsNoSigning(): void
    {
        $service = $this->buildService($this->metrosRoster());

        $zeroYears = $service->computeScenario('Metros', 1, $this->season(false), null, 0, 1000);
        self::assertSame($zeroYears['baseline']['spent'], $zeroYears['scenario']['spent']);

        $zeroSalary = $service->computeScenario('Metros', 1, $this->season(false), null, 3, 0);
        self::assertSame($zeroSalary['baseline']['spent'], $zeroSalary['scenario']['spent']);
    }

    public function testYearsClampedToSixNoUndefinedSeventhYear(): void
    {
        // Empty roster isolates the synthetic signing: 6 flat years, no salary_yr7.
        $result = $this->buildService([])
            ->computeScenario('Metros', 1, $this->season(false), null, 99, 500);

        self::assertSame(6, $result['years']);
        self::assertSame(500, $result['scenario']['spent']['year6']);
        self::assertArrayNotHasKey('year7', $result['scenario']['spent']);
    }

    public function testNegativeSalaryClampedToZeroAddsNoSigning(): void
    {
        $result = $this->buildService($this->metrosRoster())
            ->computeScenario('Metros', 1, $this->season(false), null, 3, -5);

        self::assertSame(0, $result['salary']);
        self::assertSame($result['baseline']['spent'], $result['scenario']['spent']);
    }

    public function testOverCapFlaggedWhenYearExceedsHardCap(): void
    {
        $overCapRoster = [
            ['pid' => 1, 'name' => 'Whale', 'cy' => 1, 'cyt' => 1, 'salary_yr1' => League::HARD_CAP_MAX + 1000],
        ];

        $result = $this->buildService($overCapRoster)
            ->computeScenario('Metros', 1, $this->season(false), null, 0, 0);

        self::assertTrue($result['overCap']['year1']);
        self::assertFalse($result['overCap']['year2']);
    }
}
