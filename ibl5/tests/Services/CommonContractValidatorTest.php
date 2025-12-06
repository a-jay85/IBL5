<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
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

    // ==================== validateOfferAmounts Tests ====================

    /**
     * @group offer-amounts
     */
    public function testValidateOfferAmountsSucceedsWithValidOffer(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0
        ];

        $result = $this->validator->validateOfferAmounts($offer);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    /**
     * @group offer-amounts
     */
    public function testValidateOfferAmountsFailsWhenYear1IsZero(): void
    {
        $offer = [
            'year1' => 0,
            'year2' => 550,
            'year3' => 600
        ];

        $result = $this->validator->validateOfferAmounts($offer);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Year1', $result['error']);
        $this->assertStringContainsString('zero', $result['error']);
    }

    /**
     * @group offer-amounts
     */
    public function testValidateOfferAmountsFailsWhenYear2IsZero(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 0,
            'year3' => 600
        ];

        $result = $this->validator->validateOfferAmounts($offer);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Year2', $result['error']);
    }

    /**
     * @group offer-amounts
     */
    public function testValidateOfferAmountsFailsWhenYear3IsZero(): void
    {
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 0
        ];

        $result = $this->validator->validateOfferAmounts($offer);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Year3', $result['error']);
    }

    /**
     * @group offer-amounts
     */
    public function testValidateOfferAmountsSucceedsWithOptionalYearsZero(): void
    {
        // Year 4-6 can be zero - only 1-3 are required
        $offer = [
            'year1' => 500,
            'year2' => 550,
            'year3' => 600,
            'year4' => 0,
            'year5' => 0,
            'year6' => 0
        ];

        $result = $this->validator->validateOfferAmounts($offer);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    /**
     * @group offer-amounts
     */
    public function testValidateOfferAmountsFailsWithNegativeYear1(): void
    {
        $offer = [
            'year1' => -500,
            'year2' => 550,
            'year3' => 600
        ];

        $result = $this->validator->validateOfferAmounts($offer);

        $this->assertFalse($result['valid']);
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

        // Check amounts
        $result = $this->validator->validateOfferAmounts($offer);
        $this->assertTrue($result['valid']);

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
}
