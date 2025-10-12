<?php

use PHPUnit\Framework\TestCase;
use Extension\ExtensionValidator;

/**
 * Comprehensive tests for contract extension validation logic
 * 
 * Tests all validation rules from modules/Player/extension.php including:
 * - Zero contract amount validation
 * - Extension usage validation (per chunk and per season)
 * - Maximum offer validation
 * - Raise percentage validation (Bird vs non-Bird)
 * - Salary decrease validation
 */
class ExtensionValidationTest extends TestCase
{
    private $mockDb;
    private $extensionValidator;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->extensionValidator = new ExtensionValidator($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->extensionValidator = null;
        $this->mockDb = null;
    }

    /**
     * @group validation
     * @group zero-amounts
     */
    public function testRejectsZeroAmountInYear1()
    {
        // Arrange
        $offer = [
            'year1' => 0,
            'year2' => 500,
            'year3' => 500,
            'year4' => 0,
            'year5' => 0
        ];

        // Act
        $result = $this->extensionValidator->validateOfferAmounts($offer);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Year 1', $result['error']);
        $this->assertStringContainsString('zero', $result['error']);
    }

    /**
     * @group validation
     * @group zero-amounts
     */
    public function testRejectsZeroAmountInYear2()
    {
        // Arrange
        $offer = [
            'year1' => 500,
            'year2' => 0,
            'year3' => 500,
            'year4' => 0,
            'year5' => 0
        ];

        // Act
        $result = $this->extensionValidator->validateOfferAmounts($offer);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Year 2', $result['error']);
    }

    /**
     * @group validation
     * @group zero-amounts
     */
    public function testRejectsZeroAmountInYear3()
    {
        // Arrange
        $offer = [
            'year1' => 500,
            'year2' => 500,
            'year3' => 0,
            'year4' => 0,
            'year5' => 0
        ];

        // Act
        $result = $this->extensionValidator->validateOfferAmounts($offer);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Year 3', $result['error']);
    }

    /**
     * @group validation
     * @group zero-amounts
     */
    public function testAcceptsZeroAmountsInYears4And5()
    {
        // Arrange
        $offer = [
            'year1' => 500,
            'year2' => 500,
            'year3' => 500,
            'year4' => 0,
            'year5' => 0
        ];

        // Act
        $result = $this->extensionValidator->validateOfferAmounts($offer);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group extension-usage
     */
    public function testRejectsExtensionWhenAlreadyUsedThisSeason()
    {
        // Arrange
        $teamName = 'Test Team';
        $this->mockDb->setMockData([
            ['Used_Extension_This_Season' => 1, 'Used_Extension_This_Chunk' => 0]
        ]);

        // Act
        $result = $this->extensionValidator->validateExtensionEligibility($teamName);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('already used your extension for this season', $result['error']);
    }

    /**
     * @group validation
     * @group extension-usage
     */
    public function testRejectsExtensionWhenAlreadyUsedThisChunk()
    {
        // Arrange
        $teamName = 'Test Team';
        $this->mockDb->setMockData([
            ['Used_Extension_This_Season' => 0, 'Used_Extension_This_Chunk' => 1]
        ]);

        // Act
        $result = $this->extensionValidator->validateExtensionEligibility($teamName);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('already used your extension for this Chunk', $result['error']);
    }

    /**
     * @group validation
     * @group extension-usage
     */
    public function testAcceptsExtensionWhenNotYetUsed()
    {
        // Arrange
        $teamName = 'Test Team';
        $this->mockDb->setMockData([
            ['Used_Extension_This_Season' => 0, 'Used_Extension_This_Chunk' => 0]
        ]);

        // Act
        $result = $this->extensionValidator->validateExtensionEligibility($teamName);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group maximum-offer
     */
    public function testRejectsOfferOverMaximumFor0To6YearsExperience()
    {
        // Arrange
        $offer = ['year1' => 1200]; // Max is 1063 for 0-6 years
        $yearsExperience = 5;

        // Act
        $result = $this->extensionValidator->validateMaximumOffer($offer, $yearsExperience);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('over the maximum allowed', $result['error']);
    }

    /**
     * @group validation
     * @group maximum-offer
     */
    public function testAcceptsOfferAtMaximumFor0To6YearsExperience()
    {
        // Arrange
        $offer = ['year1' => 1063]; // Exactly at max for 0-6 years
        $yearsExperience = 5;

        // Act
        $result = $this->extensionValidator->validateMaximumOffer($offer, $yearsExperience);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group maximum-offer
     */
    public function testRejectsOfferOverMaximumFor7To9YearsExperience()
    {
        // Arrange
        $offer = ['year1' => 1300]; // Max is 1275 for 7-9 years
        $yearsExperience = 8;

        // Act
        $result = $this->extensionValidator->validateMaximumOffer($offer, $yearsExperience);

        // Assert
        $this->assertFalse($result['valid']);
    }

    /**
     * @group validation
     * @group maximum-offer
     */
    public function testAcceptsOfferAtMaximumFor10PlusYearsExperience()
    {
        // Arrange
        $offer = ['year1' => 1451]; // Max for 10+ years
        $yearsExperience = 12;

        // Act
        $result = $this->extensionValidator->validateMaximumOffer($offer, $yearsExperience);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group raises
     * @dataProvider invalidRaiseProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidRaiseProvider')]
    public function testRejectsIllegalRaises($offer, $birdYears, $expectedErrorYear)
    {
        // Act
        $result = $this->extensionValidator->validateRaises($offer, $birdYears);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString("Year $expectedErrorYear", $result['error']);
        $this->assertStringContainsString('larger raise than is permitted', $result['error']);
    }

    /**
     * @group validation
     * @group raises
     */
    public function testAcceptsLegalRaisesWithoutBirdRights()
    {
        // Arrange - 10% max raise without Bird rights
        $offer = [
            'year1' => 1000,
            'year2' => 1100, // 10% raise
            'year3' => 1200, // 10% raise
            'year4' => 1300, // 10% raise
            'year5' => 1400  // 10% raise
        ];
        $birdYears = 2;

        // Act
        $result = $this->extensionValidator->validateRaises($offer, $birdYears);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group raises
     */
    public function testAcceptsLegalRaisesWithBirdRights()
    {
        // Arrange - 12.5% max raise with Bird rights
        $offer = [
            'year1' => 1000,
            'year2' => 1125, // 12.5% raise
            'year3' => 1250, // ~11.1% raise (allowed)
            'year4' => 0,
            'year5' => 0
        ];
        $birdYears = 3;

        // Act
        $result = $this->extensionValidator->validateRaises($offer, $birdYears);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * @group validation
     * @group salary-decrease
     * @dataProvider salaryDecreaseProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('salaryDecreaseProvider')]
    public function testRejectsSalaryDecreasesBetweenYears($offer, $expectedErrorYear)
    {
        // Act
        $result = $this->extensionValidator->validateSalaryDecreases($offer);

        // Assert
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString("cannot decrease salary", $result['error']);
    }

    /**
     * @group validation
     * @group salary-decrease
     */
    public function testAcceptsConstantOrIncreasingSalaries()
    {
        // Arrange
        $offer = [
            'year1' => 1000,
            'year2' => 1000, // Same salary is OK
            'year3' => 1050, // Increase is OK
            'year4' => 1050, // Same again is OK
            'year5' => 0     // Zero at end is OK
        ];

        // Act
        $result = $this->extensionValidator->validateSalaryDecreases($offer);

        // Assert
        $this->assertTrue($result['valid']);
    }

    /**
     * Data provider for invalid raises
     */
    public static function invalidRaiseProvider()
    {
        return [
            'Year 2 excessive raise without Bird rights' => [
                [
                    'year1' => 1000,
                    'year2' => 1150, // 15% raise, max is 10%
                    'year3' => 1150,
                    'year4' => 0,
                    'year5' => 0
                ],
                2, // birdYears
                2  // expectedErrorYear
            ],
            'Year 3 excessive raise with Bird rights' => [
                [
                    'year1' => 1000,
                    'year2' => 1125, // 12.5% raise OK
                    'year3' => 1300, // 15.5% raise, max is 12.5%
                    'year4' => 0,
                    'year5' => 0
                ],
                3, // birdYears
                3  // expectedErrorYear
            ],
            'Year 4 excessive raise' => [
                [
                    'year1' => 1000,
                    'year2' => 1100,
                    'year3' => 1200,
                    'year4' => 1400, // Jump too large
                    'year5' => 0
                ],
                2,
                4
            ],
            'Year 5 excessive raise' => [
                [
                    'year1' => 1000,
                    'year2' => 1100,
                    'year3' => 1200,
                    'year4' => 1300,
                    'year5' => 1500 // Jump too large
                ],
                2,
                5
            ]
        ];
    }

    /**
     * Data provider for salary decreases
     */
    public static function salaryDecreaseProvider()
    {
        return [
            'Year 2 decrease' => [
                [
                    'year1' => 1000,
                    'year2' => 900,
                    'year3' => 900,
                    'year4' => 0,
                    'year5' => 0
                ],
                2
            ],
            'Year 3 decrease' => [
                [
                    'year1' => 1000,
                    'year2' => 1100,
                    'year3' => 1000,
                    'year4' => 0,
                    'year5' => 0
                ],
                3
            ],
            'Year 4 decrease' => [
                [
                    'year1' => 1000,
                    'year2' => 1100,
                    'year3' => 1200,
                    'year4' => 1100,
                    'year5' => 0
                ],
                4
            ],
            'Year 5 decrease' => [
                [
                    'year1' => 1000,
                    'year2' => 1100,
                    'year3' => 1200,
                    'year4' => 1300,
                    'year5' => 1200
                ],
                5
            ]
        ];
    }
}
