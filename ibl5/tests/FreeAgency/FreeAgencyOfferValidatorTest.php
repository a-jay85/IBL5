<?php

declare(strict_types=1);

namespace Tests\FreeAgency;

use PHPUnit\Framework\TestCase;
use FreeAgency\FreeAgencyOfferValidator;
use FreeAgency\OfferType;
use FreeAgency\Contracts\FreeAgencyRepositoryInterface;
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
        // 10% raise limit on 500 = floor(500 * 0.1) = 50
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
        // 12.5% of 500 = floor(62.5) = 62, so max year2 = 562
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
        // 12.5% of 500 = floor(62.5) = 62, max year2 = 562
        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 500,
            'offer2' => 563, // 63 raise, exceeds 62
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
    // PENDING MLE/LLE OFFERS (ONE-AT-A-TIME RULE)
    //
    // Bug fix: a GM could submit multiple pending MLE/LLE offers because
    // the `HasMLE` / `HasLLE` flags on ibl_team_info only clear when admin
    // processes signings. The validator must also consult the pending
    // offers in ibl_fa_offers to enforce the "one pending offer at a time"
    // rule. Pending offers to the current player are excluded so a team
    // can still overwrite their own existing offer to the same player.
    // ================================================================

    public function testRejectsMLEWhenTeamAlreadyHasPendingMLEOfferToDifferentPlayer(): void
    {
        $team = $this->createTeamStub(hasMLE: 1, teamID: 7);
        $repository = $this->createRepositoryStub(pendingMle: true);
        $validator = new FreeAgencyOfferValidator($team, $repository, playerId: 99);

        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 450,
            'offerType' => OfferType::MLE_1_YEAR,
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('pending Mid-Level Exception offer', $result['error']);
    }

    public function testAcceptsMLEOverwriteToSamePlayer(): void
    {
        // Repository excludes the current player when checking — so a team
        // overwriting its own pending MLE offer to the same player is legal.
        $team = $this->createTeamStub(hasMLE: 1, teamID: 7);
        $repository = $this->createRepositoryStub(pendingMle: false);
        $validator = new FreeAgencyOfferValidator($team, $repository, playerId: 42);

        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 450,
            'offerType' => OfferType::MLE_2_YEAR,
            'offer2' => 495,
        ]));

        $this->assertTrue($result['valid']);
    }

    public function testRejectsLLEWhenTeamAlreadyHasPendingLLEOfferToDifferentPlayer(): void
    {
        $team = $this->createTeamStub(hasLLE: 1, teamID: 3);
        $repository = $this->createRepositoryStub(pendingLle: true);
        $validator = new FreeAgencyOfferValidator($team, $repository, playerId: 501);

        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 145,
            'offerType' => OfferType::LOWER_LEVEL_EXCEPTION,
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('pending Lower-Level Exception offer', $result['error']);
    }

    public function testAcceptsLLEOverwriteToSamePlayer(): void
    {
        $team = $this->createTeamStub(hasLLE: 1, teamID: 3);
        $repository = $this->createRepositoryStub(pendingLle: false);
        $validator = new FreeAgencyOfferValidator($team, $repository, playerId: 501);

        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 145,
            'offerType' => OfferType::LOWER_LEVEL_EXCEPTION,
        ]));

        $this->assertTrue($result['valid']);
    }

    public function testPendingMLEDoesNotBlockLLEOffer(): void
    {
        // MLE and LLE are independent exceptions. A pending MLE must not
        // block a LLE offer (and vice versa in the inverse test below).
        $team = $this->createTeamStub(hasMLE: 1, hasLLE: 1, teamID: 7);
        $repository = $this->createRepositoryStub(pendingMle: true, pendingLle: false);
        $validator = new FreeAgencyOfferValidator($team, $repository, playerId: 88);

        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 145,
            'offerType' => OfferType::LOWER_LEVEL_EXCEPTION,
        ]));

        $this->assertTrue($result['valid']);
    }

    public function testPendingLLEDoesNotBlockMLEOffer(): void
    {
        $team = $this->createTeamStub(hasMLE: 1, hasLLE: 1, teamID: 7);
        $repository = $this->createRepositoryStub(pendingMle: false, pendingLle: true);
        $validator = new FreeAgencyOfferValidator($team, $repository, playerId: 88);

        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 450,
            'offerType' => OfferType::MLE_1_YEAR,
        ]));

        $this->assertTrue($result['valid']);
    }

    public function testAcceptedMLEBlocksAnyFurtherMLEOfferRegardlessOfPendingCheck(): void
    {
        // Rule 2: once an MLE has been consumed (hasMLE=0), no new MLE offer
        // is allowed for the remainder of the FA phase — even if the pending
        // offers table is empty.
        $team = $this->createTeamStub(hasMLE: 0, teamID: 7);
        $repository = $this->createRepositoryStub(pendingMle: false);
        $validator = new FreeAgencyOfferValidator($team, $repository, playerId: 1);

        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 450,
            'offerType' => OfferType::MLE_1_YEAR,
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('already used', $result['error']);
    }

    public function testAcceptedLLEBlocksAnyFurtherLLEOfferRegardlessOfPendingCheck(): void
    {
        $team = $this->createTeamStub(hasLLE: 0, teamID: 7);
        $repository = $this->createRepositoryStub(pendingLle: false);
        $validator = new FreeAgencyOfferValidator($team, $repository, playerId: 1);

        $result = $validator->validateOffer($this->buildOffer([
            'offer1' => 145,
            'offerType' => OfferType::LOWER_LEVEL_EXCEPTION,
        ]));

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('already used', $result['error']);
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

    private function createTeamStub(int $hasMLE = 0, int $hasLLE = 0, int $teamID = 1): Team
    {
        $team = $this->createStub(Team::class);
        $team->hasMLE = $hasMLE;
        $team->hasLLE = $hasLLE;
        $team->teamID = $teamID;
        return $team;
    }

    /**
     * @return FreeAgencyRepositoryInterface&\PHPUnit\Framework\MockObject\Stub
     */
    private function createRepositoryStub(bool $pendingMle = false, bool $pendingLle = false): FreeAgencyRepositoryInterface
    {
        $repository = $this->createStub(FreeAgencyRepositoryInterface::class);
        $repository->method('hasPendingMleOffer')->willReturn($pendingMle);
        $repository->method('hasPendingLleOffer')->willReturn($pendingLle);
        return $repository;
    }
}
