<?php

declare(strict_types=1);

namespace Tests\Trading;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Trading\BuyoutLedgerRepository;

/**
 * Unit tests for the pure salary-slot helpers centralized in
 * BuyoutLedgerRepository (PR5 contract/cap formula dedup). These operate on
 * plain cash-consideration row arrays and require no database connection.
 */
class BuyoutLedgerRepositoryTest extends TestCase
{
    // ================================================================
    // salaryForContractYear()
    // ================================================================

    /**
     * @return array<string, array{int, int}>
     */
    public static function contractYearProvider(): array
    {
        return [
            'year 1' => [1, 101],
            'year 2' => [2, 102],
            'year 3' => [3, 103],
            'year 4' => [4, 104],
            'year 5' => [5, 105],
            'year 6' => [6, 106],
        ];
    }

    #[DataProvider('contractYearProvider')]
    public function testSalaryForContractYearReturnsMatchingSlot(int $cy, int $expected): void
    {
        $row = [
            'salary_yr1' => 101, 'salary_yr2' => 102, 'salary_yr3' => 103,
            'salary_yr4' => 104, 'salary_yr5' => 105, 'salary_yr6' => 106,
        ];

        $this->assertSame($expected, BuyoutLedgerRepository::salaryForContractYear($row, $cy));
    }

    public function testSalaryForContractYearReturnsZeroForYearZero(): void
    {
        $row = ['salary_yr1' => 101, 'salary_yr2' => 102, 'salary_yr3' => 0,
                'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0];

        $this->assertSame(0, BuyoutLedgerRepository::salaryForContractYear($row, 0));
    }

    public function testSalaryForContractYearReturnsZeroForYearSevenAndBeyond(): void
    {
        $row = ['salary_yr1' => 101, 'salary_yr2' => 102, 'salary_yr3' => 103,
                'salary_yr4' => 104, 'salary_yr5' => 105, 'salary_yr6' => 106];

        $this->assertSame(0, BuyoutLedgerRepository::salaryForContractYear($row, 7));
        $this->assertSame(0, BuyoutLedgerRepository::salaryForContractYear($row, 99));
    }

    public function testSalaryForContractYearTreatsMissingSlotAsZero(): void
    {
        $this->assertSame(0, BuyoutLedgerRepository::salaryForContractYear([], 1));
    }

    public function testSalaryForContractYearCoercesToInt(): void
    {
        $row = ['salary_yr1' => '250'];

        $this->assertSame(250, BuyoutLedgerRepository::salaryForContractYear($row, 1));
    }

    // ================================================================
    // sumCurrentSeasonSalaryFromRows()
    // ================================================================

    public function testSumReturnsZeroForNoRows(): void
    {
        $this->assertSame(0, BuyoutLedgerRepository::sumCurrentSeasonSalaryFromRows([], false));
    }

    public function testSumInSeasonReadsCurrentContractYearSlot(): void
    {
        $rows = [
            ['cy' => 2, 'salary_yr1' => 100, 'salary_yr2' => 200, 'salary_yr3' => 300,
             'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
        ];

        // cy=2, in-season: read salary_yr2.
        $this->assertSame(200, BuyoutLedgerRepository::sumCurrentSeasonSalaryFromRows($rows, false));
    }

    public function testSumOffseasonAdvancesContractYearByOne(): void
    {
        $rows = [
            ['cy' => 1, 'salary_yr1' => 100, 'salary_yr2' => 200, 'salary_yr3' => 300,
             'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
        ];

        // cy=1 advanced to 2: read salary_yr2.
        $this->assertSame(200, BuyoutLedgerRepository::sumCurrentSeasonSalaryFromRows($rows, true));
    }

    public function testSumClampsContractYearZeroToYearOne(): void
    {
        $rows = [
            ['cy' => 0, 'salary_yr1' => 150, 'salary_yr2' => 0, 'salary_yr3' => 0,
             'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
        ];

        // cy=0, in-season: clamped to year 1.
        $this->assertSame(150, BuyoutLedgerRepository::sumCurrentSeasonSalaryFromRows($rows, false));
    }

    public function testSumAdvancesPastSixYieldsZero(): void
    {
        $rows = [
            ['cy' => 6, 'salary_yr1' => 100, 'salary_yr2' => 200, 'salary_yr3' => 300,
             'salary_yr4' => 400, 'salary_yr5' => 500, 'salary_yr6' => 600],
        ];

        // cy=6 advanced to 7: off the books.
        $this->assertSame(0, BuyoutLedgerRepository::sumCurrentSeasonSalaryFromRows($rows, true));
    }

    public function testSumAccumulatesAcrossRows(): void
    {
        $rows = [
            ['cy' => 1, 'salary_yr1' => 100, 'salary_yr2' => 0, 'salary_yr3' => 0,
             'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
            ['cy' => 1, 'salary_yr1' => 250, 'salary_yr2' => 0, 'salary_yr3' => 0,
             'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
        ];

        $this->assertSame(350, BuyoutLedgerRepository::sumCurrentSeasonSalaryFromRows($rows, false));
    }

    public function testSumDefaultsMissingContractYearToOne(): void
    {
        // No 'cy' key — defaults to year 1.
        $rows = [
            ['salary_yr1' => 175, 'salary_yr2' => 0, 'salary_yr3' => 0,
             'salary_yr4' => 0, 'salary_yr5' => 0, 'salary_yr6' => 0],
        ];

        $this->assertSame(175, BuyoutLedgerRepository::sumCurrentSeasonSalaryFromRows($rows, false));
    }
}
