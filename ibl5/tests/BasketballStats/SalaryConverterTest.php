<?php

declare(strict_types=1);

namespace Tests\BasketballStats;

use PHPUnit\Framework\TestCase;
use BasketballStats\SalaryConverter;

/**
 * SalaryConverterTest - Tests for SalaryConverter utility class
 *
 * @covers \BasketballStats\SalaryConverter
 */
class SalaryConverterTest extends TestCase
{
    // ============================================
    // CONVERT TO MILLIONS TESTS
    // ============================================

    /**
     * Test convertToMillions with zero value
     */
    public function testConvertToMillionsWithZero(): void
    {
        $result = SalaryConverter::convertToMillions(0);

        $this->assertSame(0.0, $result);
        $this->assertIsFloat($result);
    }

    /**
     * Test convertToMillions with standard salary value
     */
    public function testConvertToMillionsWithStandardSalary(): void
    {
        // 500 thousands = 5.0 in display format
        $result = SalaryConverter::convertToMillions(500);

        $this->assertSame(5.0, $result);
    }

    /**
     * Test convertToMillions with veteran minimum (around 260)
     */
    public function testConvertToMillionsWithVeteranMinimum(): void
    {
        // Vet min is around 260 thousands = 2.6 in display format
        $result = SalaryConverter::convertToMillions(260);

        $this->assertSame(2.6, $result);
    }

    /**
     * Test convertToMillions with rookie contract value
     */
    public function testConvertToMillionsWithRookieContract(): void
    {
        // Typical rookie contract around 700 thousands = 7.0 in display format
        $result = SalaryConverter::convertToMillions(700);

        $this->assertSame(7.0, $result);
    }

    /**
     * Test convertToMillions with max contract value
     */
    public function testConvertToMillionsWithMaxContract(): void
    {
        // Max contract around 4500 thousands = 45.0 in display format
        $result = SalaryConverter::convertToMillions(4500);

        $this->assertSame(45.0, $result);
    }

    /**
     * Test convertToMillions with supermax value
     */
    public function testConvertToMillionsWithSupermax(): void
    {
        // Supermax around 5000 thousands = 50.0 in display format
        $result = SalaryConverter::convertToMillions(5000);

        $this->assertSame(50.0, $result);
    }

    /**
     * Test convertToMillions always returns float type
     */
    public function testConvertToMillionsReturnsFloat(): void
    {
        $result = SalaryConverter::convertToMillions(100);

        $this->assertIsFloat($result);
    }

    /**
     * Test convertToMillions with small value (under 100)
     */
    public function testConvertToMillionsWithSmallValue(): void
    {
        // 50 thousands = 0.5 in display format
        $result = SalaryConverter::convertToMillions(50);

        $this->assertSame(0.5, $result);
    }

    /**
     * Test convertToMillions with value resulting in decimal
     */
    public function testConvertToMillionsWithDecimalResult(): void
    {
        // 333 thousands = 3.33 in display format
        $result = SalaryConverter::convertToMillions(333);

        $this->assertSame(3.33, $result);
    }

    /**
     * Test convertToMillions with large cap value
     */
    public function testConvertToMillionsWithCapValue(): void
    {
        // Hard cap is 7000 thousands = 70.0 in display format
        $result = SalaryConverter::convertToMillions(7000);

        $this->assertSame(70.0, $result);
    }

    /**
     * Test convertToMillions with single digit value
     */
    public function testConvertToMillionsWithSingleDigit(): void
    {
        // 1 thousand = 0.01 in display format
        $result = SalaryConverter::convertToMillions(1);

        $this->assertSame(0.01, $result);
    }

    /**
     * Test convertToMillions precision is maintained
     */
    public function testConvertToMillionsPrecision(): void
    {
        // 123 thousands = 1.23 in display format
        $result = SalaryConverter::convertToMillions(123);

        $this->assertSame(1.23, $result);
    }

    /**
     * Test convertToMillions with typical team salary total
     */
    public function testConvertToMillionsWithTeamSalaryTotal(): void
    {
        // Team salary total around 8250 thousands = 82.5 in display format
        $result = SalaryConverter::convertToMillions(8250);

        $this->assertSame(82.5, $result);
    }

    /**
     * Test convertToMillions division by 100 formula
     */
    public function testConvertToMillionsFormula(): void
    {
        // Verify the formula: thousands / 100 = display value
        $testCases = [
            100 => 1.0,
            200 => 2.0,
            1000 => 10.0,
            1500 => 15.0,
            2500 => 25.0,
        ];

        foreach ($testCases as $input => $expected) {
            $result = SalaryConverter::convertToMillions($input);
            $this->assertSame($expected, $result, "Failed for input: $input");
        }
    }
}
