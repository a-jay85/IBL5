<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\TestCase;
use Season\Season;
use Trading\FutureSalaryCalculator;
use Tests\WideUnit\Mocks\MockDatabase;

class FutureSalaryCalculatorTest extends TestCase
{
    private MockDatabase $mockDb;
    private FutureSalaryCalculator $calculator;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->calculator = new FutureSalaryCalculator();
    }

    public function testReturnsEmptyTotalsForNoPlayers(): void
    {
        $season = $this->createSeasonStub('Regular Season');

        $result = $this->calculator->calculateFutureSalaries([], $season);

        $this->assertSame([0, 0, 0, 0, 0, 0], $result['player']);
        $this->assertSame([0, 0, 0, 0, 0, 0], $result['hold']);
    }

    public function testSumsTwoPlayerSalaries(): void
    {
        $season = $this->createSeasonStub('Regular Season');

        $players = [
            $this->createPlayerRow(cy: 1, salaryYr1: 500, salaryYr2: 0),
            $this->createPlayerRow(cy: 1, salaryYr1: 300, salaryYr2: 0),
        ];

        $result = $this->calculator->calculateFutureSalaries($players, $season);

        $this->assertSame(800, $result['player'][0]);
    }

    public function testCountsHoldsCorrectly(): void
    {
        $season = $this->createSeasonStub('Regular Season');

        $players = [
            $this->createPlayerRow(cy: 1, salaryYr1: 500, salaryYr2: 600),
            $this->createPlayerRow(cy: 1, salaryYr1: 300, salaryYr2: 0),
        ];

        $result = $this->calculator->calculateFutureSalaries($players, $season);

        $this->assertSame(2, $result['hold'][0]);
        $this->assertSame(1, $result['hold'][1]);
        $this->assertSame(0, $result['hold'][2]);
    }

    public function testAdvancesContractYearInPlayoffs(): void
    {
        $season = $this->createSeasonStub('Playoffs');

        $players = [
            $this->createPlayerRow(cy: 1, salaryYr1: 500, salaryYr2: 600),
        ];

        $result = $this->calculator->calculateFutureSalaries($players, $season);

        // Playoffs advances cy: cy=1 → cy=2, reads from salary_yr2
        $this->assertSame(600, $result['player'][0]);
        $this->assertSame(0, $result['player'][1]);
    }

    public function testZeroContractYearClampedToOne(): void
    {
        $season = $this->createSeasonStub('Regular Season');

        $players = [
            $this->createPlayerRow(cy: 0, salaryYr1: 500, salaryYr2: 600),
        ];

        $result = $this->calculator->calculateFutureSalaries($players, $season);

        // cy=0 gets clamped to cy=1
        $this->assertSame(500, $result['player'][0]);
        $this->assertSame(600, $result['player'][1]);
    }

    public function testContractYearAboveSixContributesNothing(): void
    {
        $season = $this->createSeasonStub('Playoffs');

        // cy=6 + Playoffs advance → cy=7 — all salary slots exhausted
        $players = [
            $this->createPlayerRow(cy: 6, salaryYr1: 100, salaryYr2: 200, salaryYr3: 300, salaryYr4: 400, salaryYr5: 500, salaryYr6: 600),
        ];

        $result = $this->calculator->calculateFutureSalaries($players, $season);

        $this->assertSame([0, 0, 0, 0, 0, 0], $result['player']);
        $this->assertSame([0, 0, 0, 0, 0, 0], $result['hold']);
    }

    public function testNonIntCyAndSalaryFieldsAreIgnored(): void
    {
        $season = $this->createSeasonStub('Regular Season');

        $players = [
            [
                'pos' => 'PG',
                'name' => 'Test Player',
                'pid' => 1,
                'ordinal' => 5,
                'cy' => 'bad',
                'salary_yr1' => 'not-an-int',
                'salary_yr2' => 500,
                'salary_yr3' => 0,
                'salary_yr4' => 0,
                'salary_yr5' => 0,
                'salary_yr6' => 0,
            ],
        ];

        $result = $this->calculator->calculateFutureSalaries($players, $season);

        // Non-int cy → treated as 0 → clamped to 1; non-int salary_yr1 → 0; salary_yr2 contributes
        $this->assertSame(0, $result['player'][0]);
        $this->assertSame(500, $result['player'][1]);
    }

    private function createSeasonStub(string $phase): Season
    {
        // Use the real mock Season (not a PHPUnit stub) so its phase-derived
        // methods — e.g. advancesContractYears() — return correct values for the
        // configured phase without re-encoding the phase set in test config.
        $season = new \Tests\WideUnit\Mocks\Season($this->mockDb);
        $season->phase = $phase;
        $season->endingYear = 2025;
        $season->beginningYear = 2024;
        return $season;
    }

    /**
     * @return array{pos: string, name: string, pid: int, ordinal: int, cy: int, salary_yr1: int, salary_yr2: int, salary_yr3: int, salary_yr4: int, salary_yr5: int, salary_yr6: int}
     */
    private function createPlayerRow(
        int $cy = 1,
        int $salaryYr1 = 0,
        int $salaryYr2 = 0,
        int $salaryYr3 = 0,
        int $salaryYr4 = 0,
        int $salaryYr5 = 0,
        int $salaryYr6 = 0
    ): array {
        return [
            'pos' => 'PG',
            'name' => 'Test Player',
            'pid' => 1,
            'ordinal' => 5,
            'cy' => $cy,
            'salary_yr1' => $salaryYr1,
            'salary_yr2' => $salaryYr2,
            'salary_yr3' => $salaryYr3,
            'salary_yr4' => $salaryYr4,
            'salary_yr5' => $salaryYr5,
            'salary_yr6' => $salaryYr6,
        ];
    }
}
