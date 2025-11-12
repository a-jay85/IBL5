<?php

use PHPUnit\Framework\TestCase;
use Shared\SalaryConverter;

/**
 * Tests for SalaryConverter
 */
class SalaryConverterTest extends TestCase
{
    /**
     * Test converting thousands to millions
     */
    public function testConvertToMillions()
    {
        // 100 thousand = 1 million
        $this->assertEquals(1.0, SalaryConverter::convertToMillions(100));
        $this->assertEquals(2.5, SalaryConverter::convertToMillions(250));
        $this->assertEquals(0.5, SalaryConverter::convertToMillions(50));
        $this->assertEquals(0.0, SalaryConverter::convertToMillions(0));
    }
}
