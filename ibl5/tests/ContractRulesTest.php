<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use ContractRules;

/**
 * ContractRulesTest - Comprehensive tests for IBL CBA salary rules
 *
 * Tests all contract-related business logic including:
 * - Bird Rights thresholds and calculations
 * - Veteran minimum salaries by experience
 * - Maximum contract salaries by experience  
 * - Mid-Level and Lower-Level Exceptions
 */
class ContractRulesTest extends TestCase
{
    // ============================================
    // CONSTANTS VALIDATION
    // ============================================

    public function testStandardRaisePercentageIs10Percent(): void
    {
        $this->assertEquals(0.10, ContractRules::STANDARD_RAISE_PERCENTAGE);
    }

    public function testBirdRightsRaisePercentageIs12Point5Percent(): void
    {
        $this->assertEquals(0.125, ContractRules::BIRD_RIGHTS_RAISE_PERCENTAGE);
    }

    public function testBirdRightsThresholdIs3Years(): void
    {
        $this->assertEquals(3, ContractRules::BIRD_RIGHTS_THRESHOLD);
    }

    public function testLleOfferIs145(): void
    {
        $this->assertEquals(145, ContractRules::LLE_OFFER);
    }

    public function testMleOffersHas6Years(): void
    {
        $this->assertCount(6, ContractRules::MLE_OFFERS);
    }

    public function testMleOffersIncreaseBy45EachYear(): void
    {
        $offers = ContractRules::MLE_OFFERS;
        for ($i = 1; $i < count($offers); $i++) {
            $actualRaise = $offers[$i] - $offers[$i - 1];
            $this->assertEquals(45, $actualRaise, "Year " . ($i + 1) . " raise should be 45");
        }
    }

    // ============================================
    // BIRD RIGHTS TESTS
    // ============================================

    public function testHasBirdRightsReturnsFalseForZeroYears(): void
    {
        $this->assertFalse(ContractRules::hasBirdRights(0));
    }

    public function testHasBirdRightsReturnsFalseForOneYear(): void
    {
        $this->assertFalse(ContractRules::hasBirdRights(1));
    }

    public function testHasBirdRightsReturnsFalseForTwoYears(): void
    {
        $this->assertFalse(ContractRules::hasBirdRights(2));
    }

    public function testHasBirdRightsReturnsTrueForThreeYears(): void
    {
        $this->assertTrue(ContractRules::hasBirdRights(3));
    }

    public function testHasBirdRightsReturnsTrueForMoreThanThreeYears(): void
    {
        $this->assertTrue(ContractRules::hasBirdRights(5));
        $this->assertTrue(ContractRules::hasBirdRights(10));
    }

    // ============================================
    // MAX RAISE PERCENTAGE TESTS
    // ============================================

    public function testGetMaxRaisePercentageReturnsStandardForNoBirdRights(): void
    {
        $this->assertEquals(0.10, ContractRules::getMaxRaisePercentage(0));
        $this->assertEquals(0.10, ContractRules::getMaxRaisePercentage(1));
        $this->assertEquals(0.10, ContractRules::getMaxRaisePercentage(2));
    }

    public function testGetMaxRaisePercentageReturnsBirdRightsForThreePlusYears(): void
    {
        $this->assertEquals(0.125, ContractRules::getMaxRaisePercentage(3));
        $this->assertEquals(0.125, ContractRules::getMaxRaisePercentage(5));
        $this->assertEquals(0.125, ContractRules::getMaxRaisePercentage(10));
    }

    // ============================================
    // VETERAN MINIMUM SALARY TESTS
    // ============================================

    public function testGetVeteranMinimumSalaryForRookie(): void
    {
        $this->assertEquals(35, ContractRules::getVeteranMinimumSalary(1));
    }

    public function testGetVeteranMinimumSalaryForSecondYear(): void
    {
        $this->assertEquals(51, ContractRules::getVeteranMinimumSalary(2));
    }

    public function testGetVeteranMinimumSalaryForThirdYear(): void
    {
        $this->assertEquals(61, ContractRules::getVeteranMinimumSalary(3));
    }

    public function testGetVeteranMinimumSalaryForFourthYear(): void
    {
        $this->assertEquals(64, ContractRules::getVeteranMinimumSalary(4));
    }

    public function testGetVeteranMinimumSalaryForFifthYear(): void
    {
        $this->assertEquals(70, ContractRules::getVeteranMinimumSalary(5));
    }

    public function testGetVeteranMinimumSalaryForSixthYear(): void
    {
        $this->assertEquals(76, ContractRules::getVeteranMinimumSalary(6));
    }

    public function testGetVeteranMinimumSalaryForSeventhYear(): void
    {
        $this->assertEquals(82, ContractRules::getVeteranMinimumSalary(7));
    }

    public function testGetVeteranMinimumSalaryForEighthYear(): void
    {
        $this->assertEquals(89, ContractRules::getVeteranMinimumSalary(8));
    }

    public function testGetVeteranMinimumSalaryForNinthYear(): void
    {
        $this->assertEquals(100, ContractRules::getVeteranMinimumSalary(9));
    }

    public function testGetVeteranMinimumSalaryForTenPlusYears(): void
    {
        $this->assertEquals(103, ContractRules::getVeteranMinimumSalary(10));
        $this->assertEquals(103, ContractRules::getVeteranMinimumSalary(15));
        $this->assertEquals(103, ContractRules::getVeteranMinimumSalary(20));
    }

    public function testGetVeteranMinimumSalaryForZeroExperience(): void
    {
        // Should default to year 1 minimum
        $this->assertEquals(35, ContractRules::getVeteranMinimumSalary(0));
    }

    // ============================================
    // MAXIMUM CONTRACT SALARY TESTS
    // ============================================

    public function testGetMaxContractSalaryForRookie(): void
    {
        $this->assertEquals(1063, ContractRules::getMaxContractSalary(0));
    }

    public function testGetMaxContractSalaryFor6YearVeteran(): void
    {
        $this->assertEquals(1063, ContractRules::getMaxContractSalary(6));
    }

    public function testGetMaxContractSalaryFor7YearVeteran(): void
    {
        $this->assertEquals(1275, ContractRules::getMaxContractSalary(7));
    }

    public function testGetMaxContractSalaryFor8YearVeteran(): void
    {
        $this->assertEquals(1275, ContractRules::getMaxContractSalary(8));
    }

    public function testGetMaxContractSalaryFor9YearVeteran(): void
    {
        $this->assertEquals(1275, ContractRules::getMaxContractSalary(9));
    }

    public function testGetMaxContractSalaryFor10PlusYearVeteran(): void
    {
        $this->assertEquals(1451, ContractRules::getMaxContractSalary(10));
        $this->assertEquals(1451, ContractRules::getMaxContractSalary(15));
    }

    // ============================================
    // MLE OFFER TESTS
    // ============================================

    public function testGetMleOffersFor1Year(): void
    {
        $offers = ContractRules::getMLEOffers(1);
        $this->assertCount(1, $offers);
        $this->assertEquals(450, $offers[0]);
    }

    public function testGetMleOffersFor2Years(): void
    {
        $offers = ContractRules::getMLEOffers(2);
        $this->assertCount(2, $offers);
        $this->assertEquals([450, 495], $offers);
    }

    public function testGetMleOffersFor3Years(): void
    {
        $offers = ContractRules::getMLEOffers(3);
        $this->assertCount(3, $offers);
        $this->assertEquals([450, 495, 540], $offers);
    }

    public function testGetMleOffersFor6Years(): void
    {
        $offers = ContractRules::getMLEOffers(6);
        $this->assertCount(6, $offers);
        $this->assertEquals([450, 495, 540, 585, 630, 675], $offers);
    }

    public function testGetMleOffersForZeroYearsReturnsEmpty(): void
    {
        $offers = ContractRules::getMLEOffers(0);
        $this->assertCount(0, $offers);
    }

    // ============================================
    // EDGE CASES AND BOUNDARY TESTS
    // ============================================

    public function testVeteranMinimumSalariesAreInDescendingExperienceOrder(): void
    {
        $salaries = ContractRules::VETERAN_MINIMUM_SALARIES;
        $previousExperience = PHP_INT_MAX;
        
        foreach ($salaries as $experience => $salary) {
            $this->assertLessThan($previousExperience, $experience, 
                'VETERAN_MINIMUM_SALARIES should be in descending experience order');
            $previousExperience = $experience;
        }
    }

    public function testMaxContractSalariesAreInDescendingExperienceOrder(): void
    {
        $salaries = ContractRules::MAX_CONTRACT_SALARIES;
        $previousExperience = PHP_INT_MAX;
        
        foreach ($salaries as $experience => $salary) {
            $this->assertLessThan($previousExperience, $experience,
                'MAX_CONTRACT_SALARIES should be in descending experience order');
            $previousExperience = $experience;
        }
    }

    public function testBirdRightsRaiseIsHigherThanStandard(): void
    {
        $this->assertGreaterThan(
            ContractRules::STANDARD_RAISE_PERCENTAGE,
            ContractRules::BIRD_RIGHTS_RAISE_PERCENTAGE
        );
    }

    public function testAllMleOfferValuesArePositive(): void
    {
        foreach (ContractRules::MLE_OFFERS as $offer) {
            $this->assertGreaterThan(0, $offer);
        }
    }

    public function testAllVeteranMinimumSalariesArePositive(): void
    {
        foreach (ContractRules::VETERAN_MINIMUM_SALARIES as $salary) {
            $this->assertGreaterThan(0, $salary);
        }
    }

    public function testAllMaxContractSalariesArePositive(): void
    {
        foreach (ContractRules::MAX_CONTRACT_SALARIES as $salary) {
            $this->assertGreaterThan(0, $salary);
        }
    }

    public function testLleOfferIsLessThanFirstYearMle(): void
    {
        $this->assertLessThan(
            ContractRules::MLE_OFFERS[0],
            ContractRules::LLE_OFFER
        );
    }

    // --- Merged from FreeAgencyNegotiationHelperTest ---

    /**
     * @group negotiation
     * @group max-salaries
     * @group bird-rights
     */
    public function testMaxRaisePercentageWithBirdRights(): void
    {
        // Arrange - Player with Bird rights (3+ years)
        $birdYears = 3;
        $raisePercentage = ContractRules::getMaxRaisePercentage($birdYears);

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
        $raisePercentage = ContractRules::getMaxRaisePercentage($birdYears);

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
        $maxRaise = ContractRules::calculateMaxRaise($maxContract, 3);

        // Act - Calculate salaries like renderOfferButtons does
        $year1 = $maxContract;
        $year2 = $maxContract + $maxRaise;
        $year3 = $maxContract + ($maxRaise * 2);
        $year4 = $maxContract + ($maxRaise * 3);

        // Assert - Verify calculations
        $this->assertEquals(1275, $year1);
        $this->assertEquals(1275 + 159, $year2); // 1275 * 0.125 = 159.375 floors to 159
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
        $maxRaise = ContractRules::calculateMaxRaise($maxContract, 0);

        // Act - Calculate salaries like renderOfferButtons does
        $year1 = $maxContract;
        $year2 = $maxContract + $maxRaise;
        $year3 = $maxContract + ($maxRaise * 2);
        $year4 = $maxContract + ($maxRaise * 3);

        // Assert - Verify calculations
        $this->assertEquals(1063, $year1);
        $this->assertEquals(1063 + 106, $year2); // 1063 * 0.10 = 106.3 floors to 106
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
        // All raise calculations use ContractRules::calculateMaxRaise()

        // Test with Bird rights
        $maxRaise = ContractRules::calculateMaxRaise(1000, 3);
        // Assert - Should be 125 (floor(1000 * 0.125) = 125)
        $this->assertEquals(125, $maxRaise);

        // Test without Bird rights
        $maxRaise = ContractRules::calculateMaxRaise(1000, 2);
        // Assert - Should be 100 (floor(1000 * 0.10) = 100)
        $this->assertEquals(100, $maxRaise);
    }

    /**
     * @group negotiation
     * @group max-salaries
     * @group regression
     *
     * Regression test: 10+ year player with bird rights should show 1451 as year 1 offer,
     * not 1632 (which was year 2 due to array indexing bug)
     *
     * This test validates the fix for the bug where FreeAgencyNegotiationHelper was
     * using 1-based array indexing while FreeAgencyViewHelper::renderMaxContractButtons
     * was using array_slice with offset 1, causing year 1 to display year 2 values.
     */
    public function testMaxSalaryFirstYearFor10PlusYearsWithBirdRights(): void
    {
        // Arrange - Player with 10+ years experience and Bird rights
        $yearsOfExperience = 10;
        $birdYears = 3; // Has Bird rights (3+ years with team)

        // Act - Calculate like renderOfferButtons does
        $maxContract = ContractRules::getMaxContractSalary($yearsOfExperience);
        $maxRaise = ContractRules::calculateMaxRaise($maxContract, $birdYears);

        // Build salary array with 0-based indexing (after fix)
        $maxSalaries = [
            0 => $maxContract,
            1 => $maxContract + $maxRaise,
            2 => $maxContract + ($maxRaise * 2),
            3 => $maxContract + ($maxRaise * 3),
            4 => $maxContract + ($maxRaise * 4),
            5 => $maxContract + ($maxRaise * 5),
        ];

        // Simulate what renderMaxContractButtons does for 1-year contract
        $oneYearOffer = array_slice($maxSalaries, 0, 1);

        // Assert - First year should be 1451, not 1632
        $this->assertEquals(1451, $maxContract);
        $this->assertEquals(181, $maxRaise); // floor(1451 * 0.125) = floor(181.375) = 181
        $this->assertEquals([1451], $oneYearOffer);
        $this->assertEquals(1451, $oneYearOffer[0]);
    }
}
