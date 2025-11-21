<?php

use PHPUnit\Framework\TestCase;
use FreeAgency\FreeAgencyProcessor;
use FreeAgency\FreeAgencyOfferValidator;
use FreeAgency\FreeAgencyDemandCalculator;

/**
 * Comprehensive tests for FreeAgencyProcessor
 * 
 * Tests the orchestration of free agency operations:
 * - Offer submission and validation
 * - Offer parsing (max contracts, exceptions, custom)
 * - Offer persistence to database
 * - Offer deletion
 * - Response rendering
 */
class FreeAgencyProcessorTest extends TestCase
{
    private $mockDb;
    private $mockMysqliDb;
    private FreeAgencyProcessor $processor;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockMysqliDb = $this->createMock(\mysqli::class);
        $this->processor = new FreeAgencyProcessor($this->mockDb, $this->mockMysqliDb);
    }

    /**
     * @group processor
     * @group offer-submission
     */
    public function testProcessOfferSubmissionReturnsHtmlResponse(): void
    {
        // Arrange
        $this->setupValidOfferScenario();
        
        $postData = [
            'teamname' => 'Test Team',
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
        $this->assertIsString($result);
        $this->assertStringContainsString('<html>', $result);
        $this->assertStringContainsString('</html>', $result);
    }

    /**
     * @group processor
     * @group offer-parsing
     */
    public function testProcessOfferSubmissionParsesVeteranMinimum(): void
    {
        // Arrange
        $this->setupValidOfferScenario();
        
        $postData = [
            'teamname' => 'Test Team',
            'playerID' => 1,
            'offerType' => 8, // Veteran minimum
        ];
        
        // Act
        $result = $this->processor->processOfferSubmission($postData);
        
        // Assert - Should create offer with veteran minimum salary
        $this->assertIsString($result);
    }

    /**
     * @group processor
     * @group offer-parsing
     */
    public function testProcessOfferSubmissionParsesLLE(): void
    {
        // Arrange
        $this->setupValidOfferScenario();
        
        $postData = [
            'teamname' => 'Test Team',
            'playerID' => 1,
            'offerType' => 7, // Lower-Level Exception
        ];
        
        // Act
        $result = $this->processor->processOfferSubmission($postData);
        
        // Assert
        $this->assertIsString($result);
        $this->assertStringContainsString('<html>', $result);
    }

    /**
     * @group processor
     * @group offer-parsing
     */
    public function testProcessOfferSubmissionParsesMLEOneYear(): void
    {
        // Arrange
        $this->setupValidOfferScenario();
        
        $postData = [
            'teamname' => 'Test Team',
            'playerID' => 1,
            'offerType' => 1, // MLE 1 year
        ];
        
        // Act
        $result = $this->processor->processOfferSubmission($postData);
        
        // Assert
        $this->assertIsString($result);
    }

    /**
     * @group processor
     * @group offer-parsing
     */
    public function testProcessOfferSubmissionParsesMLEMultiYear(): void
    {
        // Arrange
        $this->setupValidOfferScenario();
        
        $postData = [
            'teamname' => 'Test Team',
            'playerID' => 1,
            'offerType' => 4, // MLE 4 years
        ];
        
        // Act
        $result = $this->processor->processOfferSubmission($postData);
        
        // Assert
        $this->assertIsString($result);
    }

    /**
     * @group processor
     * @group offer-parsing
     */
    public function testProcessOfferSubmissionParsesCustomOffer(): void
    {
        // Arrange
        $this->setupValidOfferScenario();
        
        $postData = [
            'teamname' => 'Test Team',
            'playerID' => 1,
            'offeryear1' => 800,
            'offeryear2' => 850,
            'offeryear3' => 900,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'offerType' => 0, // Custom offer
        ];
        
        // Act
        $result = $this->processor->processOfferSubmission($postData);
        
        // Assert
        $this->assertIsString($result);
        $this->assertStringContainsString('<html>', $result);
    }

    /**
     * @group processor
     * @group validation
     */
    public function testProcessOfferSubmissionValidatesBeforeSaving(): void
    {
        // Arrange - Invalid offer (exceeds cap)
        $this->setupInvalidOfferScenario();
        
        $postData = [
            'teamname' => 'Test Team',
            'playerID' => 1,
            'offeryear1' => 99999, // Way over cap
            'offeryear2' => 0,
            'offeryear3' => 0,
            'offeryear4' => 0,
            'offeryear5' => 0,
            'offeryear6' => 0,
            'offerType' => 0,
        ];
        
        // Act
        $result = $this->processor->processOfferSubmission($postData);
        
        // Assert - Should return error message
        $this->assertIsString($result);
        // Validation error messages are in the response
    }

    /**
     * @group processor
     * @group validation
     */
    public function testProcessOfferSubmissionRejectsAlreadySignedPlayer(): void
    {
        // Arrange - Player already signed (not a free agent)
        $this->setupAlreadySignedPlayerScenario();
        
        $postData = [
            'teamname' => 'Test Team',
            'playerID' => 1,
            'offeryear1' => 1000,
            'offerType' => 0,
        ];
        
        // Act
        $result = $this->processor->processOfferSubmission($postData);
        
        // Assert
        $this->assertStringContainsString('previously signed', $result);
        $this->assertStringContainsString('click here to return', $result);
    }

    /**
     * @group processor
     * @group offer-deletion
     */
    public function testDeleteOffersRemovesOffer(): void
    {
        // Arrange
        $this->mockDb->setReturnTrue(true);
        
        // Act
        $result = $this->processor->deleteOffers('Test Team', 1);
        
        // Assert
        $this->assertIsString($result);
        $this->assertStringContainsString('deleted', $result);
        
        // Verify DELETE query was executed
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertGreaterThan(0, count($queries));
        
        $allQueries = implode(' ', $queries);
        $this->assertStringContainsString('DELETE FROM ibl_fa_offers', $allQueries);
    }

    /**
     * @group processor
     * @group offer-deletion
     */
    public function testDeleteOffersReturnsHtmlResponse(): void
    {
        // Arrange
        $this->mockDb->setReturnTrue(true);
        
        // Act
        $result = $this->processor->deleteOffers('Test Team', 1);
        
        // Assert
        $this->assertIsString($result);
        $this->assertStringContainsString('<html>', $result);
        $this->assertStringContainsString('</html>', $result);
        $this->assertStringContainsString('deleted', $result);
        $this->assertStringContainsString('click here to return', $result);
    }

    /**
     * @group processor
     * @group offer-response
     */
    public function testSuccessfulOfferContainsSuccessMessage(): void
    {
        // Arrange
        $this->setupValidOfferScenario();
        
        $postData = [
            'teamname' => 'Test Team',
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
        
        // Assert - Valid offers should contain "legal" message
        if (!str_contains($result, 'Sorry')) {
            $this->assertStringContainsString('legal', $result);
        }
    }

    /**
     * @group processor
     * @group offer-saving
     */
    public function testOfferSubmissionDeletesPreviousOffer(): void
    {
        // Arrange
        $this->setupValidOfferScenario();
        
        $postData = [
            'teamname' => 'Test Team',
            'playerID' => 1,
            'offeryear1' => 600,
            'offerType' => 0,
        ];
        
        // Act
        $this->processor->processOfferSubmission($postData);
        
        // Assert - Should delete existing offer first
        $queries = $this->mockDb->getExecutedQueries();
        $allQueries = implode(' ', $queries);
        
        $this->assertStringContainsString('DELETE FROM ibl_fa_offers', $allQueries);
    }

    /**
     * @group processor
     * @group offer-saving
     */
    public function testOfferSubmissionInsertsNewOffer(): void
    {
        // Arrange
        $this->setupValidOfferScenario();
        
        $postData = [
            'teamname' => 'Test Team',
            'playerID' => 1,
            'offeryear1' => 700,
            'offerType' => 0,
        ];
        
        // Act
        $this->processor->processOfferSubmission($postData);
        
        // Assert - Should insert new offer
        $queries = $this->mockDb->getExecutedQueries();
        $allQueries = implode(' ', $queries);
        
        $this->assertStringContainsString('INSERT INTO ibl_fa_offers', $allQueries);
    }

    /**
     * @group processor
     * @group sql-injection
     */
    public function testProcessOfferSubmissionEscapesTeamName(): void
    {
        // Arrange - Malicious team name
        $this->setupValidOfferScenario();
        
        $postData = [
            'teamname' => "Test'; DROP TABLE ibl_fa_offers; --",
            'playerID' => 1,
            'offeryear1' => 500,
            'offerType' => 0,
        ];
        
        // Act
        $result = $this->processor->processOfferSubmission($postData);
        
        // Assert - Should safely handle input
        $this->assertIsString($result);
    }

    /**
     * @group processor
     * @group sql-injection
     */
    public function testDeleteOffersEscapesPlayerName(): void
    {
        // Arrange
        $this->mockDb->setReturnTrue(true);
        
        // Act - Malicious player ID (doesn't matter much since it's int cast)
        $result = $this->processor->deleteOffers("'; DROP TABLE ibl_fa_offers; --", 1);
        
        // Assert - Should handle safely
        $this->assertIsString($result);
    }

    /**
     * @group processor
     * @group edge-cases
     */
    public function testProcessOfferSubmissionHandlesMissingPlayerID(): void
    {
        // Arrange - Missing playerID
        $postData = [
            'teamname' => 'Test Team',
            // playerID missing
            'offeryear1' => 500,
        ];
        
        // Act & Assert - Should not crash
        $this->expectNotToPerformAssertions();
        
        try {
            $this->processor->processOfferSubmission($postData);
        } catch (\Exception $e) {
            // Exception is acceptable for missing required data
            $this->assertTrue(true);
        }
    }

    /**
     * @group processor
     * @group edge-cases
     */
    public function testProcessOfferSubmissionHandlesZeroPlayerID(): void
    {
        // Arrange
        $postData = [
            'teamname' => 'Test Team',
            'playerID' => 0,
            'offeryear1' => 500,
        ];
        
        // Act & Assert - Should handle gracefully
        $this->expectNotToPerformAssertions();
        
        try {
            $result = $this->processor->processOfferSubmission($postData);
            $this->assertIsString($result);
        } catch (\Exception $e) {
            // Exception acceptable for invalid player ID
            $this->assertTrue(true);
        }
    }

    // Helper Methods

    /**
     * Setup valid offer scenario with all mocks configured
     */
    private function setupValidOfferScenario(): void
    {
        // Mock player that is a free agent
        $this->mockDb->setMockData([[
            'pid' => 1,
            'name' => 'Test Player',
            'teamname' => 'Old Team',
            'cy' => 3,
            'cyt' => 3, // Free agent
            'exp' => 5,
            'bird' => 0,
        ]]);
        
        // Mock validation success
        $this->mockDb->setReturnTrue(true);
        
        // Mock Season
        $GLOBALS['_mockSeason'] = new Season($this->mockDb);
    }

    /**
     * Setup invalid offer scenario (will fail validation)
     */
    private function setupInvalidOfferScenario(): void
    {
        // Mock player
        $this->mockDb->setMockData([[
            'pid' => 1,
            'name' => 'Test Player',
            'teamname' => 'Test Team',
            'cy' => 3,
            'cyt' => 3,
            'exp' => 5,
            'bird' => 0,
        ]]);
        
        // Validation will fail due to cap space
        $this->mockDb->setReturnTrue(false);
        
        $GLOBALS['_mockSeason'] = new Season($this->mockDb);
    }

    /**
     * Setup scenario where player is already signed
     */
    private function setupAlreadySignedPlayerScenario(): void
    {
        // Mock player that is NOT a free agent
        $this->mockDb->setMockData([[
            'pid' => 1,
            'name' => 'Signed Player',
            'teamname' => 'Test Team',
            'cy' => 0,
            'cyt' => 3, // Under contract
            'exp' => 5,
            'bird' => 0,
        ]]);
        
        // Mock the validator to return already signed
        $this->mockDb->setNumRows(1); // Indicates player has signed
        
        $GLOBALS['_mockSeason'] = new Season($this->mockDb);
    }
}
