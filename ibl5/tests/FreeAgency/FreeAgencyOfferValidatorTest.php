<?php

use PHPUnit\Framework\TestCase;
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
    private $mockDb;
    private $validator;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->validator = new FreeAgencyOfferValidator($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->validator = null;
        $this->mockDb = null;
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
        $offerData['year1Max'] = \FreeAgency\FreeAgencyNegotiationHelper::getMaxContractSalary(0);

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
    public function testRejectsSalaryDecrease(): void
    {
        // Arrange
        $offerData = $this->createValidOffer();
        $offerData['offer1'] = 1000;
        $offerData['offer2'] = 900; // Decrease not allowed

        // Act
        $result = $this->validator->validateOffer($offerData);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('cannot decrease salary', $result['error']);
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
            'year1Max' => \FreeAgency\FreeAgencyNegotiationHelper::getMaxContractSalary(0),
            'amendedCapSpaceYear1' => 1000,
        ];
    }
}
