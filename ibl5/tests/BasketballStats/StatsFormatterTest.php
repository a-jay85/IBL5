<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BasketballStats\StatsFormatter;

final class StatsFormatterTest extends TestCase
{
    public function testFormatPercentage(): void
    {
        // Test normal percentage
        $this->assertEquals("0.500", StatsFormatter::formatPercentage(5, 10));
        $this->assertEquals("0.750", StatsFormatter::formatPercentage(3, 4));
        
        // Test zero division
        $this->assertEquals("0.000", StatsFormatter::formatPercentage(5, 0));
        $this->assertEquals("0.000", StatsFormatter::formatPercentage(5, null));
        
        // Test null values
        $this->assertEquals("0.000", StatsFormatter::formatPercentage(null, 10));
        $this->assertEquals("0.000", StatsFormatter::formatPercentage(0, 10));
        
        // Test rounding
        $this->assertEquals("0.667", StatsFormatter::formatPercentage(2, 3));
    }

    public function testFormatPerGameAverage(): void
    {
        // Test normal average
        $this->assertEquals("10.0", StatsFormatter::formatPerGameAverage(100, 10));
        $this->assertEquals("12.5", StatsFormatter::formatPerGameAverage(25, 2));
        
        // Test zero division
        $this->assertEquals("0.0", StatsFormatter::formatPerGameAverage(100, 0));
        $this->assertEquals("0.0", StatsFormatter::formatPerGameAverage(100, null));
        
        // Test null values
        $this->assertEquals("0.0", StatsFormatter::formatPerGameAverage(null, 10));
        
        // Test rounding
        $this->assertEquals("10.3", StatsFormatter::formatPerGameAverage(31, 3));
    }

    public function testFormatPer36Stat(): void
    {
        // Test normal per-36
        $this->assertEquals("18.0", StatsFormatter::formatPer36Stat(10, 20));
        $this->assertEquals("7.2", StatsFormatter::formatPer36Stat(10, 50));
        
        // Test zero division
        $this->assertEquals("0.0", StatsFormatter::formatPer36Stat(10, 0));
        $this->assertEquals("0.0", StatsFormatter::formatPer36Stat(10, null));
        
        // Test null values
        $this->assertEquals("0.0", StatsFormatter::formatPer36Stat(null, 20));
    }

    public function testFormatTotal(): void
    {
        // Test normal totals
        $this->assertEquals("100", StatsFormatter::formatTotal(100));
        $this->assertEquals("1,234", StatsFormatter::formatTotal(1234));
        $this->assertEquals("10,000", StatsFormatter::formatTotal(10000));
        
        // Test null
        $this->assertEquals("0", StatsFormatter::formatTotal(null));
        
        // Test floats get rounded (banker's rounding)
        $this->assertEquals("124", StatsFormatter::formatTotal(123.7));
    }

    public function testFormatAverage(): void
    {
        // Test normal averages
        $this->assertEquals("10.50", StatsFormatter::formatAverage(10.5));
        $this->assertEquals("12.35", StatsFormatter::formatAverage(12.3456));
        
        // Test null
        $this->assertEquals("0.00", StatsFormatter::formatAverage(null));
        
        // Test rounding
        $this->assertEquals("10.57", StatsFormatter::formatAverage(10.567));
    }

    public function testCalculatePoints(): void
    {
        // Test normal calculation: 2*FGM + FTM + 3PM
        $this->assertEquals(25, StatsFormatter::calculatePoints(10, 3, 2)); // 20 + 3 + 2 = 25
        $this->assertEquals(10, StatsFormatter::calculatePoints(4, 1, 1));   // 8 + 1 + 1 = 10
        
        // Test with zeros
        $this->assertEquals(0, StatsFormatter::calculatePoints(0, 0, 0));
        
        // Test with nulls
        $this->assertEquals(20, StatsFormatter::calculatePoints(10, null, null));
        $this->assertEquals(5, StatsFormatter::calculatePoints(null, 5, null));
    }

    public function testSafeDivide(): void
    {
        // Test normal division
        $this->assertEquals(2.0, StatsFormatter::safeDivide(10, 5));
        $this->assertEquals(0.5, StatsFormatter::safeDivide(5, 10));
        
        // Test zero division
        $this->assertEquals(0.0, StatsFormatter::safeDivide(10, 0));
        $this->assertEquals(0.0, StatsFormatter::safeDivide(10, null));
        
        // Test null numerator
        $this->assertEquals(0.0, StatsFormatter::safeDivide(null, 10));
    }

    public function testFormatPercentageWithDecimals(): void
    {
        // Test with different decimal places
        $this->assertEquals("0.50", StatsFormatter::formatPercentageWithDecimals(5, 10, 2));
        $this->assertEquals("0.5000", StatsFormatter::formatPercentageWithDecimals(5, 10, 4));
        $this->assertEquals("0.500", StatsFormatter::formatPercentageWithDecimals(5, 10, 3));
        
        // Test zero division
        $this->assertEquals("0.00", StatsFormatter::formatPercentageWithDecimals(5, 0, 2));
    }

    public function testFormatWithDecimals(): void
    {
        // Test with different decimal places
        $this->assertEquals("10.5", StatsFormatter::formatWithDecimals(10.5, 1));
        $this->assertEquals("10.50", StatsFormatter::formatWithDecimals(10.5, 2));
        $this->assertEquals("11", StatsFormatter::formatWithDecimals(10.5, 0));
        
        // Test null
        $this->assertEquals("0.00", StatsFormatter::formatWithDecimals(null, 2));
        
        // Test rounding
        $this->assertEquals("10.6", StatsFormatter::formatWithDecimals(10.567, 1));
    }
}
