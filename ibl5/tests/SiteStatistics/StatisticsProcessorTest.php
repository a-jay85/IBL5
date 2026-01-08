<?php

namespace SiteStatistics;

use PHPUnit\Framework\TestCase;

class StatisticsProcessorTest extends TestCase
{
    private StatisticsProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new StatisticsProcessor();
        
        // Mock language constants
        if (!defined('_JANUARY')) {
            define('_JANUARY', 'January');
            define('_FEBRUARY', 'February');
            define('_MARCH', 'March');
            define('_APRIL', 'April');
            define('_MAY', 'May');
            define('_JUNE', 'June');
            define('_JULY', 'July');
            define('_AUGUST', 'August');
            define('_SEPTEMBER', 'September');
            define('_OCTOBER', 'October');
            define('_NOVEMBER', 'November');
            define('_DECEMBER', 'December');
        }
    }

    public function testCalculatePercentageWithValidValues(): void
    {
        $result = $this->processor->calculatePercentage(25, 100);
        
        $this->assertEquals(25.0, $result);
    }

    public function testCalculatePercentageWithZeroTotal(): void
    {
        $result = $this->processor->calculatePercentage(25, 0);
        
        $this->assertEquals(0.0, $result);
    }

    public function testCalculatePercentageWithPrecision(): void
    {
        $result = $this->processor->calculatePercentage(1, 3, 3);
        
        $this->assertEquals(33.333, $result);
    }

    public function testCalculatePercentageRoundsCorrectly(): void
    {
        $result = $this->processor->calculatePercentage(2, 3, 2);
        
        $this->assertEquals(66.67, $result);
    }

    public function testProcessBrowserStatsWithAllBrowsers(): void
    {
        $browsers = [
            'FireFox' => 300,
            'MSIE' => 200,
            'Netscape' => 100,
            'Opera' => 50,
        ];

        $result = $this->processor->processBrowserStats($browsers, 1000);

        $this->assertArrayHasKey('FireFox', $result);
        $this->assertArrayHasKey('MSIE', $result);
        $this->assertEquals(300, $result['FireFox']['count']);
        $this->assertEquals(30.0, $result['FireFox']['percentage']);
        $this->assertEquals(200, $result['MSIE']['count']);
        $this->assertEquals(20.0, $result['MSIE']['percentage']);
    }

    public function testProcessBrowserStatsWithMissingBrowsers(): void
    {
        $browsers = [
            'FireFox' => 500,
        ];

        $result = $this->processor->processBrowserStats($browsers, 1000);

        $this->assertEquals(500, $result['FireFox']['count']);
        $this->assertEquals(50.0, $result['FireFox']['percentage']);
        $this->assertEquals(0, $result['MSIE']['count']);
        $this->assertEquals(0.0, $result['MSIE']['percentage']);
    }

    public function testProcessOSStatsWithAllOperatingSystems(): void
    {
        $osList = [
            'Windows' => 500,
            'Linux' => 300,
            'Mac' => 150,
            'FreeBSD' => 50,
        ];

        $result = $this->processor->processOSStats($osList, 1000);

        $this->assertArrayHasKey('Windows', $result);
        $this->assertArrayHasKey('Linux', $result);
        $this->assertEquals(500, $result['Windows']['count']);
        $this->assertEquals(50.0, $result['Windows']['percentage']);
        $this->assertEquals(300, $result['Linux']['count']);
        $this->assertEquals(30.0, $result['Linux']['percentage']);
    }

    public function testProcessOSStatsWithMissingOS(): void
    {
        $osList = [
            'Windows' => 800,
        ];

        $result = $this->processor->processOSStats($osList, 1000);

        $this->assertEquals(800, $result['Windows']['count']);
        $this->assertEquals(80.0, $result['Windows']['percentage']);
        $this->assertEquals(0, $result['Linux']['count']);
        $this->assertEquals(0.0, $result['Linux']['percentage']);
    }

    public function testGetMonthNameReturnsCorrectName(): void
    {
        $this->assertEquals('January', $this->processor->getMonthName(1));
        $this->assertEquals('February', $this->processor->getMonthName(2));
        $this->assertEquals('March', $this->processor->getMonthName(3));
        $this->assertEquals('December', $this->processor->getMonthName(12));
    }

    public function testGetMonthNameReturnsEmptyForInvalidMonth(): void
    {
        $this->assertEquals('', $this->processor->getMonthName(0));
        $this->assertEquals('', $this->processor->getMonthName(13));
    }

    public function testFormatHourRangeForSingleDigitHour(): void
    {
        $result = $this->processor->formatHourRange(5);
        
        $this->assertEquals('05:00 - 05:59', $result);
    }

    public function testFormatHourRangeForDoubleDigitHour(): void
    {
        $result = $this->processor->formatHourRange(14);
        
        $this->assertEquals('14:00 - 14:59', $result);
    }

    public function testFormatHourRangeForMidnight(): void
    {
        $result = $this->processor->formatHourRange(0);
        
        $this->assertEquals('00:00 - 00:59', $result);
    }

    public function testFormatHourRangeForEndOfDay(): void
    {
        $result = $this->processor->formatHourRange(23);
        
        $this->assertEquals('23:00 - 23:59', $result);
    }

    public function testCalculateBarWidthWithValidValues(): void
    {
        $result = $this->processor->calculateBarWidth(50, 100);
        
        $this->assertEquals(100, $result); // 50% * 2
    }

    public function testCalculateBarWidthWithZeroTotal(): void
    {
        $result = $this->processor->calculateBarWidth(50, 0);
        
        $this->assertEquals(0, $result);
    }

    public function testCalculateBarWidthWithCustomMultiplier(): void
    {
        $result = $this->processor->calculateBarWidth(25, 100, 4);
        
        $this->assertEquals(100, $result); // 25% * 4
    }

    public function testCalculateBarWidthRoundsCorrectly(): void
    {
        $result = $this->processor->calculateBarWidth(33, 100, 2);
        
        $this->assertEquals(66, $result); // 33% rounds to 33, * 2 = 66
    }
}
