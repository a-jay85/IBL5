<?php

use PHPUnit\Framework\TestCase;
use FreeAgency\FreeAgencyNegotiationHelper;

/**
 * Tests for FreeAgencyNegotiationHelper
 * 
 * Validates negotiation page rendering:
 * - Veteran minimum calculations
 * - Max contract calculations
 * - Demand year calculations
 */
class FreeAgencyNegotiationHelperTest extends TestCase
{
    private $mockDb;
    private $helper;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->helper = new FreeAgencyNegotiationHelper($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->helper = null;
        $this->mockDb = null;
    }

    /**
     * @group veteran-minimum
     */
    public function testCalculateVeteranMinimumForRookie(): void
    {
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->helper);
        $method = $reflection->getMethod('calculateVeteranMinimum');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($this->helper, 0);

        // Assert
        $this->assertEquals(35, $result);
    }

    /**
     * @group veteran-minimum
     */
    public function testCalculateVeteranMinimumForVeteran(): void
    {
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->helper);
        $method = $reflection->getMethod('calculateVeteranMinimum');
        $method->setAccessible(true);

        // Act - 10+ years experience
        $result = $method->invoke($this->helper, 10);

        // Assert
        $this->assertEquals(103, $result);
    }

    /**
     * @group max-contract
     */
    public function testCalculateMaxContractForRookie(): void
    {
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->helper);
        $method = $reflection->getMethod('calculateMaxContract');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($this->helper, 0);

        // Assert
        $this->assertEquals(1063, $result);
    }

    /**
     * @group max-contract
     */
    public function testCalculateMaxContractForSupermax(): void
    {
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->helper);
        $method = $reflection->getMethod('calculateMaxContract');
        $method->setAccessible(true);

        // Act - 10+ years experience gets supermax
        $result = $method->invoke($this->helper, 10);

        // Assert
        $this->assertEquals(1451, $result);
    }

    /**
     * @group demand-years
     */
    public function testCalculateDemandYearsWithAllYears(): void
    {
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->helper);
        $method = $reflection->getMethod('calculateDemandYears');
        $method->setAccessible(true);

        // Arrange
        $demands = [
            'dem1' => 500,
            'dem2' => 550,
            'dem3' => 600,
            'dem4' => 650,
            'dem5' => 700,
            'dem6' => 750,
        ];

        // Act
        $result = $method->invoke($this->helper, $demands);

        // Assert
        $this->assertEquals(6, $result);
    }

    /**
     * @group demand-years
     */
    public function testCalculateDemandYearsWithThreeYears(): void
    {
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->helper);
        $method = $reflection->getMethod('calculateDemandYears');
        $method->setAccessible(true);

        // Arrange
        $demands = [
            'dem1' => 500,
            'dem2' => 550,
            'dem3' => 600,
            'dem4' => 0,
            'dem5' => 0,
            'dem6' => 0,
        ];

        // Act
        $result = $method->invoke($this->helper, $demands);

        // Assert
        $this->assertEquals(3, $result);
    }

    /**
     * @group demand-total
     */
    public function testCalculateTotalDemands(): void
    {
        // Use reflection to test private method
        $reflection = new ReflectionClass($this->helper);
        $method = $reflection->getMethod('calculateTotalDemands');
        $method->setAccessible(true);

        // Arrange
        $demands = [
            'dem1' => 500,
            'dem2' => 550,
            'dem3' => 600,
            'dem4' => 0,
            'dem5' => 0,
            'dem6' => 0,
        ];

        // Act
        $result = $method->invoke($this->helper, $demands);

        // Assert - Total 1650 / 100 = 16.50
        $this->assertEquals(16.50, $result);
    }
}
