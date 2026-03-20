<?php

declare(strict_types=1);

namespace Tests\Services;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Services\CommonContractValidator;

/**
 * CommonContractValidatorTest - Unit tests for shared contract validation
 * 
 * Tests the CommonContractValidator service which provides reusable
 * validation methods for contract offers across Extension, FreeAgency,
 * and Negotiation modules.
 */
class CommonContractValidatorTest extends TestCase
{
    private CommonContractValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CommonContractValidator();
    }

    // ==================== validateRaises Tests ====================

    /**
     * @group raises
     */
    public function testValidateRaisesSucceedsWithoutBirdRights(): void
    {
        // 10% max raise without bird rights
        // Year 1: 1000, Year 2: 1100 (10% raise) is legal
        $offer = [
            'year1' => 1000,
            'year2' => 1100,
            'year3' => 1200,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0
        ];

        $result = $this->validator->validateRaises($offer, 0);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    /**
     * @group raises
     */
    public function testValidateRaisesSucceedsWithBirdRights(): void
    {
        // 12.5% max raise with bird rights (3+ years)
        // Year 1: 1000, Year 2: 1125 (12.5% raise) is legal
        $offer = [
            'year1' => 1000,
            'year2' => 1125,
            'year3' => 1250,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0
        ];

        $result = $this->validator->validateRaises($offer, 3);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    /**
     * @group raises
     */
    public function testValidateRaisesFailsWithExcessiveRaiseWithoutBirdRights(): void
    {
        // 10% max raise without bird rights
        // Year 1: 1000, Year 2: 1150 (15% raise) exceeds limit
        $offer = [
            'year1' => 1000,
            'year2' => 1150,
            'year3' => 1300
        ];

        $result = $this->validator->validateRaises($offer, 0);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('raise', $result['error']);
        $this->assertStringContainsString('1150', $result['error']);
    }

    /**
     * @group raises
     */
    public function testValidateRaisesFailsWithExcessiveRaiseWithBirdRights(): void
    {
        // 12.5% max raise with bird rights
        // Year 1: 1000, Year 2: 1200 (20% raise) exceeds limit
        $offer = [
            'year1' => 1000,
            'year2' => 1200,
            'year3' => 1300
        ];

        $result = $this->validator->validateRaises($offer, 3);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('raise', $result['error']);
    }

    /**
     * @group raises
     */
    public function testValidateRaisesSkipsZeroYears(): void
    {
        // Year 4 is zero - should be skipped
        $offer = [
            'year1' => 1000,
            'year2' => 1100,
            'year3' => 1200,
            'year4' => 0,
            'year5' => 0
        ];

        $result = $this->validator->validateRaises($offer, 0);

        $this->assertTrue($result['valid']);
    }

    /**
     * @group raises
     */
    public function testValidateRaisesAppliesMaxRaiseToEachYear(): void
    {
        // Max raise must be applied consistently (can't compound)
        // Year 1: 1000, max 100/year
        // Year 2: 1100 (ok), Year 3: 1200 (ok), Year 4: 1300 (ok), Year 5: 1400 (ok)
        $offer = [
            'year1' => 1000,
            'year2' => 1100,
            'year3' => 1200,
            'year4' => 1300,
            'year5' => 1400,
            'year6' => 1500
        ];

        $result = $this->validator->validateRaises($offer, 0);

        $this->assertTrue($result['valid']);
    }

    /**
     * @group raises
     */
    public function testValidateRaisesFailsWhenAnyYearExceedsMaxRaise(): void
    {
        // Year 5 exceeds max raise
        $offer = [
            'year1' => 1000,
            'year2' => 1100,
            'year3' => 1200,
            'year4' => 1300,
            'year5' => 1600,  // Should be max 1500
            'year6' => 0
        ];

        $result = $this->validator->validateRaises($offer, 0);

        $this->assertFalse($result['valid']);
    }

    // ==================== validateSalaryDecreases Tests ====================

    /**
     * @group salary-decreases
     */
    public function testValidateSalaryDecreasesSucceedsWithIncreasingCombo(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 650,
            'year5' => 700
        ];

        $result = $this->validator->validateSalaryDecreases($offer);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    /**
     * @group salary-decreases
     */
    public function testValidateSalaryDecreasesSucceedsWithContractEnd(): void
    {
        // Year 4 is zero (contract ends) - allowed
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 0,
            'year5' => 0
        ];

        $result = $this->validator->validateSalaryDecreases($offer);

        $this->assertTrue($result['valid']);
    }

    /**
     * @group salary-decreases
     */
    public function testValidateSalaryDecreasesFailsWithYear2Decrease(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 450,  // Decrease from 500
            'year3' => 600
        ];

        $result = $this->validator->validateSalaryDecreases($offer);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('cannot decrease', $result['error']);
        $this->assertStringContainsString('450', $result['error']);
    }

    /**
     * @group salary-decreases
     */
    public function testValidateSalaryDecreasesFailsWithYear3Decrease(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 500  // Decrease from 550
        ];

        $result = $this->validator->validateSalaryDecreases($offer);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('3 year', $result['error']);
    }

    /**
     * @group salary-decreases
     */
    public function testValidateSalaryDecreasesFailsWithYear5Decrease(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 650,
            'year5' => 600  // Decrease from 650
        ];

        $result = $this->validator->validateSalaryDecreases($offer);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('5 year', $result['error']);
    }

    /**
     * @group salary-decreases
     */
    public function testValidateSalaryDecreasesSucceedsWithFlatSalary(): void
    {
        // Flat salary is allowed
        $offer = [
            'year1' => 500,
            'year2' => 500,
            'year3' => 500,
            'year4' => 500
        ];

        $result = $this->validator->validateSalaryDecreases($offer);

        $this->assertTrue($result['valid']);
    }

    // ==================== validateMaximumYearOne Tests ====================

    /**
     * @group max-year-one
     */
    public function testValidateMaximumYearOneSucceedsForRookie(): void
    {
        // Rookie (0 years): max 1063
        $offer = ['year1' => 1063, 'year2' => 1100, 'year3' => 1200];

        $result = $this->validator->validateMaximumYearOne($offer, 0);

        $this->assertTrue($result['valid']);
    }

    /**
     * @group max-year-one
     */
    public function testValidateMaximumYearOneSucceedsFor3YearPlayer(): void
    {
        // 3 years experience: max 1063
        $offer = ['year1' => 1063, 'year2' => 1100, 'year3' => 1200];

        $result = $this->validator->validateMaximumYearOne($offer, 3);

        $this->assertTrue($result['valid']);
    }

    /**
     * @group max-year-one
     */
    public function testValidateMaximumYearOneSucceedsFor7YearPlayer(): void
    {
        // 7 years experience: max 1275
        $offer = ['year1' => 1275, 'year2' => 1350, 'year3' => 1425];

        $result = $this->validator->validateMaximumYearOne($offer, 7);

        $this->assertTrue($result['valid']);
    }

    /**
     * @group max-year-one
     */
    public function testValidateMaximumYearOneSucceedsFor10YearPlayer(): void
    {
        // 10+ years experience: max 1451
        $offer = ['year1' => 1451, 'year2' => 1550, 'year3' => 1650];

        $result = $this->validator->validateMaximumYearOne($offer, 10);

        $this->assertTrue($result['valid']);
    }

    /**
     * @group max-year-one
     */
    public function testValidateMaximumYearOneFailsForRookieOverMax(): void
    {
        // Rookie over max (1063)
        $offer = ['year1' => 1100, 'year2' => 1200, 'year3' => 1300];

        $result = $this->validator->validateMaximumYearOne($offer, 0);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('maximum', $result['error']);
    }

    /**
     * @group max-year-one
     */
    public function testValidateMaximumYearOneFailsFor7YearPlayerOverMax(): void
    {
        // 7 year player over max (1275)
        $offer = ['year1' => 1300, 'year2' => 1400, 'year3' => 1500];

        $result = $this->validator->validateMaximumYearOne($offer, 7);

        $this->assertFalse($result['valid']);
    }

    /**
     * @group max-year-one
     */
    public function testValidateMaximumYearOneFailsFor10YearPlayerOverMax(): void
    {
        // 10+ year player over max (1451)
        $offer = ['year1' => 1500, 'year2' => 1600, 'year3' => 1700];

        $result = $this->validator->validateMaximumYearOne($offer, 10);

        $this->assertFalse($result['valid']);
    }

    // ==================== validateNoGaps Tests ====================

    /**
     * @group no-gaps
     */
    public function testValidateNoGapsSucceedsWithFull6Years(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 650,
            'year5' => 700,
            'year6' => 750
        ];

        $result = $this->validator->validateNoGaps($offer);

        $this->assertTrue($result['valid']);
    }

    /**
     * @group no-gaps
     */
    public function testValidateNoGapsSucceedsWithContractEnd(): void
    {
        // 3-year contract ending in year 4
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0
        ];

        $result = $this->validator->validateNoGaps($offer);

        $this->assertTrue($result['valid']);
    }

    /**
     * @group no-gaps
     */
    public function testValidateNoGapsFailsWithGapInYear4(): void
    {
        // Year 3 ends contract, Year 4 resumes
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 0,
            'year4' => 600,
            'year5' => 0
        ];

        $result = $this->validator->validateNoGaps($offer);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('gaps', $result['error']);
        $this->assertStringContainsString('year 3', $result['error']);
    }

    /**
     * @group no-gaps
     */
    public function testValidateNoGapsFailsWithGapInYear5(): void
    {
        // Year 4 ends contract, Year 5 resumes
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 0,
            'year5' => 700,
            'year6' => 0
        ];

        $result = $this->validator->validateNoGaps($offer);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('year 4', $result['error']);
        $this->assertStringContainsString('year 5', $result['error']);
    }

    /**
     * @group no-gaps
     */
    public function testValidateNoGapsFailsWithGapInYear6(): void
    {
        // Year 5 ends contract, Year 6 resumes
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 650,
            'year5' => 0,
            'year6' => 750
        ];

        $result = $this->validator->validateNoGaps($offer);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('year 5', $result['error']);
    }

    /**
     * @group no-gaps
     */
    public function testValidateNoGapsSucceedsWithMissingOptionalYears(): void
    {
        // Year 4-6 missing from array (treated as 0)
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600
        ];

        $result = $this->validator->validateNoGaps($offer);

        $this->assertTrue($result['valid']);
    }

    // ==================== calculateOfferValue Tests ====================

    /**
     * @group calculate-value
     */
    public function testCalculateOfferValueFor3YearContract(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0
        ];

        $result = $this->validator->calculateOfferValue($offer);

        $this->assertEquals(1650, $result['total']);
        $this->assertEquals(3, $result['years']);
        $this->assertEquals(550.0, $result['averagePerYear']);
    }

    /**
     * @group calculate-value
     */
    public function testCalculateOfferValueFor5YearContract(): void
    {
        $offer = [
            'year1' => 1000,
            'year2' => 1100,
            'year3' => 1200,
            'year4' => 1300,
            'year5' => 1400,
            'year6' => 0
        ];

        $result = $this->validator->calculateOfferValue($offer);

        $this->assertEquals(6000, $result['total']);
        $this->assertEquals(5, $result['years']);
        $this->assertEquals(1200.0, $result['averagePerYear']);
    }

    /**
     * @group calculate-value
     */
    public function testCalculateOfferValueFor6YearContract(): void
    {
        $offer = [
            'year1' => 1000,
            'year2' => 1100,
            'year3' => 1200,
            'year4' => 1300,
            'year5' => 1400,
            'year6' => 1500
        ];

        $result = $this->validator->calculateOfferValue($offer);

        $this->assertEquals(7500, $result['total']);
        $this->assertEquals(6, $result['years']);
        $this->assertEquals(1250.0, $result['averagePerYear']);
    }

    /**
     * @group calculate-value
     */
    public function testCalculateOfferValueFor1YearContract(): void
    {
        $offer = [
            'year1' => 800,
            'year2' => 0,
            'year3' => 0,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0
        ];

        $result = $this->validator->calculateOfferValue($offer);

        $this->assertEquals(800, $result['total']);
        $this->assertEquals(1, $result['years']);
        $this->assertEquals(800.0, $result['averagePerYear']);
    }

    /**
     * @group calculate-value
     */
    public function testCalculateOfferValueWithAllZero(): void
    {
        $offer = [
            'year1' => 0,
            'year2' => 0,
            'year3' => 0,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0
        ];

        $result = $this->validator->calculateOfferValue($offer);

        $this->assertEquals(0, $result['total']);
        $this->assertEquals(1, $result['years']); // Minimum of 1 year
        $this->assertEquals(0.0, $result['averagePerYear']);
    }

    /**
     * @group calculate-value
     */
    public function testCalculateOfferValueWithMissingYears(): void
    {
        // Only year1-3 provided
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600
        ];

        $result = $this->validator->calculateOfferValue($offer);

        $this->assertEquals(1650, $result['total']);
        $this->assertEquals(3, $result['years']);
        $this->assertEqualsWithDelta(550.0, $result['averagePerYear'], 0.01);
    }

    /**
     * @group calculate-value
     */
    public function testCalculateOfferValueIgnoresZeroYears(): void
    {
        // Year 4-5 are zero, only 1-3 count
        $offer = [
            'year1' => 1000,
            'year2' => 1100,
            'year3' => 1200,
            'year4' => 0,
            'year5' => 0
        ];

        $result = $this->validator->calculateOfferValue($offer);

        $this->assertEquals(3300, $result['total']);
        $this->assertEquals(3, $result['years']);
        $this->assertEquals(1100.0, $result['averagePerYear']);
    }

    // ==================== Integration Tests ====================

    /**
     * @group integration
     */
    public function testCompleteValidationWorkflow(): void
    {
        // Valid contract offer that passes all validations
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0
        ];

        // Check raises (without bird rights)
        $result = $this->validator->validateRaises($offer, 0);
        $this->assertTrue($result['valid']);

        // Check decreases
        $result = $this->validator->validateSalaryDecreases($offer);
        $this->assertTrue($result['valid']);

        // Check max year 1 (rookie)
        $result = $this->validator->validateMaximumYearOne($offer, 0);
        $this->assertTrue($result['valid']);

        // Check no gaps
        $result = $this->validator->validateNoGaps($offer);
        $this->assertTrue($result['valid']);

        // Calculate value
        $value = $this->validator->calculateOfferValue($offer);
        $this->assertEquals(1650, $value['total']);
        $this->assertEquals(3, $value['years']);
    }

    /**
     * @group integration
     */
    public function testCompleteValidationWithVeteranBirdRights(): void
    {
        // Veteran with bird rights using 12.5% max raise
        $offer = [
            'year1' => 1000,
            'year2' => 1125,
            'year3' => 1250,
            'year4' => 1375,
            'year5' => 1500,
            'year6' => 0
        ];

        // Check raises with bird rights (5 years service)
        $result = $this->validator->validateRaises($offer, 5);
        $this->assertTrue($result['valid']);

        // Check decreases
        $result = $this->validator->validateSalaryDecreases($offer);
        $this->assertTrue($result['valid']);

        // Check max year 1 (5-year vet: max 1063, but this is 1000 so ok)
        $result = $this->validator->validateMaximumYearOne($offer, 5);
        $this->assertTrue($result['valid']);

        // Check value
        $value = $this->validator->calculateOfferValue($offer);
        $this->assertEquals(6250, $value['total']);
        $this->assertEquals(5, $value['years']);
    }

    // --- Merged from CommonContractValidatorEdgeCaseTest ---

    // ============================================
    // EMPTY/NULL OFFER ARRAY TESTS
    // ============================================

    /**
     * Test validateRaises with empty offer array
     */
    public function testValidateRaisesWithEmptyArraySucceeds(): void
    {
        $result = $this->validator->validateRaises([], 0);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test validateSalaryDecreases with empty offer array
     */
    public function testValidateSalaryDecreasesWithEmptyArraySucceeds(): void
    {
        $result = $this->validator->validateSalaryDecreases([]);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test validateMaximumYearOne with empty offer array
     */
    public function testValidateMaximumYearOneWithEmptyArraySucceeds(): void
    {
        $result = $this->validator->validateMaximumYearOne([], 0);

        // Empty year1 defaults to 0, which is under max
        $this->assertTrue($result['valid']);
    }

    /**
     * Test validateNoGaps with empty offer array
     */
    public function testValidateNoGapsWithEmptyArraySucceeds(): void
    {
        $result = $this->validator->validateNoGaps([]);

        // All years default to 0, so contract never "starts"
        $this->assertTrue($result['valid']);
    }

    /**
     * Test calculateOfferValue with empty offer array
     */
    public function testCalculateOfferValueWithEmptyArrayReturnsZero(): void
    {
        $result = $this->validator->calculateOfferValue([]);

        $this->assertEquals(0, $result['total']);
        $this->assertEquals(1, $result['years']); // Minimum of 1
        $this->assertEquals(0.0, $result['averagePerYear']);
    }

    // ============================================
    // RAISE PERCENTAGE BOUNDARY TESTS
    // ============================================

    /**
     * Test raise exactly at 10% (100 on 1000)
     */
    public function testValidateRaisesExactlyTenPercentSucceeds(): void
    {
        $offer = [
            'year1' => 1000,
            'year2' => 1100, // Exactly 10%
            'year3' => 0
        ];

        $result = $this->validator->validateRaises($offer, 0);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test raise one over 10% (101 on 1000)
     */
    public function testValidateRaisesOneOverTenPercentFails(): void
    {
        $offer = [
            'year1' => 1000,
            'year2' => 1101, // One over 10%
            'year3' => 0
        ];

        $result = $this->validator->validateRaises($offer, 0);

        $this->assertFalse($result['valid']);
    }

    /**
     * Test raise exactly at 12.5% (125 on 1000)
     */
    public function testValidateRaisesExactlyTwelvePointFivePercentSucceeds(): void
    {
        $offer = [
            'year1' => 1000,
            'year2' => 1125, // Exactly 12.5%
            'year3' => 0
        ];

        $result = $this->validator->validateRaises($offer, 3);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test raise one over 12.5% (126 on 1000)
     */
    public function testValidateRaisesOneOverTwelvePointFivePercentFails(): void
    {
        $offer = [
            'year1' => 1000,
            'year2' => 1126, // One over 12.5%
            'year3' => 0
        ];

        $result = $this->validator->validateRaises($offer, 3);

        $this->assertFalse($result['valid']);
    }

    /**
     * Test raise calculation with non-round number
     */
    public function testValidateRaisesWithNonRoundFirstYear(): void
    {
        $offer = [
            'year1' => 777, // Non-round
            'year2' => 854, // 777 * 1.10 = 854.7, floor = 854
            'year3' => 0
        ];

        $result = $this->validator->validateRaises($offer, 0);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test max raise is calculated from year1 only
     */
    public function testValidateRaisesMaxFromYear1Only(): void
    {
        // Max raise = 100 (10% of 1000)
        // Year 3 can be year2 + 100, not year2 * 1.10
        $offer = [
            'year1' => 1000,
            'year2' => 1100,
            'year3' => 1200, // +100 from year2, not +110
            'year4' => 1300, // +100 from year3
            'year5' => 1400, // +100 from year4
            'year6' => 1500  // +100 from year5
        ];

        $result = $this->validator->validateRaises($offer, 0);

        $this->assertTrue($result['valid']);
    }

    // ============================================
    // BIRD YEARS BOUNDARY TESTS
    // ============================================

    /**
     * Test with exactly 2 bird years (no bird rights)
     */
    public function testValidateRaisesWithTwoBirdYearsUsesTenPercent(): void
    {
        $offer = [
            'year1' => 1000,
            'year2' => 1110, // 11% - over 10%
            'year3' => 0
        ];

        $result = $this->validator->validateRaises($offer, 2);

        $this->assertFalse($result['valid']);
    }

    /**
     * Test with exactly 3 bird years (has bird rights)
     */
    public function testValidateRaisesWithThreeBirdYearsUsesTwelvePointFive(): void
    {
        $offer = [
            'year1' => 1000,
            'year2' => 1110, // 11% - under 12.5%
            'year3' => 0
        ];

        $result = $this->validator->validateRaises($offer, 3);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test with zero bird years
     */
    public function testValidateRaisesWithZeroBirdYearsUsesTenPercent(): void
    {
        $offer = [
            'year1' => 1000,
            'year2' => 1100, // Exactly 10%
            'year3' => 0
        ];

        $result = $this->validator->validateRaises($offer, 0);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test with negative bird years (edge case)
     */
    public function testValidateRaisesWithNegativeBirdYearsUsesTenPercent(): void
    {
        $offer = [
            'year1' => 1000,
            'year2' => 1100, // 10%
            'year3' => 0
        ];

        // Negative bird years should be treated as no bird rights
        $result = $this->validator->validateRaises($offer, -1);

        $this->assertTrue($result['valid']);
    }

    // ============================================
    // SALARY DECREASE EDGE CASES
    // ============================================

    /**
     * Test flat salary (no increase, no decrease)
     */
    public function testValidateSalaryDecreasesWithFlatSalarySucceeds(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 500,
            'year3' => 500,
            'year4' => 500,
            'year5' => 500
        ];

        $result = $this->validator->validateSalaryDecreases($offer);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test decrease by 1
     */
    public function testValidateSalaryDecreasesDecreaseByOneFails(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 499, // Decrease by 1
            'year3' => 0
        ];

        $result = $this->validator->validateSalaryDecreases($offer);

        $this->assertFalse($result['valid']);
    }

    /**
     * Test valid contract ending in zero
     */
    public function testValidateSalaryDecreasesContractEndingInZeroSucceeds(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 600,
            'year3' => 0, // Contract ends
            'year4' => 0,
            'year5' => 0
        ];

        $result = $this->validator->validateSalaryDecreases($offer);

        $this->assertTrue($result['valid']);
    }

    // ============================================
    // MAX YEAR ONE BOUNDARY TESTS
    // ============================================

    /**
     * Test rookie exactly at max (1063)
     */
    public function testValidateMaxYearOneRookieAtMaxSucceeds(): void
    {
        $maxSalary = \ContractRules::getMaxContractSalary(0);
        $offer = ['year1' => $maxSalary];

        $result = $this->validator->validateMaximumYearOne($offer, 0);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test rookie one over max
     */
    public function testValidateMaxYearOneRookieOneOverMaxFails(): void
    {
        $maxSalary = \ContractRules::getMaxContractSalary(0);
        $offer = ['year1' => $maxSalary + 1];

        $result = $this->validator->validateMaximumYearOne($offer, 0);

        $this->assertFalse($result['valid']);
    }

    /**
     * Test 7-year veteran exactly at max (1275)
     */
    public function testValidateMaxYearOneSevenYearVetAtMaxSucceeds(): void
    {
        $maxSalary = \ContractRules::getMaxContractSalary(7);
        $offer = ['year1' => $maxSalary];

        $result = $this->validator->validateMaximumYearOne($offer, 7);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test 10+ year veteran exactly at max (1451)
     */
    public function testValidateMaxYearOneTenYearVetAtMaxSucceeds(): void
    {
        $maxSalary = \ContractRules::getMaxContractSalary(10);
        $offer = ['year1' => $maxSalary];

        $result = $this->validator->validateMaximumYearOne($offer, 10);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test with zero year1
     */
    public function testValidateMaxYearOneWithZeroSucceeds(): void
    {
        $offer = ['year1' => 0];

        $result = $this->validator->validateMaximumYearOne($offer, 0);

        $this->assertTrue($result['valid']);
    }

    // ============================================
    // CONTRACT GAP TESTS
    // ============================================

    /**
     * Test gap between year1 and year2
     */
    public function testValidateNoGapsGapInYear2Fails(): void
    {
        $offer = [
            'year1' => 0, // Contract never starts
            'year2' => 500, // Gap!
            'year3' => 0
        ];

        $result = $this->validator->validateNoGaps($offer);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('year 1', $result['error']);
        $this->assertStringContainsString('year 2', $result['error']);
    }

    /**
     * Test all years zero (no contract)
     */
    public function testValidateNoGapsAllZerosSucceeds(): void
    {
        $offer = [
            'year1' => 0,
            'year2' => 0,
            'year3' => 0,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0
        ];

        $result = $this->validator->validateNoGaps($offer);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test gap in middle of contract
     */
    public function testValidateNoGapsGapInMiddleFails(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 0,
            'year4' => 600, // Gap!
            'year5' => 0,
            'year6' => 0
        ];

        $result = $this->validator->validateNoGaps($offer);

        $this->assertFalse($result['valid']);
    }

    // ============================================
    // CALCULATE OFFER VALUE EDGE CASES
    // ============================================

    /**
     * Test single year contract
     */
    public function testCalculateOfferValueSingleYear(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 0,
            'year3' => 0
        ];

        $result = $this->validator->calculateOfferValue($offer);

        $this->assertEquals(500, $result['total']);
        $this->assertEquals(1, $result['years']);
        $this->assertEquals(500.0, $result['averagePerYear']);
    }

    /**
     * Test all zeros returns minimum 1 year
     */
    public function testCalculateOfferValueAllZerosReturnsOneYear(): void
    {
        $offer = [
            'year1' => 0,
            'year2' => 0,
            'year3' => 0
        ];

        $result = $this->validator->calculateOfferValue($offer);

        $this->assertEquals(0, $result['total']);
        $this->assertEquals(1, $result['years']); // Minimum of 1
        $this->assertEquals(0.0, $result['averagePerYear']);
    }

    /**
     * Test very large contract values
     */
    public function testCalculateOfferValueLargeValues(): void
    {
        $offer = [
            'year1' => 1000000,
            'year2' => 1100000,
            'year3' => 1200000,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0
        ];

        $result = $this->validator->calculateOfferValue($offer);

        $this->assertEquals(3300000, $result['total']);
        $this->assertEquals(3, $result['years']);
        $this->assertEquals(1100000.0, $result['averagePerYear']);
    }

    /**
     * Test average calculation precision
     */
    public function testCalculateOfferValueAveragePrecision(): void
    {
        $offer = [
            'year1' => 100,
            'year2' => 100,
            'year3' => 100
        ];

        $result = $this->validator->calculateOfferValue($offer);

        $this->assertEquals(300, $result['total']);
        $this->assertEquals(3, $result['years']);
        $this->assertEqualsWithDelta(100.0, $result['averagePerYear'], 0.001);
    }

    /**
     * Test non-divisible average
     */
    public function testCalculateOfferValueNonDivisibleAverage(): void
    {
        $offer = [
            'year1' => 100,
            'year2' => 200,
            'year3' => 300
        ];

        $result = $this->validator->calculateOfferValue($offer);

        $this->assertEquals(600, $result['total']);
        $this->assertEquals(3, $result['years']);
        $this->assertEquals(200.0, $result['averagePerYear']);
    }

    // ============================================
    // DATA PROVIDER TESTS
    // ============================================

    /**     */
    #[DataProvider('raisePercentageBoundaryProvider')]
    public function testRaisePercentageBoundaries(
        int $year1,
        int $year2,
        int $birdYears,
        bool $expectedValid
    ): void {
        $offer = ['year1' => $year1, 'year2' => $year2, 'year3' => 0];
        $result = $this->validator->validateRaises($offer, $birdYears);

        $this->assertEquals($expectedValid, $result['valid']);
    }

    public static function raisePercentageBoundaryProvider(): array
    {
        return [
            '10% exactly valid without bird' => [1000, 1100, 0, true],
            '10.1% invalid without bird' => [1000, 1101, 0, false],
            '12.5% exactly valid with bird' => [1000, 1125, 3, true],
            '12.6% invalid with bird' => [1000, 1126, 3, false],
            '0% raise valid' => [1000, 1000, 0, true],
            'negative raise valid (not a decrease)' => [1000, 999, 0, true], // Handled by validateSalaryDecreases
        ];
    }

    /**     */
    #[DataProvider('yearsExperienceMaxProvider')]
    public function testMaxContractByExperience(int $yearsExperience): void
    {
        $maxSalary = \ContractRules::getMaxContractSalary($yearsExperience);
        $offer = ['year1' => $maxSalary];

        $resultAtMax = $this->validator->validateMaximumYearOne($offer, $yearsExperience);
        $this->assertTrue($resultAtMax['valid']);

        $offer['year1'] = $maxSalary + 1;
        $resultOverMax = $this->validator->validateMaximumYearOne($offer, $yearsExperience);
        $this->assertFalse($resultOverMax['valid']);
    }

    public static function yearsExperienceMaxProvider(): array
    {
        return [
            'rookie' => [0],
            '1 year' => [1],
            '3 years' => [3],
            '5 years' => [5],
            '7 years' => [7],
            '10 years' => [10],
            '15 years' => [15],
        ];
    }
}
