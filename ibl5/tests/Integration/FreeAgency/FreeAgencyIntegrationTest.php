<?php

declare(strict_types=1);

namespace Tests\Integration\FreeAgency;

use Tests\Integration\IntegrationTestCase;
use Tests\Integration\Mocks\TestDataFactory;
use FreeAgency\FreeAgencyProcessor;

/**
 * Integration tests for complete free agency offer workflows
 *
 * Tests end-to-end scenarios combining validation, perceived value calculation,
 * and database persistence:
 * - Successful offer submissions (custom, MLE, LLE, VetMin)
 * - Validation failures (cap space, max salary, already signed)
 * - Offer deletion workflows
 *
 * @covers \FreeAgency\FreeAgencyProcessor
 * @covers \FreeAgency\FreeAgencyOfferValidator
 * @covers \FreeAgency\FreeAgencyDemandCalculator
 * @covers \FreeAgency\FreeAgencyRepository
 * @covers \FreeAgency\FreeAgencyCapCalculator
 * @covers \FreeAgency\OfferType
 */
class FreeAgencyIntegrationTest extends IntegrationTestCase
{
    private FreeAgencyProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new FreeAgencyProcessor($this->mockDb);
        
        // Prevent Discord notifications during tests
        $_SERVER['SERVER_NAME'] = 'localhost';
    }

    protected function tearDown(): void
    {
        unset($this->processor);
        unset($_SERVER['SERVER_NAME']);
        parent::tearDown();
    }

    // ========== SUCCESS SCENARIOS ==========

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testSuccessfulCustomOfferSubmission(): void
    {
        // Arrange
        $this->setupSuccessfulOfferScenario();

        $postData = [
            'teamname' => 'Miami Cyclones',
            'playerID' => 1,
            'offeryear1' => 500,
            'offeryear2' => 550,
            'offeryear3' => 600,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'offerType' => 0, // Custom offer
        ];

        // Act
        $result = $this->processor->processOfferSubmission($postData);

        // Assert
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('offer_success', $result['type']);

        // Verify offer was saved to database
        $this->assertQueryExecuted('INSERT INTO ibl_fa_offers');
        $this->assertQueryExecuted("'Miami Cyclones'");
    }

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testSuccessfulMLEOfferSubmission(): void
    {
        // Arrange
        $this->setupMLEOfferScenario();

        $postData = [
            'teamname' => 'Miami Cyclones',
            'playerID' => 1,
            'offerType' => 3, // 3-year MLE
        ];

        // Act
        $result = $this->processor->processOfferSubmission($postData);

        // Assert
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('offer_success', $result['type']);

        // Verify MLE flag was set
        $this->assertQueryExecuted('INSERT INTO ibl_fa_offers');
    }

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testSuccessfulLLEOfferSubmission(): void
    {
        // Arrange
        $this->setupLLEOfferScenario();

        $postData = [
            'teamname' => 'Miami Cyclones',
            'playerID' => 1,
            'offerType' => 7, // LLE = 7 (OfferType::LOWER_LEVEL_EXCEPTION)
        ];

        // Act
        $result = $this->processor->processOfferSubmission($postData);

        // Assert
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('offer_success', $result['type']);

        // Verify LLE flag was set
        $this->assertQueryExecuted('INSERT INTO ibl_fa_offers');
    }

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testSuccessfulVeteranMinimumOfferSubmission(): void
    {
        // Arrange
        $this->setupVetMinOfferScenario();

        $postData = [
            'teamname' => 'Miami Cyclones',
            'playerID' => 1,
            'offerType' => 8, // VetMin = 8 (OfferType::VETERAN_MINIMUM)
        ];

        // Act
        $result = $this->processor->processOfferSubmission($postData);

        // Assert
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('offer_success', $result['type']);
        $this->assertQueryExecuted('INSERT INTO ibl_fa_offers');
    }

    // ========== VALIDATION FAILURE SCENARIOS ==========

    /**
     * @group integration
     * @group validation-failures
     */
    public function testRejectsOfferWhenPlayerAlreadySigned(): void
    {
        // Arrange
        $this->setupAlreadySignedScenario();

        $postData = [
            'teamname' => 'Miami Cyclones',
            'playerID' => 1,
            'offeryear1' => 500,
            'offeryear2' => 0,
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'offerType' => 0,
        ];

        // Act
        $result = $this->processor->processOfferSubmission($postData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame('already_signed', $result['type']);
        $this->assertStringContainsString('previously signed', $result['message']);

        // Verify no offer was saved
        $this->assertQueryNotExecuted('INSERT INTO ibl_fa_offers');
    }

    /**
     * @group integration
     * @group validation-failures
     */
    public function testRejectsOfferExceedingCapSpace(): void
    {
        // Arrange
        $this->setupCapSpaceExceededScenario();

        $postData = [
            'teamname' => 'Miami Cyclones',
            'playerID' => 1,
            'offeryear1' => 2000, // Exceeds available cap space
            'offeryear2' => 2100,
            'offeryear3' => 2200,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'offerType' => 0,
        ];

        // Act
        $result = $this->processor->processOfferSubmission($postData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame('validation_error', $result['type']);

        // Verify no offer was saved
        $this->assertQueryNotExecuted('INSERT INTO ibl_fa_offers');
    }

    // ========== ADDITIONAL VALIDATION FAILURE SCENARIOS ==========

    /**
     * @group integration
     * @group validation-failures
     */
    public function testOfferBelowVetMinRejected(): void
    {
        // Arrange
        $this->setupSuccessfulOfferScenario();

        // Veteran minimum for exp=5 is 70 (from ContractRules::VETERAN_MINIMUM_SALARIES)
        $postData = [
            'teamname' => 'Miami Cyclones',
            'playerID' => 1,
            'offeryear1' => 50, // Below vet min of 70 for exp=5
            'offeryear2' => 0,
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'offerType' => 0, // Custom offer
        ];

        // Act
        $result = $this->processor->processOfferSubmission($postData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame('validation_error', $result['type']);
        $this->assertQueryNotExecuted('INSERT INTO ibl_fa_offers');
    }

    /**
     * @group integration
     * @group validation-failures
     */
    public function testOfferExceedsMaxContractRejected(): void
    {
        // Arrange
        $this->setupSuccessfulOfferScenario();

        // Max contract for exp=5 (0-6 years bracket) is 1063
        $postData = [
            'teamname' => 'Miami Cyclones',
            'playerID' => 1,
            'offeryear1' => 1100, // Exceeds max contract of 1063 for exp=5
            'offeryear2' => 0,
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'offerType' => 0, // Custom offer
        ];

        // Act
        $result = $this->processor->processOfferSubmission($postData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame('validation_error', $result['type']);
        $this->assertQueryNotExecuted('INSERT INTO ibl_fa_offers');
    }

    /**
     * @group integration
     * @group validation-failures
     */
    public function testRaisesExceedStandardLimitRejected(): void
    {
        // Arrange - base data has bird_years=0 (no Bird Rights)
        $this->setupSuccessfulOfferScenario();

        // Standard raise limit is 10%. For offer1=500, max raise = round(500 * 0.10) = 50
        // So offer2 max is 550. Offering 600 is a 100 increase, which exceeds 50.
        $postData = [
            'teamname' => 'Miami Cyclones',
            'playerID' => 1,
            'offeryear1' => 500,
            'offeryear2' => 600, // 20% raise, exceeds 10% standard limit
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'offerType' => 0, // Custom offer
        ];

        // Act
        $result = $this->processor->processOfferSubmission($postData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame('validation_error', $result['type']);
        $this->assertQueryNotExecuted('INSERT INTO ibl_fa_offers');
    }

    /**
     * @group integration
     * @group bird-rights
     */
    public function testBirdRightsRaisesAllowed(): void
    {
        // Arrange - player with Bird Rights (bird_years=3, same team)
        $this->setupBirdRightsOfferScenario();

        // Bird Rights raise limit is 12.5%. For offer1=500, max raise = round(500 * 0.125) = 63
        // So offer2 max is 563. Offering 562 is within the limit.
        $postData = [
            'teamname' => 'Miami Cyclones',
            'playerID' => 1,
            'offeryear1' => 500,
            'offeryear2' => 562, // 12.4% raise, within Bird Rights 12.5% limit
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'offerType' => 0, // Custom offer
        ];

        // Act
        $result = $this->processor->processOfferSubmission($postData);

        // Assert
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertSame('offer_success', $result['type']);
        $this->assertQueryExecuted('INSERT INTO ibl_fa_offers');
    }

    /**
     * @group integration
     * @group validation-failures
     */
    public function testMLEOfferWhenAlreadyUsedRejected(): void
    {
        // Arrange - team has already used their MLE (HasMLE=0)
        $this->setupMLEAlreadyUsedScenario();

        $postData = [
            'teamname' => 'Miami Cyclones',
            'playerID' => 1,
            'offerType' => 3, // 3-year MLE
        ];

        // Act
        $result = $this->processor->processOfferSubmission($postData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame('validation_error', $result['type']);
        $this->assertQueryNotExecuted('INSERT INTO ibl_fa_offers');
    }

    /**
     * @group integration
     * @group validation-failures
     */
    public function testOfferWithGapInContractYearsRejected(): void
    {
        // Arrange
        $this->setupSuccessfulOfferScenario();

        // Gap in contract years: year 2 is 0, but year 3 has a value
        $postData = [
            'teamname' => 'Miami Cyclones',
            'playerID' => 1,
            'offeryear1' => 500,
            'offeryear2' => 0,   // Gap
            'offeryear3' => 600, // Resuming after gap
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'offerType' => 0, // Custom offer
        ];

        // Act
        $result = $this->processor->processOfferSubmission($postData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame('validation_error', $result['type']);
        $this->assertQueryNotExecuted('INSERT INTO ibl_fa_offers');
    }

    /**
     * @group integration
     * @group validation-failures
     */
    public function testOfferWithZeroFirstYearRejected(): void
    {
        // Arrange
        $this->setupSuccessfulOfferScenario();

        // Zero first year is not allowed
        $postData = [
            'teamname' => 'Miami Cyclones',
            'playerID' => 1,
            'offeryear1' => 0,
            'offeryear2' => 0,
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'offerType' => 0, // Custom offer
        ];

        // Act
        $result = $this->processor->processOfferSubmission($postData);

        // Assert
        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertSame('validation_error', $result['type']);
        $this->assertQueryNotExecuted('INSERT INTO ibl_fa_offers');
    }

    // ========== OFFER DELETION ==========

    /**
     * @group integration
     * @group deletion
     */
    public function testDeleteOfferRemovesFromDatabase(): void
    {
        // Arrange
        $this->setupExistingOfferScenario();

        // Act
        $result = $this->processor->deleteOffers('Miami Cyclones', 1);

        // Assert
        $this->assertIsArray($result);
        $this->assertTrue($result['success']);

        // Verify DELETE query was executed
        $this->assertQueryExecuted('DELETE FROM ibl_fa_offers');
    }

    // ========== HELPER METHODS ==========

    private function setupSuccessfulOfferScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseFreeAgentData(), [
                // Team has cap space
                'Salary_Total' => 5000,
                'Salary_Cap' => 8250,
                'Tax_Line' => 10000,
                'HasMLE' => 0,
                'HasLLE' => 0,
                // Player is not signed
                'tid' => 0,
                'teamname' => 'Free Agent',
                // Season settings
                'freeAgencyNotificationsState' => 'Off',
            ])
        ]);
    }

    private function setupMLEOfferScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseFreeAgentData(), [
                // Team has MLE available
                'Salary_Total' => 9000,
                'Salary_Cap' => 8250,
                'Tax_Line' => 10000,
                'HasMLE' => 1,
                'HasLLE' => 0,
                // Over cap but can use MLE
                'tid' => 0,
                'teamname' => 'Free Agent',
                'freeAgencyNotificationsState' => 'Off',
            ])
        ]);
    }

    private function setupLLEOfferScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseFreeAgentData(), [
                // Team has LLE available
                'Salary_Total' => 9000,
                'Salary_Cap' => 8250,
                'Tax_Line' => 10000,
                'HasMLE' => 0,
                'HasLLE' => 1,
                'tid' => 0,
                'teamname' => 'Free Agent',
                'freeAgencyNotificationsState' => 'Off',
            ])
        ]);
    }

    private function setupVetMinOfferScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseFreeAgentData(), [
                // Team can always offer vet min
                'Salary_Total' => 12000,
                'Salary_Cap' => 8250,
                'Tax_Line' => 10000,
                'HasMLE' => 0,
                'HasLLE' => 0,
                'tid' => 0,
                'teamname' => 'Free Agent',
                'freeAgencyNotificationsState' => 'Off',
            ])
        ]);
    }

    private function setupAlreadySignedScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseFreeAgentData(), [
                // Player is already on a team (signed)
                'tid' => 5,
                'teamname' => 'New York Knights',
                'Salary_Total' => 5000,
                'Salary_Cap' => 8250,
                'freeAgencyNotificationsState' => 'Off',
                // Critical: These fields indicate player was signed this FA period
                'cy' => 0,  // Current year = 0 (not yet started)
                'cy1' => 500,  // But has year 1 contract != 0 (signed!)
            ])
        ]);
    }

    private function setupCapSpaceExceededScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseFreeAgentData(), [
                // Team is near cap with little space
                'Salary_Total' => 8000,
                'Salary_Cap' => 8250,
                'Tax_Line' => 10000,
                'HasMLE' => 0,
                'HasLLE' => 0,
                'tid' => 0,
                'teamname' => 'Free Agent',
                'freeAgencyNotificationsState' => 'Off',
            ])
        ]);
    }

    private function setupBirdRightsOfferScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseFreeAgentData(), [
                // Player is on the offering team (required for Bird Rights)
                'tid' => 1,
                'teamname' => 'Miami Cyclones',
                'bird_years' => 3,
                'bird' => 3,
                // Team has cap space
                'Salary_Total' => 5000,
                'Salary_Cap' => 8250,
                'Tax_Line' => 10000,
                'HasMLE' => 0,
                'HasLLE' => 0,
                // Player is not signed (cy=0 means not in current contract)
                'cy' => 0,
                'cy1' => 0,
                'freeAgencyNotificationsState' => 'Off',
            ])
        ]);
    }

    private function setupMLEAlreadyUsedScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseFreeAgentData(), [
                // Team is over the cap but has already used MLE
                'Salary_Total' => 9000,
                'Salary_Cap' => 8250,
                'Tax_Line' => 10000,
                'HasMLE' => 0, // MLE already used
                'HasLLE' => 0,
                'tid' => 0,
                'teamname' => 'Free Agent',
                'freeAgencyNotificationsState' => 'Off',
            ])
        ]);
    }

    private function setupExistingOfferScenario(): void
    {
        $this->mockDb->setMockData([
            array_merge($this->getBaseFreeAgentData(), [
                // Existing offer data
                'offer1' => 500,
                'offer2' => 550,
                'offer3' => 600,
                'offer4' => 0,
                'offer5' => 0,
                'offer6' => 0,
                'tid' => 0,
                'teamname' => 'Free Agent',
            ])
        ]);
    }

    /**
     * Base free agent player data for all scenarios
     */
    private function getBaseFreeAgentData(): array
    {
        return array_merge(TestDataFactory::createPlayer([
            'pid' => 1,
            'name' => 'Test FreeAgent',
            'tid' => 0,
            'teamname' => 'Free Agent',
            'exp' => 5,
            'bird_years' => 0,
            'position' => 'SG',
            'pos' => 'SG',
            // Free agent contract status (not signed)
            'cy' => 0,  // Current year = 0 (not in contract)
            'cy1' => 0,  // Year 1 salary = 0 (unsigned)
        ]), TestDataFactory::createTeam([
            'teamid' => 1,
            'team_name' => 'Miami Cyclones',
        ]), TestDataFactory::createSeason([
            'Phase' => 'Free Agency',
        ]), [
            // Free agency specific fields
            'freeAgencyNotificationsState' => 'Off',
            'winner' => 3,
            'tradition' => 3,
            'loyalty' => 3,
            'playingTime' => 3,
            'security' => 3,
            'Contract_Wins' => 50,
            'Contract_Losses' => 32,
            'Contract_AvgW' => 2500,
            'Contract_AvgL' => 2000,
            'money_committed_at_position' => 2000,
        ]);
    }
}
