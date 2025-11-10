<?php

use PHPUnit\Framework\TestCase;
use RookieOption\RookieOptionProcessor;

/**
 * Tests for RookieOptionProcessor
 */
class RookieOptionProcessorTest extends TestCase
{
    private $processor;
    
    protected function setUp(): void
    {
        $this->processor = new RookieOptionProcessor();
    }
    
    /**
     * Test calculating rookie option value
     */
    public function testCalculateRookieOptionValue()
    {
        // Rookie option should be 2x the final year salary
        $this->assertEquals(200, $this->processor->calculateRookieOptionValue(100));
        $this->assertEquals(400, $this->processor->calculateRookieOptionValue(200));
        $this->assertEquals(800, $this->processor->calculateRookieOptionValue(400));
        $this->assertEquals(0, $this->processor->calculateRookieOptionValue(0));
    }
    
    /**
     * Test converting thousands to millions
     */
    public function testConvertToMillions()
    {
        // 100 thousand = 1 million
        $this->assertEquals(1.0, $this->processor->convertToMillions(100));
        $this->assertEquals(2.5, $this->processor->convertToMillions(250));
        $this->assertEquals(0.5, $this->processor->convertToMillions(50));
        $this->assertEquals(0.0, $this->processor->convertToMillions(0));
    }
}
