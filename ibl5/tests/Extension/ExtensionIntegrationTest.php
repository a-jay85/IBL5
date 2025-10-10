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
        
        // Verify chunk flag was set
        $this->assertContains('Used_Extension_This_Chunk = 1', implode(' ', $queries));
        
        // Verify season flag was set (for accepted extension)
        $this->assertContains('Used_Extension_This_Season = 1', implode(' ', $queries));
        
        // Verify player contract was updated
        $this->assertContains('UPDATE ibl_plr', implode(' ', $queries));
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
        $this->assertContains('Used_Extension_This_Chunk = 1', $allQueries);
        $this->assertNotContains('Used_Extension_This_Season = 1', $allQueries);
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
        $this->assertNotContains('Used_Extension_This_Chunk', $allQueries);
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
        
        // Verify NO new database changes
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertCount(0, $queries); // Only the initial setup query
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
            ['Used_Extension_This_Season' => 0, 'Used_Extension_This_Chunk' => 0],
            ['name' => 'Veteran Player', 'exp' => 8, 'bird' => 4, 'cy' => 1,
             'winner' => 3, 'tradition' => 3, 'loyalty' => 3, 'playingTime' => 3],
            ['catid' => 1, 'counter' => 10],
            ['topicid' => 5]
        ]);
    }

    private function setupHighLoyaltyPlayerScenario()
    {
        $this->mockDb->setMockData([
            ['Used_Extension_This_Season' => 0, 'Used_Extension_This_Chunk' => 0,
             'Contract_Wins' => 45, 'Contract_Losses' => 37],
            ['name' => 'Loyal Player', 'exp' => 6, 'bird' => 3,
             'winner' => 3, 'tradition' => 3, 'loyalty' => 5, 'playingTime' => 3],
            ['catid' => 1, 'counter' => 10],
            ['topicid' => 5]
        ]);
    }

    private function setupPlayingTimeScenario()
    {
        $this->mockDb->setMockData([
            ['Used_Extension_This_Season' => 0, 'Used_Extension_This_Chunk' => 0,
             'money_committed_at_position' => 8000], // High money at position
            ['name' => 'Rotation Player', 'exp' => 4, 'bird' => 2,
             'winner' => 3, 'tradition' => 3, 'loyalty' => 2, 'playingTime' => 5],
            ['catid' => 1, 'counter' => 10],
            ['topicid' => 5]
        ]);
    }
}
