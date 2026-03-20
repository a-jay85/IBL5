<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use FreeAgency\FreeAgencyOfferValidator;

/**
 * Tests for FreeAgencyOfferValidator
 * 
 * Validates contract offer rules through public API:
 * - Minimum salary requirements
 * - Cap space constraints (soft and hard cap)
 * - Maximum contract values
 * - Legal raise percentages
 * - No salary decreases
 */
class FreeAgencyOfferValidatorTest extends TestCase
{
    private $validator;

    protected function setUp(): void
    {
        $this->validator = new FreeAgencyOfferValidator(null);
    }

    protected function tearDown(): void
    {
        $this->validator = null;
    }

    /**
     * @group validation
     * @group minimum-salary
     */
    public function testRejectsOfferWithZeroFirstYear(): void
    {
        // Arrange
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 0;

        // Act
        $result = $this->validator->validateOffer($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('greater than zero', $result['error']);
    }

    /**
     * @group validation
     * @group minimum-salary
     */
    public function testRejectsOfferBelowVeteransMinimum(): void
    {
        // Arrange
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 30;
        $offerData['vetmin'] = 35;

        // Act
        $result = $this->validator->validateOffer($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString("Veteran's Minimum", $result['error']);
    }

    /**
     * @group validation
     * @group cap-space
     */
    public function testRejectsOfferOverHardCap(): void
    {
        // Arrange
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 3000;
        $offerData['amendedCapSpaceYear1'] = 500; // Hard cap = 500 + 2000 = 2500

        // Act
        $result = $this->validator->validateOffer($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('hard cap', $result['error']);
    }

    /**
     * @group validation
     * @group cap-space
     */
    public function testRejectsOfferOverSoftCapWithoutBirdRights(): void
    {
        // Arrange
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 600;
        $offerData['amendedCapSpaceYear1'] = 500;
        $offerData['birdYears'] = 2; // Less than 3, no Bird Rights
        $offerData['offerType'] = 0; // Not using exception

        // Act
        $result = $this->validator->validateOffer($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('soft cap', $result['error']);
    }

    /**
     * @group validation
     * @group cap-space
     */
    public function testAcceptsOfferWithinAllConstraintsWithBirdRights(): void
    {
        // Arrange
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 600;
        $offerData['offer2'] = 660; // Must be >= offer1
        $offerData['amendedCapSpaceYear1'] = 1000; // Hard cap = 1000 + 2000 = 3000
        $offerData['year1Max'] = 1500; // Max contract is higher than offer
        $offerData['birdYears'] = 3; // Bird Rights, can exceed soft cap but not hard cap

        // Act
        $result = $this->validator->validateOffer($offerData);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group max-contract
     */
    public function testRejectsOfferOverMaximumValue(): void
    {
        // Arrange
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 1500;
        $offerData['year1Max'] = \ContractRules::getMaxContractSalary(0);

        // Act
        $result = $this->validator->validateOffer($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('maximum allowed', $result['error']);
    }

    /**
     * @group validation
     * @group raises
     */
    public function testRejectsIllegalRaiseWithoutBirdRights(): void
    {
        // Arrange
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 1000;
        $offerData['offer2'] = 1150; // 15% raise, max is 10%
        $offerData['birdYears'] = 2;

        // Act
        $result = $this->validator->validateOffer($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('larger raise than is permitted', $result['error']);
    }

    /**
     * @group validation
     * @group raises
     */
    public function testAcceptsLegalRaiseWithoutBirdRights(): void
    {
        // Arrange
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 1000;
        $offerData['offer2'] = 1100; // 10% raise - legal
        $offerData['birdYears'] = 2;

        // Act
        $result = $this->validator->validateOffer($offerData);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group raises
     */
    public function testAcceptsLegalRaiseWithBirdRights(): void
    {
        // Arrange
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 1000;
        $offerData['offer2'] = 1125; // 12.5% raise - legal with Bird Rights
        $offerData['birdYears'] = 3;

        // Act
        $result = $this->validator->validateOffer($offerData);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group decreases
     */
    public function testAllowsZeroForUnusedYears(): void
    {
        // Arrange
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 1000;
        $offerData['offer2'] = 1100;
        $offerData['offer3'] = 0; // Zero is OK (contract ends)
        $offerData['offer4'] = 0;

        // Act
        $result = $this->validator->validateOffer($offerData);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group mle-lle
     */
    public function testRejectsMLEOfferWhenTeamOutOfMLE(): void
    {
        // Arrange
        $mockTeam = (object)[
            'hasMLE' => 0, // Team has already used MLE
            'hasLLE' => 1
        ];
        $validator = new FreeAgencyOfferValidator($mockTeam);
        $offerData = $this->createValidOffer();
        $offerData['offerType'] = \FreeAgency\OfferType::MLE_1_YEAR; // MLE offer

        // Act
        $result = $validator->validateOffer($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Mid-Level Exception', $result['error']);
        $this->assertStringContainsString('already used', $result['error']);
    }

    /**
     * @group validation
     * @group mle-lle
     */
    public function testRejectsLLEOfferWhenTeamOutOfLLE(): void
    {
        // Arrange
        $mockTeam = (object)[
            'hasMLE' => 1,
            'hasLLE' => 0 // Team has already used LLE
        ];
        $validator = new FreeAgencyOfferValidator($mockTeam);
        $offerData = $this->createValidOffer();
        $offerData['offerType'] = \FreeAgency\OfferType::LOWER_LEVEL_EXCEPTION; // LLE offer

        // Act
        $result = $validator->validateOffer($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Lower-Level Exception', $result['error']);
        $this->assertStringContainsString('already used', $result['error']);
    }

    /**
     * @group validation
     * @group mle-lle
     */
    public function testAcceptsMLEOfferWhenTeamHasMLE(): void
    {
        // Arrange
        $mockTeam = (object)[
            'hasMLE' => 1, // Team has MLE available
            'hasLLE' => 1
        ];
        $validator = new FreeAgencyOfferValidator($mockTeam);
        $offerData = $this->createValidOffer();
        $offerData['offerType'] = \FreeAgency\OfferType::MLE_1_YEAR; // MLE offer

        // Act
        $result = $validator->validateOffer($offerData);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group mle-lle
     */
    public function testAcceptsLLEOfferWhenTeamHasLLE(): void
    {
        // Arrange
        $mockTeam = (object)[
            'hasMLE' => 1,
            'hasLLE' => 1 // Team has LLE available
        ];
        $validator = new FreeAgencyOfferValidator($mockTeam);
        $offerData = $this->createValidOffer();
        $offerData['offerType'] = \FreeAgency\OfferType::LOWER_LEVEL_EXCEPTION; // LLE offer

        // Act
        $result = $validator->validateOffer($offerData);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group mle-lle
     */
    public function testSkipsMLECheckWhenNoTeamProvided(): void
    {
        // Arrange
        $validator = new FreeAgencyOfferValidator(null); // No team
        $offerData = $this->createValidOffer();
        $offerData['offerType'] = \FreeAgency\OfferType::MLE_1_YEAR; // MLE offer

        // Act
        $result = $validator->validateOffer($offerData);

        // Assert - Should not be rejected for MLE check (passes through)
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group mle-lle
     */
    public function testSkipsMLECheckWhenCustomOfferType(): void
    {
        // Arrange
        $mockTeam = (object)[
            'hasMLE' => 0, // Team out of MLE
            'hasLLE' => 1
        ];
        $validator = new FreeAgencyOfferValidator($mockTeam);
        $offerData = $this->createValidOffer();
        $offerData['offerType'] = 0; // Custom offer, not MLE

        // Act
        $result = $validator->validateOffer($offerData);

        // Assert - Should not check MLE for custom offers
        $this->assertTrue($result['valid']);
    }

    /**
     * Helper to create a valid offer
     *
     * @return array<string, mixed>
     */
    private function createValidOffer(): array
    {
        return [
            'offer1' => 500,
            'offer2' => 550,
            'offer3' => 0,
            'offer4' => 0,
            'offer5' => 0,
            'offer6' => 0,
            'birdYears' => 3,
            'vetmin' => 35,
            'year1Max' => \ContractRules::getMaxContractSalary(0),
            'amendedCapSpaceYear1' => 1000,
            'offerType' => 0,
        ];
    }

    // --- Merged from FreeAgencyOfferValidatorEdgeCaseTest ---

    // ============================================
    // BIRD RIGHTS BOUNDARY TESTS
    // ============================================

    /**
     * Test offer with exactly 3 bird years (threshold for bird rights)
     */
    public function testAcceptsOfferWithExactlyThreeBirdYears(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['birdYears'] = 3; // Exactly at threshold
        $offerData['offer1'] = 600;
        $offerData['offer2'] = 675; // 12.5% raise - legal with bird rights

        $result = $this->validator->validateOffer($offerData);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test offer with 2 bird years (just under threshold)
     */
    public function testRejectsExcessiveRaiseWithTwoBirdYears(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['birdYears'] = 2; // Just under threshold
        $offerData['offer1'] = 600;
        $offerData['offer2'] = 675; // 12.5% raise - illegal without bird rights

        $result = $this->validator->validateOffer($offerData);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('raise', $result['error']);
    }

    /**
     * Test exactly 10% raise without bird rights (boundary)
     */
    public function testAcceptsExactlyTenPercentRaiseWithoutBirdRights(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['birdYears'] = 2;
        $offerData['offer1'] = 1000;
        $offerData['offer2'] = 1100; // Exactly 10%

        $result = $this->validator->validateOffer($offerData);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test one over 10% raise without bird rights
     */
    public function testRejectsOneOverTenPercentRaiseWithoutBirdRights(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['birdYears'] = 2;
        $offerData['offer1'] = 1000;
        $offerData['offer2'] = 1101; // One over 10%

        $result = $this->validator->validateOffer($offerData);

        $this->assertFalse($result['valid']);
    }

    /**
     * Test exactly 12.5% raise with bird rights (boundary)
     */
    public function testAcceptsExactlyTwelvePointFivePercentRaiseWithBirdRights(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['birdYears'] = 3;
        $offerData['offer1'] = 1000;
        $offerData['offer2'] = 1125; // Exactly 12.5%

        $result = $this->validator->validateOffer($offerData);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test one over 12.5% raise with bird rights
     */
    public function testRejectsOneOverTwelvePointFivePercentRaiseWithBirdRights(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['birdYears'] = 3;
        $offerData['offer1'] = 1000;
        $offerData['offer2'] = 1126; // One over 12.5%

        $result = $this->validator->validateOffer($offerData);

        $this->assertFalse($result['valid']);
    }

    // ============================================
    // HARD CAP BOUNDARY TESTS
    // ============================================

    /**
     * Test offer exactly at hard cap limit
     */
    public function testAcceptsOfferExactlyAtHardCap(): void
    {
        $offerData = $this->createValidOffer();
        // Hard cap = amendedCapSpaceYear1 + (HARD_CAP_MAX - SOFT_CAP_MAX)
        // Hard cap = 500 + 2000 = 2500
        $offerData['amendedCapSpaceYear1'] = 500;
        $offerData['offer1'] = 2500; // Exactly at hard cap
        $offerData['birdYears'] = 3; // Need bird rights to exceed soft cap
        $offerData['year1Max'] = 3000;

        $result = $this->validator->validateOffer($offerData);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test offer one over hard cap limit
     */
    public function testRejectsOfferOneOverHardCap(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['amendedCapSpaceYear1'] = 500;
        $offerData['offer1'] = 2501; // One over hard cap
        $offerData['birdYears'] = 3;
        $offerData['year1Max'] = 3000;

        $result = $this->validator->validateOffer($offerData);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('hard cap', $result['error']);
    }

    /**
     * Test offer exactly at soft cap without bird rights
     */
    public function testAcceptsOfferExactlyAtSoftCapWithoutBirdRights(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['amendedCapSpaceYear1'] = 800;
        $offerData['offer1'] = 800; // Exactly at soft cap
        $offerData['birdYears'] = 2;
        $offerData['offerType'] = 0;

        $result = $this->validator->validateOffer($offerData);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test offer one over soft cap without bird rights
     */
    public function testRejectsOfferOneOverSoftCapWithoutBirdRights(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['amendedCapSpaceYear1'] = 800;
        $offerData['offer1'] = 801; // One over soft cap
        $offerData['birdYears'] = 2;
        $offerData['offerType'] = 0;

        $result = $this->validator->validateOffer($offerData);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('soft cap', $result['error']);
    }

    // ============================================
    // MLE/LLE AVAILABILITY TESTS
    // ============================================

    /**
     * Test MLE check with integer 1 for hasMLE
     */
    public function testAcceptsMLEWithIntOne(): void
    {
        $mockTeam = (object)[
            'hasMLE' => 1,
            'hasLLE' => 1
        ];
        $validator = new FreeAgencyOfferValidator($mockTeam);
        $offerData = $this->createValidOffer();
        $offerData['offerType'] = \FreeAgency\OfferType::MLE_1_YEAR;

        $result = $validator->validateOffer($offerData);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test MLE check with integer 0 for hasMLE
     */
    public function testRejectsMLEWithIntZero(): void
    {
        $mockTeam = (object)[
            'hasMLE' => 0,
            'hasLLE' => 1
        ];
        $validator = new FreeAgencyOfferValidator($mockTeam);
        $offerData = $this->createValidOffer();
        $offerData['offerType'] = \FreeAgency\OfferType::MLE_1_YEAR;

        $result = $validator->validateOffer($offerData);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Mid-Level Exception', $result['error']);
    }

    /**
     * Test LLE check with integer 1 for hasLLE
     */
    public function testAcceptsLLEWithIntOne(): void
    {
        $mockTeam = (object)[
            'hasMLE' => 1,
            'hasLLE' => 1
        ];
        $validator = new FreeAgencyOfferValidator($mockTeam);
        $offerData = $this->createValidOffer();
        $offerData['offerType'] = \FreeAgency\OfferType::LOWER_LEVEL_EXCEPTION;

        $result = $validator->validateOffer($offerData);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test LLE check with integer 0 for hasLLE
     */
    public function testRejectsLLEWithIntZero(): void
    {
        $mockTeam = (object)[
            'hasMLE' => 1,
            'hasLLE' => 0
        ];
        $validator = new FreeAgencyOfferValidator($mockTeam);
        $offerData = $this->createValidOffer();
        $offerData['offerType'] = \FreeAgency\OfferType::LOWER_LEVEL_EXCEPTION;

        $result = $validator->validateOffer($offerData);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Lower-Level Exception', $result['error']);
    }

    // ============================================
    // VETERAN MINIMUM BOUNDARY TESTS
    // ============================================

    /**
     * Test offer exactly at veteran minimum
     */
    public function testAcceptsOfferExactlyAtVeteranMinimum(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['vetmin'] = 50;
        $offerData['offer1'] = 50; // Exactly at vet min
        $offerData['offer2'] = 56; // Valid raise (50 + floor(50 * 0.125) = 56)

        $result = $this->validator->validateOffer($offerData);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test offer one below veteran minimum
     */
    public function testRejectsOfferOneBelowVeteranMinimum(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['vetmin'] = 50;
        $offerData['offer1'] = 49; // One below

        $result = $this->validator->validateOffer($offerData);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString("Veteran's Minimum", $result['error']);
    }

    // ============================================
    // CONTRACT CONTINUITY TESTS
    // ============================================

    /**
     * Test all six years filled (max contract length)
     */
    public function testAcceptsFullSixYearContract(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 500;
        $offerData['offer2'] = 550;
        $offerData['offer3'] = 600;
        $offerData['offer4'] = 650;
        $offerData['offer5'] = 700;
        $offerData['offer6'] = 750;

        $result = $this->validator->validateOffer($offerData);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test gap in contract years (year 3 is 0, year 4 is not)
     */
    public function testRejectsGapInContractYears(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 500;
        $offerData['offer2'] = 550;
        $offerData['offer3'] = 0;
        $offerData['offer4'] = 650; // Gap!
        $offerData['offer5'] = 0;
        $offerData['offer6'] = 0;

        $result = $this->validator->validateOffer($offerData);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('gaps', $result['error']);
    }

    /**
     * Test gap at end of contract (year 5 is 0, year 6 is not)
     */
    public function testRejectsGapAtEndOfContract(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 500;
        $offerData['offer2'] = 550;
        $offerData['offer3'] = 600;
        $offerData['offer4'] = 650;
        $offerData['offer5'] = 0;
        $offerData['offer6'] = 750; // Gap!

        $result = $this->validator->validateOffer($offerData);

        $this->assertFalse($result['valid']);
    }

    // ============================================
    // MAXIMUM CONTRACT BOUNDARY TESTS
    // ============================================

    /**
     * Test offer exactly at maximum contract value
     */
    public function testAcceptsOfferExactlyAtMaximumContract(): void
    {
        $maxContract = \ContractRules::getMaxContractSalary(10); // 10-year vet
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = $maxContract;
        $offerData['year1Max'] = $maxContract;
        $offerData['amendedCapSpaceYear1'] = 5000; // Enough cap space

        $result = $this->validator->validateOffer($offerData);

        $this->assertTrue($result['valid']);
    }

    /**
     * Test offer one over maximum contract value
     */
    public function testRejectsOfferOneOverMaximumContract(): void
    {
        $maxContract = \ContractRules::getMaxContractSalary(10);
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = $maxContract + 1;
        $offerData['year1Max'] = $maxContract;
        $offerData['amendedCapSpaceYear1'] = 5000;

        $result = $this->validator->validateOffer($offerData);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('maximum allowed', $result['error']);
    }

    // ============================================
    // NEGATIVE VALUE TESTS
    // ============================================

    /**
     * Test negative offer amount treated as invalid
     */
    public function testRejectsNegativeOfferAmount(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = -100;

        $result = $this->validator->validateOffer($offerData);

        // Negative is less than vet min, so should fail
        $this->assertFalse($result['valid']);
    }

    /**
     * Test negative cap space handling
     */
    public function testHandlesNegativeCapSpace(): void
    {
        $offerData = $this->createValidOffer();
        $offerData['amendedCapSpaceYear1'] = -500; // Over the cap
        $offerData['offer1'] = 500;
        $offerData['birdYears'] = 2;
        $offerData['offerType'] = 0;

        $result = $this->validator->validateOffer($offerData);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('soft cap', $result['error']);
    }

    // ============================================
    // DATA PROVIDER TESTS
    // ============================================

    #[DataProvider('birdYearsRaiseProvider')]
    public function testBirdYearsAffectsMaxRaise(
        int $birdYears,
        int $offer1,
        int $offer2,
        bool $expectedValid
    ): void {
        $offerData = $this->createValidOffer();
        $offerData['birdYears'] = $birdYears;
        $offerData['offer1'] = $offer1;
        $offerData['offer2'] = $offer2;

        $result = $this->validator->validateOffer($offerData);

        $this->assertEquals($expectedValid, $result['valid']);
    }

    public static function birdYearsRaiseProvider(): array
    {
        return [
            '0 bird years, 10% raise valid' => [0, 1000, 1100, true],
            '0 bird years, 11% raise invalid' => [0, 1000, 1110, false],
            '2 bird years, 10% raise valid' => [2, 1000, 1100, true],
            '2 bird years, 11% raise invalid' => [2, 1000, 1110, false],
            '3 bird years, 12.5% raise valid' => [3, 1000, 1125, true],
            '3 bird years, 13% raise invalid' => [3, 1000, 1130, false],
            '5 bird years, 12.5% raise valid' => [5, 1000, 1125, true],
            '5 bird years, 13% raise invalid' => [5, 1000, 1130, false],
        ];
    }
}
