<?php

declare(strict_types=1);

namespace Tests\BasketballStats;

use PHPUnit\Framework\TestCase;
use BasketballStats\StatsSanitizer;

final class StatsSanitizerTest extends TestCase
{
    public function testSanitizeInt(): void
    {
        // Test normal integers
        $this->assertSame(10, StatsSanitizer::sanitizeInt(10));
        $this->assertSame(10, StatsSanitizer::sanitizeInt("10"));
        
        // Test null and empty
        $this->assertSame(0, StatsSanitizer::sanitizeInt(null));
        $this->assertSame(0, StatsSanitizer::sanitizeInt(""));
        
        // Test floats get truncated
        $this->assertSame(10, StatsSanitizer::sanitizeInt(10.7));
        $this->assertSame(10, StatsSanitizer::sanitizeInt("10.7"));
        
        // Test negative
        $this->assertSame(-5, StatsSanitizer::sanitizeInt(-5));
    }

    public function testToIntCoercesNumericInput(): void
    {
        // int passthrough, including bounds and zero
        $this->assertSame(10, StatsSanitizer::toInt(10));
        $this->assertSame(0, StatsSanitizer::toInt(0));
        $this->assertSame(-5, StatsSanitizer::toInt(-5));
        $this->assertSame(PHP_INT_MAX, StatsSanitizer::toInt(PHP_INT_MAX));

        // numeric strings, including negative and "0"
        $this->assertSame(10, StatsSanitizer::toInt('10'));
        $this->assertSame(-5, StatsSanitizer::toInt('-5'));
        $this->assertSame(0, StatsSanitizer::toInt('0'));

        // floats and float strings truncate toward zero
        $this->assertSame(10, StatsSanitizer::toInt(10.7));
        $this->assertSame(-10, StatsSanitizer::toInt(-10.7));
        $this->assertSame(10, StatsSanitizer::toInt('10.7'));
    }

    public function testToIntReturnsZeroForNonCoercibleInput(): void
    {
        $this->assertSame(0, StatsSanitizer::toInt(null));
        $this->assertSame(0, StatsSanitizer::toInt(''));
        $this->assertSame(0, StatsSanitizer::toInt('abc'));
        $this->assertSame(0, StatsSanitizer::toInt('12abc'));   // sanitizeInt() would give 12
        $this->assertSame(0, StatsSanitizer::toInt(true));      // sanitizeInt() would give 1
        $this->assertSame(0, StatsSanitizer::toInt(false));
        $this->assertSame(0, StatsSanitizer::toInt([]));
        $this->assertSame(0, StatsSanitizer::toInt(['7']));
        $this->assertSame(0, StatsSanitizer::toInt(new \stdClass()));
    }

    public function testSanitizeFloat(): void
    {
        // Test normal floats
        $this->assertSame(10.5, StatsSanitizer::sanitizeFloat(10.5));
        $this->assertSame(10.5, StatsSanitizer::sanitizeFloat("10.5"));
        
        // Test null and empty
        $this->assertSame(0.0, StatsSanitizer::sanitizeFloat(null));
        $this->assertSame(0.0, StatsSanitizer::sanitizeFloat(""));
        
        // Test integers
        $this->assertSame(10.0, StatsSanitizer::sanitizeFloat(10));
        
        // Test negative
        $this->assertSame(-5.5, StatsSanitizer::sanitizeFloat(-5.5));
    }

    public function testSanitizeString(): void
    {
        // Test normal strings
        $this->assertSame("hello", StatsSanitizer::sanitizeString("hello"));
        
        // Test null
        $this->assertSame("", StatsSanitizer::sanitizeString(null));
        
        // Test numbers
        $this->assertSame("10", StatsSanitizer::sanitizeString(10));
        $this->assertSame("10.5", StatsSanitizer::sanitizeString(10.5));
    }

    public function testIntFromArrayPassesIntsAndCastsNumericStrings(): void
    {
        $this->assertSame(7, StatsSanitizer::intFromArray(['a' => 7], 'a'));
        $this->assertSame(12, StatsSanitizer::intFromArray(['a' => '12'], 'a'));
        $this->assertSame(-3, StatsSanitizer::intFromArray(['a' => '-3'], 'a'));
        $this->assertSame(0, StatsSanitizer::intFromArray(['a' => 0], 'a'));
    }

    public function testIntFromArrayReturnsZeroForNonIntegralValues(): void
    {
        $this->assertSame(0, StatsSanitizer::intFromArray(['a' => 1.9], 'a'));
        $this->assertSame(0, StatsSanitizer::intFromArray(['a' => true], 'a'));
        $this->assertSame(0, StatsSanitizer::intFromArray(['a' => ['5']], 'a'));
        $this->assertSame(0, StatsSanitizer::intFromArray(['a' => null], 'a'));
        $this->assertSame(0, StatsSanitizer::intFromArray(['a' => 'abc'], 'a'));
        $this->assertSame(0, StatsSanitizer::intFromArray(['a' => ''], 'a'));
        $this->assertSame(0, StatsSanitizer::intFromArray(['a' => '12abc'], 'a'));
        $this->assertSame(0, StatsSanitizer::intFromArray([], 'a'));
    }

    public function testScalarToStringStringifiesStringsIntsAndFloats(): void
    {
        $this->assertSame('Bob', StatsSanitizer::scalarToString('Bob'));
        $this->assertSame('', StatsSanitizer::scalarToString(''));
        $this->assertSame('23', StatsSanitizer::scalarToString(23));
        $this->assertSame('1.5', StatsSanitizer::scalarToString(1.5));
    }

    public function testScalarToStringReturnsEmptyForNullBoolAndArray(): void
    {
        $this->assertSame('', StatsSanitizer::scalarToString(null));
        $this->assertSame('', StatsSanitizer::scalarToString(true));
        $this->assertSame('', StatsSanitizer::scalarToString(false));
        $this->assertSame('', StatsSanitizer::scalarToString(['x']));
    }
}
