<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use PHPUnit\Framework\TestCase;
use FreeAgency\FreeAgencyOfferValidator;
use FreeAgency\OfferType;
use Team\Team;

/**
 * Tests for FreeAgencyOfferValidator — all 7 validation rules from original freeagentoffer.php.
 *
 * Original validation order:
 * 1. Year 1 must be > 0
 * 2. Year 1 must be >= veteran minimum
 * 3. Hard cap check (softCap + 2000)
 * 4. Soft cap check (unless Bird Rights or MLE/LLE)
 * 5. Year 1 must be <= max contract
 * 6. Year-to-year raises must not exceed 10% (or 12.5% with Bird Rights)
 * 7. No salary decreases (except to $0 termination)
 */
class FreeAgencyOfferValidatorTest extends TestCase
{
    // ================================================================
    // VALID OFFERS
    // ================================================================

    public function testValidSingleYearOffer(): void
    {
        $validator = new FreeAgencyOfferValidator();
        $result = $validator->validateOffer($this->buildOffer(['offer1' => 500]));

        $this->assertTrue($result['valid']);
    }

    public function testValidMultiYearOfferWithAllowedRaises(): void
    {
        $validator = new FreeAgencyOfferValidator();
        // 10% raise limit on 500 = round(500 * 0.1) = 50
        // So max year 2 = 550, max year 3 = 600
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 500, 'offer2' => 550, 'offer3' => 600,
        ]));

        $this->assertTrue($result['valid']);
    }

    // ================================================================
    // RULE 1: Year 1 > 0
    // ================================================================

    public function testRejectsZeroFirstYear(): void
    {
        $validator = new FreeAgencyOfferValidator();
        $result = $validator->validateOffer($this->buildOffer(['offer1' => 0]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('zero', $result['error']);
    }

    // ================================================================
    // RULE 2: Year 1 >= Veteran Minimum
    // ================================================================

    public function testRejectsOfferBelowVeteranMinimum(): void
    {
        $validator = new FreeAgencyOfferValidator();
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 50,
            'vetmin' => 70,
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Minimum', $result['error']);
    }

    public function testAcceptsOfferAtExactVeteranMinimum(): void
    {
        $validator = new FreeAgencyOfferValidator();
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 70,
            'vetmin' => 70,
        ]));

        $this->assertTrue($result['valid']);
    }

    // ================================================================
    // RULE 3: Hard Cap (softCapSpace + 2000)
    // ================================================================

    public function testRejectsOfferExceedingHardCap(): void
    {
        $validator = new FreeAgencyOfferValidator();
        // amendedCapSpaceYear1 = 100, hard cap = 100 + 2000 = 2100
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 2200,
            'amendedCapSpaceYear1' => 100,
            'year1Max' => 5000, // Don't trigger max check
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('hard cap', $result['error']);
    }

    public function testAcceptsOfferAtExactHardCap(): void
    {
        $validator = new FreeAgencyOfferValidator();
        // amendedCapSpaceYear1 = 100, hard cap = 2100
        // Use bird rights to bypass soft cap check
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 2100,
            'amendedCapSpaceYear1' => 100,
            'year1Max' => 5000,
            'birdYears' => 3,
        ]));

        $this->assertTrue($result['valid']);
    }

    // ================================================================
    // RULE 4: Soft Cap (unless Bird Rights or MLE/LLE)
    // ================================================================

    public function testRejectsCustomOfferExceedingSoftCapWithoutBirdRights(): void
    {
        $validator = new FreeAgencyOfferValidator();
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 600,
            'amendedCapSpaceYear1' => 500,
            'birdYears' => 0,
            'offerType' => OfferType::CUSTOM,
            'year1Max' => 5000,
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('soft cap', $result['error']);
    }

    public function testBirdRightsBypassesSoftCapCheck(): void
    {
        $validator = new FreeAgencyOfferValidator();
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 600,
            'amendedCapSpaceYear1' => 500,
            'birdYears' => 3, // Bird Rights
            'offerType' => OfferType::CUSTOM,
            'year1Max' => 5000,
        ]));

        $this->assertTrue($result['valid']);
    }

    public function testMLEBypassesSoftCapCheck(): void
    {
        $team = $this->createTeamStub(hasMLE: 1);
        $validator = new FreeAgencyOfferValidator($team);
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 450,
            'amendedCapSpaceYear1' => 100, // Under soft cap
            'birdYears' => 0,
            'offerType' => OfferType::MLE_3_YEAR,
            'year1Max' => 5000,
        ]));

        $this->assertTrue($result['valid']);
    }

    // ================================================================
    // RULE 5: Maximum Contract
    // ================================================================

    public function testRejectsOfferOverMaxContract(): void
    {
        $validator = new FreeAgencyOfferValidator();
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 1100,
            'year1Max' => 1063,
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('maximum', $result['error']);
    }

    public function testAcceptsOfferAtExactMaxContract(): void
    {
        $validator = new FreeAgencyOfferValidator();
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 1063,
            'year1Max' => 1063,
        ]));

        $this->assertTrue($result['valid']);
    }

    // ================================================================
    // RULE 6: Raises
    // Standard: 10% of Year 1 salary
    // Bird Rights (bird >= 3): 12.5% of Year 1 salary
    // ================================================================

    public function testRejectsRaiseExceedingStandardLimit(): void
    {
        $validator = new FreeAgencyOfferValidator();
        // 10% of 500 = round(50) = 50, so max year2 = 550
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 500,
            'offer2' => 600, // 100 raise, exceeds 50
            'birdYears' => 0,
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('raise', $result['error']);
    }

    public function testAcceptsRaiseAtExactStandardLimit(): void
    {
        $validator = new FreeAgencyOfferValidator();
        // 10% of 500 = 50, year2 max = 550
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 500,
            'offer2' => 550,
            'birdYears' => 0,
        ]));

        $this->assertTrue($result['valid']);
    }

    public function testBirdRightsAllows125PercentRaise(): void
    {
        $validator = new FreeAgencyOfferValidator();
        // 12.5% of 500 = round(62.5) = 63, so max year2 = 563
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 500,
            'offer2' => 562,
            'birdYears' => 3,
        ]));

        $this->assertTrue($result['valid']);
    }

    public function testBirdRightsRejectsRaiseOver125Percent(): void
    {
        $validator = new FreeAgencyOfferValidator();
        // 12.5% of 500 = 63, max year2 = 563
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 500,
            'offer2' => 564, // 64 raise, exceeds 63
            'birdYears' => 3,
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('raise', $result['error']);
    }

    public function testRaiseLimitAppliesConsecutivelyNotCumulatively(): void
    {
        $validator = new FreeAgencyOfferValidator();
        // Year 1: 500, max raise: 50
        // Year 2: 550, max year3 = 550 + 50 = 600
        // Year 3: 600 is exactly at limit
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 500,
            'offer2' => 550,
            'offer3' => 600,
            'birdYears' => 0,
        ]));

        $this->assertTrue($result['valid']);
    }

    /**
     * BUG REGRESSION TEST: Non-bird-rights team should NOT be able to offer 12.5% raises
     *
     * This test verifies the fix for the bug where GMs without Bird Rights could offer
     * contracts with a 12.5% raise. The team must use the 10% standard raise limit.
     */
    public function testRejectsNonBirdRightsTeamOfferingAbove10PercentRaise(): void
    {
        $validator = new FreeAgencyOfferValidator();
        // 10% of 500 = 50, so max year2 = 550
        // But this offers 11% raise (555), which exceeds the 10% limit for non-BR teams
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 500,
            'offer2' => 555, // 11% raise (55), exceeds 10% limit (50)
            'birdYears' => 0, // Explicitly no bird rights
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('raise', $result['error']);
    }

    // ================================================================
    // RULE 7: Salary Decreases
    // Original: Cannot decrease salary in later years (except to $0 termination)
    // ================================================================

    public function testRejectsSalaryDecrease(): void
    {
        $validator = new FreeAgencyOfferValidator();
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 500,
            'offer2' => 400, // Decrease
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('decrease', strtolower($result['error']));
    }

    public function testAllowsDecreaseToZeroAsTermination(): void
    {
        $validator = new FreeAgencyOfferValidator();
        // Offer 500 in year 1, 0 in year 2 = 1-year contract
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 500,
            'offer2' => 0,
        ]));

        $this->assertTrue($result['valid']);
    }

    public function testRejectsSalaryDecreaseInYear3(): void
    {
        $validator = new FreeAgencyOfferValidator();
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 500,
            'offer2' => 550,
            'offer3' => 540, // Decrease from 550
        ]));

        $this->assertFalse($result['valid']);
    }

    // ================================================================
    // GAP DETECTION
    // ================================================================

    public function testRejectsGapInContractYears(): void
    {
        $validator = new FreeAgencyOfferValidator();
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 500,
            'offer2' => 0,
            'offer3' => 600, // Gap
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('gap', strtolower($result['error']));
    }

    // ================================================================
    // MLE/LLE AVAILABILITY
    // ================================================================

    public function testRejectsMLEWhenAlreadyUsed(): void
    {
        $team = $this->createTeamStub(hasMLE: 0);
        $validator = new FreeAgencyOfferValidator($team);

        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 450,
            'offerType' => OfferType::MLE_3_YEAR,
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Mid-Level', $result['error']);
    }

    public function testAcceptsMLEWhenAvailable(): void
    {
        $team = $this->createTeamStub(hasMLE: 1);
        $validator = new FreeAgencyOfferValidator($team);

        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 450,
            'offerType' => OfferType::MLE_1_YEAR,
        ]));

        $this->assertTrue($result['valid']);
    }

    public function testRejectsLLEWhenAlreadyUsed(): void
    {
        $team = $this->createTeamStub(hasLLE: 0);
        $validator = new FreeAgencyOfferValidator($team);

        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 145,
            'offerType' => OfferType::LOWER_LEVEL_EXCEPTION,
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Lower-Level', $result['error']);
    }

    public function testAcceptsLLEWhenAvailable(): void
    {
        $team = $this->createTeamStub(hasLLE: 1);
        $validator = new FreeAgencyOfferValidator($team);

        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 145,
            'offerType' => OfferType::LOWER_LEVEL_EXCEPTION,
        ]));

        $this->assertTrue($result['valid']);
    }

    // ================================================================
    // HELPERS
    // ================================================================

    /**
     * @param array<string, int> $overrides
     * @return array<string, int>
     */
    private function buildOffer(array $overrides = []): array
    {
        return array_merge([
            'offer1' => 500,
            'offer2' => 0,
            'offer3' => 0,
            'offer4' => 0,
            'offer5' => 0,
            'offer6' => 0,
            'birdYears' => 0,
            'offerType' => OfferType::CUSTOM,
            'vetmin' => 35,
            'year1Max' => 1063,
            'amendedCapSpaceYear1' => 5000,
        ], $overrides);
    }

    private function createTeamStub(int $hasMLE = 0, int $hasLLE = 0): Team
    {
        $team = $this->createStub(Team::class);
        $team->hasMLE = $hasMLE;
        $team->hasLLE = $hasLLE;
        return $team;
    }
}
