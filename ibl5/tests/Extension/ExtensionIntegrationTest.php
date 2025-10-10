<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for complete contract extension workflows
 * 
 * Tests end-to-end scenarios combining validation, evaluation, and database operations:
 * - Successful extension scenarios
 * - Failed extension scenarios
 * - Edge cases and special conditions
 */
class ExtensionIntegrationTest extends TestCase
{
    private $mockDb;
    private $extensionProcessor;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->extensionProcessor = new ExtensionProcessor($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->extensionProcessor = null;
        $this->mockDb = null;
    }

    /**
     * @group integration
     * @group success-scenarios
     */
    public function testCompleteSuccessfulExtensionWorkflow()
    {
        // Arrange - Setup complete player and team data
        $this->setupSuccessfulExtensionScenario();
        
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerName' => 'Test Player',
            'offer' => [
                'year1' => 1000,
                'year2' => 1100,
                'year3' => 1200,
                'year4' => 1300,
                'year5' => 1400
            ]
        ];

        // Act
        $result = $this->extensionProcessor->processExtension($extensionData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertTrue($result['accepted']);
        $this->assertStringContainsString('accept', $result['message']);
        
        // Verify database operations were performed
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertGreaterThan(0, count($queries));
        
        $allQueries = implode(' ', $queries);
        
        // Verify chunk flag was set
        $this->assertStringContainsString('Used_Extension_This_Chunk = 1', $allQueries);
        
        // Verify season flag was set (for accepted extension)
        $this->assertStringContainsString('Used_Extension_This_Season = 1', $allQueries);
        
        // Verify player contract was updated
        $this->assertStringContainsString('UPDATE ibl_plr', $allQueries);
    }

    /**
     * @group integration
     * @group rejection-scenarios
     */
    public function testCompleteRejectedExtensionWorkflow()
    {
        // Arrange - Setup player who will reject
        $this->setupRejectedExtensionScenario();
        
        $extensionData = [
            'teamName' => 'Seattle SuperSonics',
            'playerName' => 'Demanding Player',
            'offer' => [
                'year1' => 800,
                'year2' => 850,
                'year3' => 900,
                'year4' => 0,
                'year5' => 0
            ]
        ];

        // Act
        $result = $this->extensionProcessor->processExtension($extensionData);

        // Assert
        $this->assertTrue($result['success']); // Extension attempt was legal
        $this->assertFalse($result['accepted']); // But offer was rejected
        $this->assertStringContainsString('refuse', $result['message']);
        
        // Verify chunk flag was set but NOT season flag
        $queries = $this->mockDb->getExecutedQueries();
        $allQueries = implode(' ', $queries);
        $this->assertStringContainsString('Used_Extension_This_Chunk = 1', $allQueries);
        $this->assertStringNotContainsString('Used_Extension_This_Season = 1', $allQueries);
    }

    /**
     * @group integration
     * @group validation-failures
     */
    public function testRejectsExtensionWithZeroAmountInYear1()
    {
        // Arrange
        $this->setupBasicExtensionScenario();
        
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerName' => 'Test Player',
            'offer' => [
                'year1' => 0, // Invalid!
                'year2' => 1000,
                'year3' => 1100,
                'year4' => 0,
                'year5' => 0
            ]
        ];

        // Act
        $result = $this->extensionProcessor->processExtension($extensionData);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Year 1', $result['error']);
        $this->assertStringContainsString('zero', $result['error']);
        
        // Verify NO database changes were made
        $queries = $this->mockDb->getExecutedQueries();
        $allQueries = implode(' ', $queries);
        $this->assertStringNotContainsString('Used_Extension_This_Chunk', $allQueries);
    }

    /**
     * @group integration
     * @group validation-failures
     */
    public function testRejectsExtensionWhenAlreadyUsedThisSeason()
    {
        // Arrange
        $this->setupAlreadyExtendedScenario();
        
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerName' => 'Test Player',
            'offer' => [
                'year1' => 1000,
                'year2' => 1100,
                'year3' => 1200,
                'year4' => 0,
                'year5' => 0
            ]
        ];

        // Act
        $result = $this->extensionProcessor->processExtension($extensionData);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already used your extension for this season', $result['error']);
        
        // Verify only eligibility check query was made, no extension processing
        $queries = $this->mockDb->getExecutedQueries();
        // Should have at least one query for eligibility check
        $this->assertGreaterThanOrEqual(1, count($queries));
        // But should NOT have marked chunk as used or updated contract
        $allQueries = implode(' ', $queries);
        $this->assertStringNotContainsString('UPDATE ibl_plr', $allQueries);
    }

    /**
     * @group integration
     * @group validation-failures
     */
    public function testRejectsExtensionWithExcessiveRaise()
    {
        // Arrange
        $this->setupBasicExtensionScenario();
        
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerName' => 'Test Player',
            'offer' => [
                'year1' => 1000,
                'year2' => 1200, // 20% raise, but max is 10% without Bird rights
                'year3' => 1400,
                'year4' => 0,
                'year5' => 0
            ]
        ];

        // Act
        $result = $this->extensionProcessor->processExtension($extensionData);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('larger raise than is permitted', $result['error']);
    }

    /**
     * @group integration
     * @group validation-failures
     */
    public function testRejectsExtensionOverMaximumSalary()
    {
        // Arrange
        $this->setupBasicExtensionScenario();
        
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerName' => 'Young Player', // Only 3 years experience
            'offer' => [
                'year1' => 1500, // Over max of 1063 for 0-6 years exp
                'year2' => 1600,
                'year3' => 1700,
                'year4' => 0,
                'year5' => 0
            ]
        ];

        // Act
        $result = $this->extensionProcessor->processExtension($extensionData);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('over the maximum allowed', $result['error']);
    }

    /**
     * @group integration
     * @group bird-rights
     */
    public function testAllowsHigherRaisesWithBirdRights()
    {
        // Arrange - Player with Bird rights
        $this->setupBirdRightsExtensionScenario();
        
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerName' => 'Veteran Player',
            'offer' => [
                'year1' => 1000,
                'year2' => 1125, // 12.5% raise allowed with Bird rights
                'year3' => 1250, // Another ~11% raise
                'year4' => 0,
                'year5' => 0
            ]
        ];

        // Act
        $result = $this->extensionProcessor->processExtension($extensionData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertTrue($result['accepted']);
    }

    /**
     * @group integration
     * @group player-preferences
     */
    public function testPlayerWithHighLoyaltyAcceptsLowerOffer()
    {
        // Arrange - Player with high loyalty to current team
        $this->setupHighLoyaltyPlayerScenario();
        
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerName' => 'Loyal Player',
            'offer' => [
                'year1' => 900,
                'year2' => 950,
                'year3' => 1000,
                'year4' => 0,
                'year5' => 0
            ]
        ];

        // Act
        $result = $this->extensionProcessor->processExtension($extensionData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertTrue($result['accepted']);
        $this->assertArrayHasKey('modifierApplied', $result);
        $this->assertGreaterThan(1.0, $result['modifierApplied']); // Loyalty increases modifier
    }

    /**
     * @group integration
     * @group player-preferences
     */
    public function testPlayerRejectsOfferDueToLackOfPlayingTime()
    {
        // Arrange - Player values playing time, team has lots of money at position
        $this->setupPlayingTimeScenario();
        
        $extensionData = [
            'teamName' => 'Stacked Team',
            'playerName' => 'Rotation Player',
            'offer' => [
                'year1' => 1100,
                'year2' => 1200,
                'year3' => 1300,
                'year4' => 0,
                'year5' => 0
            ]
        ];

        // Act
        $result = $this->extensionProcessor->processExtension($extensionData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertFalse($result['accepted']); // Rejects due to playing time concerns
    }

    /**
     * @group integration
     * @group edge-cases
     */
    public function testHandles3YearMinimumExtension()
    {
        // Arrange
        $this->setupSuccessfulExtensionScenario();
        
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerName' => 'Test Player',
            'offer' => [
                'year1' => 1000,
                'year2' => 1100,
                'year3' => 1200,
                'year4' => 0, // 3-year extension
                'year5' => 0
            ]
        ];

        // Act
        $result = $this->extensionProcessor->processExtension($extensionData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(3, $result['extensionYears']);
    }

    /**
     * @group integration
     * @group edge-cases
     */
    public function testHandles5YearMaximumExtension()
    {
        // Arrange
        $this->setupSuccessfulExtensionScenario();
        
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerName' => 'Test Player',
            'offer' => [
                'year1' => 1000,
                'year2' => 1100,
                'year3' => 1200,
                'year4' => 1300,
                'year5' => 1400 // 5-year extension
            ]
        ];

        // Act
        $result = $this->extensionProcessor->processExtension($extensionData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(5, $result['extensionYears']);
    }

    /**
     * @group integration
     * @group notifications
     */
    public function testSendsDiscordNotificationOnAcceptedExtension()
    {
        // Arrange
        $this->setupSuccessfulExtensionScenario();
        
        $extensionData = [
            'teamName' => 'Miami Cyclones',
            'playerName' => 'Test Player',
            'offer' => [
                'year1' => 1000,
                'year2' => 1100,
                'year3' => 1200,
                'year4' => 0,
                'year5' => 0
            ]
        ];

        // Act
        $result = $this->extensionProcessor->processExtension($extensionData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertTrue($result['discordNotificationSent']);
        $this->assertStringContainsString('#extensions', $result['discordChannel']);
    }

    // ==== HELPER METHODS TO SET UP TEST SCENARIOS ====

    private function setupSuccessfulExtensionScenario()
    {
        $this->mockDb->setMockData([
            // Team info
            ['Used_Extension_This_Season' => 0, 'Used_Extension_This_Chunk' => 0,
             'Contract_Wins' => 50, 'Contract_Losses' => 32,
             'Contract_AvgW' => 2500, 'Contract_AvgL' => 2000],
            // Player info
            ['name' => 'Test Player', 'teamname' => 'Miami Cyclones',
             'winner' => 3, 'tradition' => 3, 'loyalty' => 3, 'playingTime' => 3,
             'exp' => 5, 'bird' => 2, 'cy' => 1, 'cy1' => 800, 'cy2' => 0],
            // Category info
            ['catid' => 1, 'counter' => 10],
            // Topic info
            ['topicid' => 5]
        ]);
    }

    private function setupRejectedExtensionScenario()
    {
        $this->mockDb->setMockData([
            // Team info - losing team
            ['Used_Extension_This_Season' => 0, 'Used_Extension_This_Chunk' => 0,
             'Contract_Wins' => 25, 'Contract_Losses' => 57,
             'Contract_AvgW' => 1500, 'Contract_AvgL' => 3500],
            // Player info - high demands
            ['name' => 'Demanding Player', 'teamname' => 'Seattle SuperSonics',
             'winner' => 5, 'tradition' => 5, 'loyalty' => 1, 'playingTime' => 5,
             'exp' => 8, 'bird' => 3, 'cy' => 1, 'cy1' => 1200, 'cy2' => 0],
            ['catid' => 1, 'counter' => 10],
            ['topicid' => 5]
        ]);
    }

    private function setupBasicExtensionScenario()
    {
        $this->mockDb->setMockData([
            ['Used_Extension_This_Season' => 0, 'Used_Extension_This_Chunk' => 0],
            ['name' => 'Test Player', 'exp' => 5, 'bird' => 2, 'cy' => 1, 'cy1' => 800]
        ]);
    }

    private function setupAlreadyExtendedScenario()
    {
        $this->mockDb->setMockData([
            ['Used_Extension_This_Season' => 1, 'Used_Extension_This_Chunk' => 0]
        ]);
    }

    private function setupBirdRightsExtensionScenario()
    {
        $this->mockDb->setMockData([
            // Team info for eligibility check
            ['Used_Extension_This_Season' => 0, 'Used_Extension_This_Chunk' => 0,
             'Contract_Wins' => 50, 'Contract_Losses' => 32,
             'Contract_AvgW' => 2500, 'Contract_AvgL' => 2000],
            // Player info with Bird rights
            ['name' => 'Veteran Player', 'exp' => 8, 'bird' => 4, 'cy' => 1,
             'winner' => 3, 'tradition' => 3, 'loyalty' => 3, 'playingTime' => 3,
             'cy1' => 900, 'cy2' => 0, 'cy3' => 0, 'cy4' => 0, 'cy5' => 0, 'cy6' => 0],
            ['catid' => 1, 'counter' => 10],
            ['topicid' => 5]
        ]);
    }

    private function setupHighLoyaltyPlayerScenario()
    {
        $this->mockDb->setMockData([
            ['Used_Extension_This_Season' => 0, 'Used_Extension_This_Chunk' => 0,
             'Contract_Wins' => 45, 'Contract_Losses' => 37,
             'Contract_AvgW' => 2300, 'Contract_AvgL' => 2100],
            ['name' => 'Loyal Player', 'exp' => 6, 'bird' => 3,
             'winner' => 3, 'tradition' => 3, 'loyalty' => 5, 'playingTime' => 3,
             'cy' => 1, 'cy1' => 850, 'cy2' => 0, 'cy3' => 0, 'cy4' => 0, 'cy5' => 0, 'cy6' => 0],
            ['catid' => 1, 'counter' => 10],
            ['topicid' => 5]
        ]);
    }

    private function setupPlayingTimeScenario()
    {
        $this->mockDb->setMockData([
            ['Used_Extension_This_Season' => 0, 'Used_Extension_This_Chunk' => 0,
             'money_committed_at_position' => 8000,
             'Contract_Wins' => 55, 'Contract_Losses' => 27,
             'Contract_AvgW' => 2700, 'Contract_AvgL' => 1900],
            ['name' => 'Rotation Player', 'exp' => 4, 'bird' => 2,
             'winner' => 3, 'tradition' => 3, 'loyalty' => 2, 'playingTime' => 5,
             'cy' => 1, 'cy1' => 800, 'cy2' => 0, 'cy3' => 0, 'cy4' => 0, 'cy5' => 0, 'cy6' => 0],
            ['catid' => 1, 'counter' => 10],
            ['topicid' => 5]
        ]);
    }
}
