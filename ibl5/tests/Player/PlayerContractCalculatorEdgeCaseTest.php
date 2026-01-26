<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Player\PlayerContractCalculator;
use Player\PlayerData;

/**
 * Edge case tests for PlayerContractCalculator
 *
 * Tests boundary conditions, unusual states, and edge cases for contract calculations.
 *
 * @covers \Player\PlayerContractCalculator
 */
class PlayerContractCalculatorEdgeCaseTest extends TestCase
{
    private PlayerContractCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PlayerContractCalculator();
    }

    // ============================================
    // CURRENT SEASON SALARY EDGE CASES
    // ============================================

    public function testGetCurrentSeasonSalaryWithNullYear1Salary(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        // contractYear1Salary is null by default

        $result = $this->calculator->getCurrentSeasonSalary($playerData);

        $this->assertEquals(0, $result);
    }

    public function testGetCurrentSeasonSalaryForYear6(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 6;
        $playerData->contractYear6Salary = 5000;

        $result = $this->calculator->getCurrentSeasonSalary($playerData);

        $this->assertEquals(5000, $result);
    }

    public function testGetCurrentSeasonSalaryForYear8(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 8;

        $result = $this->calculator->getCurrentSeasonSalary($playerData);

        $this->assertEquals(0, $result);
    }

    public function testGetCurrentSeasonSalaryForYear100(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 100;

        $result = $this->calculator->getCurrentSeasonSalary($playerData);

        $this->assertEquals(0, $result);
    }

    // ============================================
    // NEXT SEASON SALARY EDGE CASES
    // ============================================

    public function testGetNextSeasonSalaryFromYear6ReturnsZero(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 6;
        $playerData->contractYear6Salary = 5000;

        $result = $this->calculator->getNextSeasonSalary($playerData);

        // Year 6 + 1 = Year 7, which returns 0
        $this->assertEquals(0, $result);
    }

    public function testGetNextSeasonSalaryFromYear0(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 0;
        $playerData->contractYear1Salary = 1000;

        $result = $this->calculator->getNextSeasonSalary($playerData);

        // Year 0 + 1 = Year 1
        $this->assertEquals(1000, $result);
    }

    public function testGetNextSeasonSalaryFromYear5(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 5;
        $playerData->contractYear5Salary = 4000;
        $playerData->contractYear6Salary = 5000;

        $result = $this->calculator->getNextSeasonSalary($playerData);

        $this->assertEquals(5000, $result);
    }

    // ============================================
    // GET FUTURE SALARIES EDGE CASES
    // ============================================

    public function testGetFutureSalariesFromYear0(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 0;
        $playerData->contractYear1Salary = 1000;
        $playerData->contractYear2Salary = 1100;
        $playerData->contractYear3Salary = 1200;
        $playerData->contractYear4Salary = 0;
        $playerData->contractYear5Salary = 0;
        $playerData->contractYear6Salary = 0;

        $result = $this->calculator->getFutureSalaries($playerData);

        // Offset 0, all 6 years included
        $this->assertEquals([1000, 1100, 1200, 0, 0, 0], $result);
    }

    public function testGetFutureSalariesFromYear5(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 5;
        $playerData->contractYear5Salary = 4500;
        $playerData->contractYear6Salary = 5000;

        $result = $this->calculator->getFutureSalaries($playerData);

        // Offset 5, only years 5-6 plus padding
        $this->assertCount(6, $result);
        $this->assertEquals(5000, $result[0]); // Year 6
        $this->assertEquals(0, $result[1]); // Padding
    }

    public function testGetFutureSalariesFromYear6(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 6;
        $playerData->contractYear6Salary = 5000;

        $result = $this->calculator->getFutureSalaries($playerData);

        // Offset 6, all padding zeros
        $this->assertEquals([0, 0, 0, 0, 0, 0], $result);
    }

    public function testGetFutureSalariesFromYear7ReturnsAllZeros(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 7;

        $result = $this->calculator->getFutureSalaries($playerData);

        // Beyond contract length, all zeros
        $this->assertEquals([0, 0, 0, 0, 0, 0], $result);
    }

    public function testGetFutureSalariesWithAllNullValues(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        // All salary fields are null

        $result = $this->calculator->getFutureSalaries($playerData);

        // Null values become null in array (not 0)
        $this->assertCount(6, $result);
    }

    // ============================================
    // REMAINING CONTRACT ARRAY EDGE CASES
    // ============================================

    public function testGetRemainingContractArraySingleYearContract(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 1;
        $playerData->contractYear1Salary = 1500;

        $result = $this->calculator->getRemainingContractArray($playerData);

        $this->assertEquals([1 => 1500], $result);
    }

    public function testGetRemainingContractArrayMaxSixYearContract(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 6;
        $playerData->contractYear1Salary = 1000;
        $playerData->contractYear2Salary = 1100;
        $playerData->contractYear3Salary = 1200;
        $playerData->contractYear4Salary = 1300;
        $playerData->contractYear5Salary = 1400;
        $playerData->contractYear6Salary = 1500;

        $result = $this->calculator->getRemainingContractArray($playerData);

        $this->assertCount(6, $result);
        $this->assertEquals(1000, $result[1]);
        $this->assertEquals(1500, $result[6]);
    }

    public function testGetRemainingContractArrayCurrentYearGreaterThanTotalYears(): void
    {
        // This is technically an invalid state but should be handled gracefully
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 5;
        $playerData->contractTotalYears = 3;
        $playerData->contractYear5Salary = 1000; // Won't be included

        $result = $this->calculator->getRemainingContractArray($playerData);

        // Loop starts at 5, ends at 3 - no iterations, returns default
        $this->assertEquals([1 => 0], $result);
    }

    public function testGetRemainingContractArrayWithSparseZeroSalaries(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 4;
        $playerData->contractYear1Salary = 1000;
        $playerData->contractYear2Salary = 0;     // Sparse: zero salary
        $playerData->contractYear3Salary = 1200;
        $playerData->contractYear4Salary = 0;     // Sparse: zero salary

        $result = $this->calculator->getRemainingContractArray($playerData);

        // Zero salaries are skipped
        $this->assertEquals([1 => 1000, 3 => 1200], $result);
    }

    public function testGetRemainingContractArrayLastYearOfContract(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 4;
        $playerData->contractTotalYears = 4;
        $playerData->contractYear4Salary = 2000;

        $result = $this->calculator->getRemainingContractArray($playerData);

        $this->assertEquals([1 => 2000], $result);
    }

    // ============================================
    // TOTAL REMAINING SALARY EDGE CASES
    // ============================================

    public function testGetTotalRemainingSalaryWithZeroSalaries(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 3;
        // All salaries default to null/0

        $result = $this->calculator->getTotalRemainingSalary($playerData);

        $this->assertEquals(0, $result);
    }

    public function testGetTotalRemainingSalaryWithLargeValues(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 3;
        $playerData->contractYear1Salary = 7000; // Max salary
        $playerData->contractYear2Salary = 7000;
        $playerData->contractYear3Salary = 7000;

        $result = $this->calculator->getTotalRemainingSalary($playerData);

        $this->assertEquals(21000, $result);
    }

    // ============================================
    // BUYOUT ARRAY EDGE CASES
    // ============================================

    public function testGetLongBuyoutArrayWithZeroSalary(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 1;
        // Zero salary

        $result = $this->calculator->getLongBuyoutArray($playerData);

        // 0 / 6 = 0 per year
        $this->assertEquals([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0], $result);
    }

    public function testGetShortBuyoutArrayWithZeroSalary(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 1;
        // Zero salary

        $result = $this->calculator->getShortBuyoutArray($playerData);

        // 0 / 2 = 0 per year
        $this->assertEquals([1 => 0, 2 => 0], $result);
    }

    public function testGetLongBuyoutArrayWithNonDivisibleSalary(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 1;
        $playerData->contractYear1Salary = 1000; // 1000 / 6 = 166.67, rounds to 167

        $result = $this->calculator->getLongBuyoutArray($playerData);

        // Each year should get rounded value
        $expectedPerYear = (int) round(1000 / 6); // 167
        $this->assertEquals($expectedPerYear, $result[1]);
        $this->assertCount(6, $result);
    }

    public function testGetShortBuyoutArrayWithOddSalary(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 1;
        $playerData->contractYear1Salary = 1001; // 1001 / 2 = 500.5, rounds to 501

        $result = $this->calculator->getShortBuyoutArray($playerData);

        $expectedPerYear = (int) round(1001 / 2); // 501
        $this->assertEquals([1 => $expectedPerYear, 2 => $expectedPerYear], $result);
    }

    public function testGetLongBuyoutArrayWithVerySmallSalary(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 1;
        $playerData->contractYear1Salary = 1; // 1 / 6 = 0.167, rounds to 0

        $result = $this->calculator->getLongBuyoutArray($playerData);

        // Rounds to 0
        $this->assertEquals([1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0], $result);
    }

    public function testGetShortBuyoutArrayWithMinimumSalary(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 1;
        $playerData->contractYear1Salary = 1; // 1 / 2 = 0.5, rounds to 1

        $result = $this->calculator->getShortBuyoutArray($playerData);

        $expectedPerYear = (int) round(1 / 2); // 1
        $this->assertEquals([1 => $expectedPerYear, 2 => $expectedPerYear], $result);
    }

    public function testGetLongBuyoutArrayWithMaxContractValue(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 6;
        $playerData->contractYear1Salary = 7000;
        $playerData->contractYear2Salary = 7000;
        $playerData->contractYear3Salary = 7000;
        $playerData->contractYear4Salary = 7000;
        $playerData->contractYear5Salary = 7000;
        $playerData->contractYear6Salary = 7000;

        $result = $this->calculator->getLongBuyoutArray($playerData);

        // 42000 / 6 = 7000 per year
        $this->assertEquals([1 => 7000, 2 => 7000, 3 => 7000, 4 => 7000, 5 => 7000, 6 => 7000], $result);
    }

    // ============================================
    // YEAR BOUNDARY DATA PROVIDER TESTS
    // ============================================

    #[DataProvider('yearBoundaryProvider')]
    public function testYearBoundaryBehavior(int $year, int $expectedSalary): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = $year;
        $playerData->contractYear1Salary = 1000;
        $playerData->contractYear2Salary = 2000;
        $playerData->contractYear3Salary = 3000;
        $playerData->contractYear4Salary = 4000;
        $playerData->contractYear5Salary = 5000;
        $playerData->contractYear6Salary = 6000;

        $result = $this->calculator->getCurrentSeasonSalary($playerData);

        $this->assertEquals($expectedSalary, $result);
    }

    public static function yearBoundaryProvider(): array
    {
        return [
            'year 0 defaults to year 1' => [0, 1000],
            'year 1' => [1, 1000],
            'year 2' => [2, 2000],
            'year 3' => [3, 3000],
            'year 4' => [4, 4000],
            'year 5' => [5, 5000],
            'year 6' => [6, 6000],
            'year 7 returns 0' => [7, 0],
            'year 8 returns 0' => [8, 0],
            'year 10 returns 0' => [10, 0],
        ];
    }

    // ============================================
    // NULL PROPERTY HANDLING TESTS
    // ============================================

    public function testCalculatorHandlesAllNullProperties(): void
    {
        $playerData = new PlayerData();
        $playerData->contractCurrentYear = 1;
        $playerData->contractTotalYears = 1;
        // All other properties are null

        $currentSalary = $this->calculator->getCurrentSeasonSalary($playerData);
        $nextSalary = $this->calculator->getNextSeasonSalary($playerData);
        $remainingArray = $this->calculator->getRemainingContractArray($playerData);
        $totalRemaining = $this->calculator->getTotalRemainingSalary($playerData);
        $longBuyout = $this->calculator->getLongBuyoutArray($playerData);
        $shortBuyout = $this->calculator->getShortBuyoutArray($playerData);

        $this->assertEquals(0, $currentSalary);
        $this->assertEquals(0, $nextSalary);
        $this->assertEquals([1 => 0], $remainingArray);
        $this->assertEquals(0, $totalRemaining);
        $this->assertCount(6, $longBuyout);
        $this->assertCount(2, $shortBuyout);
    }
}
