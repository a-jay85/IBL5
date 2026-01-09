<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use BasketballStats\StatsSanitizer;

final class StatsSanitizerTest extends TestCase
{
    public function testSanitizeInt(): void
    {
        // Test normal integers
        $this->assertEquals(10, StatsSanitizer::sanitizeInt(10));
        $this->assertEquals(10, StatsSanitizer::sanitizeInt("10"));
        
        // Test null and empty
        $this->assertEquals(0, StatsSanitizer::sanitizeInt(null));
        $this->assertEquals(0, StatsSanitizer::sanitizeInt(""));
        
        // Test floats get truncated
        $this->assertEquals(10, StatsSanitizer::sanitizeInt(10.7));
        $this->assertEquals(10, StatsSanitizer::sanitizeInt("10.7"));
        
        // Test negative
        $this->assertEquals(-5, StatsSanitizer::sanitizeInt(-5));
    }

    public function testSanitizeFloat(): void
    {
        // Test normal floats
        $this->assertEquals(10.5, StatsSanitizer::sanitizeFloat(10.5));
        $this->assertEquals(10.5, StatsSanitizer::sanitizeFloat("10.5"));
        
        // Test null and empty
        $this->assertEquals(0.0, StatsSanitizer::sanitizeFloat(null));
        $this->assertEquals(0.0, StatsSanitizer::sanitizeFloat(""));
        
        // Test integers
        $this->assertEquals(10.0, StatsSanitizer::sanitizeFloat(10));
        
        // Test negative
        $this->assertEquals(-5.5, StatsSanitizer::sanitizeFloat(-5.5));
    }

    public function testSanitizeString(): void
    {
        // Test normal strings
        $this->assertEquals("hello", StatsSanitizer::sanitizeString("hello"));
        
        // Test null
        $this->assertEquals("", StatsSanitizer::sanitizeString(null));
        
        // Test numbers
        $this->assertEquals("10", StatsSanitizer::sanitizeString(10));
        $this->assertEquals("10.5", StatsSanitizer::sanitizeString(10.5));
    }

    public function testSanitizeRow(): void
    {
        $row = [
            'id' => '1',
            'name' => 'Player',
            'points' => '100',
            'average' => '10.5',
            'other' => 'value'
        ];
        
        $sanitized = StatsSanitizer::sanitizeRow(
            $row,
            ['id', 'points'],
            ['average']
        );
        
        $this->assertEquals(1, $sanitized['id']);
        $this->assertEquals(100, $sanitized['points']);
        $this->assertEquals(10.5, $sanitized['average']);
        $this->assertEquals('Player', $sanitized['name']);
        $this->assertEquals('value', $sanitized['other']);
    }

    public function testSanitizePercentage(): void
    {
        // Test normal percentages
        $this->assertEquals(0.5, StatsSanitizer::sanitizePercentage(0.5));
        $this->assertEquals(0.75, StatsSanitizer::sanitizePercentage(0.75));
        
        // Test clamping
        $this->assertEquals(0.0, StatsSanitizer::sanitizePercentage(-0.5));
        $this->assertEquals(1.0, StatsSanitizer::sanitizePercentage(1.5));
        
        // Test null
        $this->assertEquals(0.0, StatsSanitizer::sanitizePercentage(null));
        
        // Test boundaries
        $this->assertEquals(0.0, StatsSanitizer::sanitizePercentage(0));
        $this->assertEquals(1.0, StatsSanitizer::sanitizePercentage(1));
    }

    public function testSanitizeGames(): void
    {
        // Test normal values
        $this->assertEquals(10, StatsSanitizer::sanitizeGames(10));
        $this->assertEquals(10, StatsSanitizer::sanitizeGames("10"));
        
        // Test negative (should clamp to 0)
        $this->assertEquals(0, StatsSanitizer::sanitizeGames(-5));
        
        // Test null
        $this->assertEquals(0, StatsSanitizer::sanitizeGames(null));
        
        // Test zero
        $this->assertEquals(0, StatsSanitizer::sanitizeGames(0));
    }

    public function testSanitizeMinutes(): void
    {
        // Test normal values
        $this->assertEquals(30.5, StatsSanitizer::sanitizeMinutes(30.5));
        $this->assertEquals(30.0, StatsSanitizer::sanitizeMinutes(30));
        
        // Test negative (should clamp to 0)
        $this->assertEquals(0.0, StatsSanitizer::sanitizeMinutes(-5.5));
        
        // Test null
        $this->assertEquals(0.0, StatsSanitizer::sanitizeMinutes(null));
        
        // Test zero
        $this->assertEquals(0.0, StatsSanitizer::sanitizeMinutes(0));
    }
}
