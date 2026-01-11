<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\DateParser;

/**
 * DateParserTest - Tests for DateParser utility
 *
 * Covers date parsing from schedule files including:
 * - Standard date formats
 * - "Post" date conversion (playoffs)
 * - Season phase adjustments
 * - Year determination based on month
 * - Olympics overrides
 */
class DateParserTest extends TestCase
{
    // Standard Date Parsing

    public function testExtractDateParsesStandardDate(): void
    {
        $result = DateParser::extractDate(
            'November 1, 2023',
            'Regular Season',
            2023,
            2024,
            'IBL'
        );

        $this->assertIsArray($result);
        $this->assertEquals(11, $result['month']);
        $this->assertEquals(1, $result['day']);
    }

    public function testExtractDateReturnsNullForEmptyString(): void
    {
        $result = DateParser::extractDate(
            '',
            'Regular Season',
            2023,
            2024,
            'IBL'
        );

        $this->assertNull($result);
    }

    public function testExtractDateReturnsNullForInvalidDate(): void
    {
        $result = DateParser::extractDate(
            'Not a valid date',
            'Regular Season',
            2023,
            2024,
            'IBL'
        );

        $this->assertNull($result);
    }

    // Post Date Conversion (Playoffs)

    public function testExtractDateConvertsPostToJune(): void
    {
        $result = DateParser::extractDate(
            'Post 15, 2024',
            'Playoffs',
            2023,
            2024,
            'IBL'
        );

        $this->assertIsArray($result);
        $this->assertEquals(6, $result['month']);
        $this->assertEquals(15, $result['day']);
    }

    public function testExtractDateHandlesPostDateWithDifferentDays(): void
    {
        $result = DateParser::extractDate(
            'Post 1, 2024',
            'Playoffs',
            2023,
            2024,
            'IBL'
        );

        $this->assertEquals(6, $result['month']);
        $this->assertEquals(1, $result['day']);
    }

    // Year Determination Based on Month

    public function testExtractDateUsesBeginningYearForLateFallMonths(): void
    {
        // November should use beginning year
        $result = DateParser::extractDate(
            'November 15, 2023',
            'Regular Season',
            2023,
            2024,
            'IBL'
        );

        $this->assertEquals(2023, $result['year']);
    }

    public function testExtractDateUsesEndingYearForWinterMonths(): void
    {
        // January should use ending year
        $result = DateParser::extractDate(
            'January 10, 2023',
            'Regular Season',
            2023,
            2024,
            'IBL'
        );

        $this->assertEquals(2024, $result['year']);
    }

    public function testExtractDateUsesEndingYearForSpringMonths(): void
    {
        // April should use ending year
        $result = DateParser::extractDate(
            'April 5, 2023',
            'Regular Season',
            2023,
            2024,
            'IBL'
        );

        $this->assertEquals(2024, $result['year']);
    }

    // Preseason Phase

    public function testExtractDatePreseasonUsesSpecialYear(): void
    {
        $result = DateParser::extractDate(
            'October 10, 2023',
            'Preseason',
            2023,
            2024,
            'IBL'
        );

        $this->assertIsArray($result);
        // Preseason uses IBL_PRESEASON_YEAR constant
        $this->assertEquals(\Season::IBL_PRESEASON_YEAR, $result['year']);
    }

    // Date Format Output

    public function testExtractDateReturnsFormattedDateString(): void
    {
        $result = DateParser::extractDate(
            'December 25, 2023',
            'Regular Season',
            2023,
            2024,
            'IBL'
        );

        $this->assertArrayHasKey('date', $result);
        $this->assertStringContainsString('12-25', $result['date']);
    }

    public function testExtractDateReturnsAllRequiredKeys(): void
    {
        $result = DateParser::extractDate(
            'February 14, 2024',
            'Regular Season',
            2023,
            2024,
            'IBL'
        );

        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('year', $result);
        $this->assertArrayHasKey('month', $result);
        $this->assertArrayHasKey('day', $result);
    }

    // Olympics League Override

    public function testExtractDateOlympicsUsesSpecialMonth(): void
    {
        $result = DateParser::extractDate(
            'August 10, 2024',
            'Olympics',
            2024,
            2025,
            'olympics'
        );

        $this->assertIsArray($result);
        $this->assertEquals(\Season::IBL_OLYMPICS_MONTH, $result['month']);
    }

    public function testExtractDateOlympicsCaseInsensitive(): void
    {
        $result = DateParser::extractDate(
            'August 10, 2024',
            'Olympics',
            2024,
            2025,
            'OLYMPICS'
        );

        $this->assertEquals(\Season::IBL_OLYMPICS_MONTH, $result['month']);
    }

    // Edge Cases

    public function testExtractDateHandlesSingleDigitDay(): void
    {
        $result = DateParser::extractDate(
            'March 5, 2024',
            'Regular Season',
            2023,
            2024,
            'IBL'
        );

        $this->assertEquals(5, $result['day']);
    }

    public function testExtractDateHandlesDoubleDigitDay(): void
    {
        $result = DateParser::extractDate(
            'March 25, 2024',
            'Regular Season',
            2023,
            2024,
            'IBL'
        );

        $this->assertEquals(25, $result['day']);
    }
}
