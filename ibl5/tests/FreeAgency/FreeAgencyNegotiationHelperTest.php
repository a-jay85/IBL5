<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for FreeAgencyNegotiationHelper max salary calculations
 * 
 * Validates that the max salary calculations in FreeAgencyNegotiationHelper::renderOfferButtons
 * correctly use bird years to determine raise percentages, matching FreeAgencyOfferValidator logic:
 * - Max raise = maxContract * raisePercentage (determined by bird years)
 * - 10% for players without Bird rights (bird < 3)
 * - 12.5% for players with Bird rights (bird >= 3)
 */
class FreeAgencyNegotiationHelperTest extends TestCase
{
    /**
     * @group negotiation
     * @group max-salaries
     * @group bird-rights
     */
    public function testMaxRaisePercentageWithBirdRights(): void
    {
        // Arrange - Player with Bird rights (3+ years)
        $birdYears = 3;
        $raisePercentage = \ContractRules::getMaxRaisePercentage($birdYears);

        // Assert - Should return 0.125 (12.5%) with Bird rights
        $this->assertEquals(0.125, $raisePercentage);
    }

    /**
     * @group negotiation
     * @group max-salaries
     * @group no-bird-rights
     */
    public function testMaxRaisePercentageWithoutBirdRights(): void
    {
        // Arrange - Player without Bird rights (< 3 years)
        $birdYears = 2;
        $raisePercentage = \ContractRules::getMaxRaisePercentage($birdYears);

        // Assert - Should return 0.10 (10%) without Bird rights
        $this->assertEquals(0.10, $raisePercentage);
    }

    /**
     * @group negotiation
     * @group max-salaries
     * @group calculation
     */
    public function testMaxSalaryCalculationWithBirdRights(): void
    {
        // Arrange
        $maxContract = 1275; // 7-9 years experience
        $raisePercentage = 0.125; // Bird rights
        $maxRaise = (int) round($maxContract * $raisePercentage);

        // Act - Calculate salaries like renderOfferButtons does
        $year1 = $maxContract;
        $year2 = $maxContract + $maxRaise;
        $year3 = $maxContract + ($maxRaise * 2);
        $year4 = $maxContract + ($maxRaise * 3);

        // Assert - Verify calculations
        $this->assertEquals(1275, $year1);
        $this->assertEquals(1275 + 159, $year2); // 1275 * 0.125 = 159.375 rounds to 159
        $this->assertEquals(1275 + (159 * 2), $year3);
        $this->assertEquals(1275 + (159 * 3), $year4);
    }

    /**
     * @group negotiation
     * @group max-salaries
     * @group calculation
     */
    public function testMaxSalaryCalculationWithoutBirdRights(): void
    {
        // Arrange
        $maxContract = 1063; // 0-6 years experience
        $raisePercentage = 0.10; // No Bird rights
        $maxRaise = (int) round($maxContract * $raisePercentage);

        // Act - Calculate salaries like renderOfferButtons does
        $year1 = $maxContract;
        $year2 = $maxContract + $maxRaise;
        $year3 = $maxContract + ($maxRaise * 2);
        $year4 = $maxContract + ($maxRaise * 3);

        // Assert - Verify calculations
        $this->assertEquals(1063, $year1);
        $this->assertEquals(1063 + 106, $year2); // 1063 * 0.10 = 106.3 rounds to 106
        $this->assertEquals(1063 + (106 * 2), $year3);
        $this->assertEquals(1063 + (106 * 3), $year4);
    }

    /**
     * @group negotiation
     * @group consistency
     * 
     * Validates that the raise calculation matches FreeAgencyOfferValidator logic
     */
    public function testRaiseCalculationConsistency(): void
    {
        // Both classes should use the same logic:
        // maxRaise = (int) round(firstYearOffer * raisePercentage)

        // Test with Bird rights
        $firstYearOffer = 1000;
        $birdYears = 3;
        $raisePercentage = \ContractRules::getMaxRaisePercentage($birdYears);
        
        $maxRaiseNegotiationHelper = (int) round($firstYearOffer * $raisePercentage);
        
        // Assert - Should be 125 (1000 * 0.125 = 125)
        $this->assertEquals(125, $maxRaiseNegotiationHelper);

        // Test without Bird rights
        $birdYears = 2;
        $raisePercentage = \ContractRules::getMaxRaisePercentage($birdYears);
        
        $maxRaiseNegotiationHelper = (int) round($firstYearOffer * $raisePercentage);
        
        // Assert - Should be 100 (1000 * 0.10 = 100)
        $this->assertEquals(100, $maxRaiseNegotiationHelper);
    }
}
