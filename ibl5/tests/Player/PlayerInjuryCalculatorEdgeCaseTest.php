<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Player\PlayerInjuryCalculator;
use Player\PlayerData;

/**
 * Edge case tests for PlayerInjuryCalculator
 *
 * Tests boundary conditions, date edge cases, and unusual input scenarios.
 *
 * @covers \Player\PlayerInjuryCalculator
 */
class PlayerInjuryCalculatorEdgeCaseTest extends TestCase
{
    private PlayerInjuryCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new PlayerInjuryCalculator();
    }

    // ============================================
    // NEGATIVE DAYS REMAINING TESTS
    // ============================================

    public function testReturnsEmptyStringForNegativeOneDaysRemaining(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = -1;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-01-15');

        $this->assertEquals('', $result);
    }

    public function testReturnsEmptyStringForLargeNegativeDaysRemaining(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = -100;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-01-15');

        $this->assertEquals('', $result);
    }

    // ============================================
    // LARGE INJURY VALUE TESTS
    // ============================================

    public function testHandlesFullYearInjury(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 365;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-01-01');

        // 365 + 1 = 366 days from Jan 1, 2024 (leap year)
        $this->assertEquals('2025-01-01', $result);
    }

    public function testHandlesMultiYearInjury(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 730; // ~2 years

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-01-01');

        // 730 + 1 = 731 days from Jan 1, 2024
        // 2024 has 366 days (leap year), so Jan 1, 2025 is 366 days later
        // Then 731 - 366 = 365 more days = Jan 1, 2026
        $this->assertEquals('2026-01-01', $result);
    }

    public function testHandlesVeryLargeInjuryValue(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 1000;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-01-01');

        // Should return valid date format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);
    }

    // ============================================
    // YEAR BOUNDARY TESTS
    // ============================================

    public function testCrossesYearBoundaryFromDecember(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 10;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-12-25');

        // 10 + 1 = 11 days from Dec 25 = Jan 5
        $this->assertEquals('2025-01-05', $result);
    }

    public function testCrossesYearBoundaryFromDecember31(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 1;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-12-31');

        // 1 + 1 = 2 days from Dec 31 = Jan 2
        $this->assertEquals('2025-01-02', $result);
    }

    public function testExactlyOneDayToJanuary1(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 0; // Not injured

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-12-31');

        $this->assertEquals('', $result);
    }

    // ============================================
    // LEAP YEAR TESTS
    // ============================================

    public function testCrossesFebruaryInLeapYear(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 5;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-02-27');

        // 5 + 1 = 6 days from Feb 27, 2024 (leap year)
        // Feb 27 -> Feb 28 -> Feb 29 -> Mar 1 -> Mar 2 -> Mar 3 -> Mar 4
        $this->assertEquals('2024-03-04', $result);
    }

    public function testCrossesFebruaryInNonLeapYear(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 5;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2023-02-26');

        // 5 + 1 = 6 days from Feb 26, 2023 (non-leap year)
        // Feb 26 -> Feb 27 -> Feb 28 -> Mar 1 -> Mar 2 -> Mar 3 -> Mar 4
        $this->assertEquals('2023-03-04', $result);
    }

    public function testStartsOnLeapDay(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 3;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-02-29');

        // 3 + 1 = 4 days from Feb 29
        $this->assertEquals('2024-03-04', $result);
    }

    // ============================================
    // BOUNDARY VALUE TESTS
    // ============================================

    #[DataProvider('daysRemainingBoundaryProvider')]
    public function testDaysRemainingBoundaryValues(int $daysRemaining, bool $expectsDate): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = $daysRemaining;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-06-15');

        if ($expectsDate) {
            $this->assertNotEmpty($result);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $result);
        } else {
            $this->assertEquals('', $result);
        }
    }

    public static function daysRemainingBoundaryProvider(): array
    {
        return [
            'exactly zero - not injured' => [0, false],
            'exactly one - minimum injury' => [1, true],
            'two days' => [2, true],
            'negative one' => [-1, false],
            'large positive' => [999, true],
        ];
    }

    // ============================================
    // MONTH-END BOUNDARY TESTS
    // ============================================

    public function testCrossesMonth30DayBoundary(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 5;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-04-28');

        // 5 + 1 = 6 days from April 28 (30-day month)
        $this->assertEquals('2024-05-04', $result);
    }

    public function testCrossesMonth31DayBoundary(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 5;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-03-29');

        // 5 + 1 = 6 days from March 29 (31-day month)
        $this->assertEquals('2024-04-04', $result);
    }

    // ============================================
    // RETURN DATE FORMAT TESTS
    // ============================================

    public function testReturnDateIsAlwaysYMDFormat(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 7;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-01-01');

        // Verify format is exactly YYYY-MM-DD
        $this->assertMatchesRegularExpression('/^20\d{2}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $result);
    }

    public function testReturnDateHasLeadingZerosForSingleDigitMonthAndDay(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 1;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-01-01');

        // Jan 1 + 2 days = Jan 3
        $this->assertEquals('2024-01-03', $result);
        $this->assertStringContainsString('-01-', $result);
    }

    // ============================================
    // CALCULATION VERIFICATION TESTS
    // ============================================

    public function testAddsDaysRemainingPlusOneCorrectly(): void
    {
        // Per interface: adds (daysRemainingForInjury + 1) days
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 5;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-01-10');

        // Expected: Jan 10 + 6 days = Jan 16
        $this->assertEquals('2024-01-16', $result);
    }

    public function testTwoDaysRemainingMeansThreeDaysFromNow(): void
    {
        // If 2 days remaining, player returns in 2+1=3 days
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 2;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-01-01');

        $this->assertEquals('2024-01-04', $result);
    }

    // ============================================
    // EDGE INPUT DATE TESTS
    // ============================================

    public function testHandlesJanuary1StartDate(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 10;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-01-01');

        $this->assertEquals('2024-01-12', $result);
    }

    public function testHandlesDecember31StartDate(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 10;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-12-31');

        $this->assertEquals('2025-01-11', $result);
    }

    // ============================================
    // MULTI-MONTH SPAN TESTS
    // ============================================

    public function testSpansMultipleMonths(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 60;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-01-15');

        // 60 + 1 = 61 days from Jan 15
        // Jan has 16 remaining days, Feb has 29 (leap), need 16 more in March
        $this->assertEquals('2024-03-16', $result);
    }

    public function testSpansQuarter(): void
    {
        $playerData = new PlayerData();
        $playerData->daysRemainingForInjury = 90;

        $result = $this->calculator->getInjuryReturnDate($playerData, '2024-01-01');

        // 90 + 1 = 91 days from Jan 1
        // Jan 31 + Feb 29 + Mar 31 = 91 days = April 1
        $this->assertEquals('2024-04-01', $result);
    }
}
