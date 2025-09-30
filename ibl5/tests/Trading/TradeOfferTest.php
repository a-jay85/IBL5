<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Trading_TradeOffer class
 * 
 * Tests trade offer creation, validation, and database insertion.
 */
class TradeOfferTest extends TestCase
{
    private $tradeOffer;
    private $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->tradeOffer = new Trading_TradeOffer($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->tradeOffer = null;
        $this->mockDb = null;
    }

    /**
     * @test
     */
    public function createTradeOffer_withValidData_returnsSuccess()
    {
        // Arrange
        $tradeData = $this->getValidTradeData();

        // Act
        $result = $this->tradeOffer->createTradeOffer($tradeData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('tradeText', $result);
        $this->assertArrayHasKey('tradeOfferId', $result);
    }

    /**
     * @test
     */
    public function createTradeOffer_withInvalidCashAmount_returnsError()
    {
        // Arrange
        $tradeData = $this->getValidTradeData();
        $tradeData['userSendsCash'][1] = 50; // Below minimum of 100

        // Act
        $result = $this->tradeOffer->createTradeOffer($tradeData);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('minimum amount of cash', $result['error']);
    }

    /**
     * @test
     */
    public function createTradeOffer_withSalaryCapViolation_returnsError()
    {
        // Arrange
        $tradeData = $this->getValidTradeData();
        // Set up data that would cause cap violation
        $tradeData['check'] = [0 => 'on']; // User sends expensive player
        $tradeData['contract'] = [0 => 70000]; // Very expensive contract
        $tradeData['switchCounter'] = 1;

        // Act
        $result = $this->tradeOffer->createTradeOffer($tradeData);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }

    /**
     * @test
     */
    public function createTradeOffer_withPlayerTrade_includesPlayerInTradeText()
    {
        // Arrange
        $tradeData = $this->getValidTradeData();
        $tradeData['check'] = [0 => 'on'];
        $tradeData['type'] = [0 => 1]; // Player
        $tradeData['index'] = [0 => 12345];
        $tradeData['contract'] = [0 => 5000];
        $tradeData['switchCounter'] = 1;

        // Mock player data
        $this->mockDb->setMockData([
            ['name' => 'Test Player', 'pos' => 'PG']
        ]);

        // Act
        $result = $this->tradeOffer->createTradeOffer($tradeData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Test Player', $result['tradeText']);
        $this->assertStringContainsString('PG', $result['tradeText']);
    }

    /**
     * @test
     */
    public function createTradeOffer_withDraftPickTrade_includesPickInTradeText()
    {
        // Arrange
        $tradeData = $this->getValidTradeData();
        $tradeData['check'] = [0 => 'on'];
        $tradeData['type'] = [0 => 0]; // Draft pick
        $tradeData['index'] = [0 => 67890];
        $tradeData['switchCounter'] = 1;

        // Mock draft pick data
        $this->mockDb->setMockData([
            [
                'teampick' => 'Lakers',
                'year' => 2024,
                'round' => 1,
                'notes' => 'Protected 1-10'
            ]
        ]);

        // Act
        $result = $this->tradeOffer->createTradeOffer($tradeData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Lakers', $result['tradeText']);
        $this->assertStringContainsString('2024', $result['tradeText']);
        $this->assertStringContainsString('Round 1', $result['tradeText']);
        $this->assertStringContainsString('Protected 1-10', $result['tradeText']);
    }

    /**
     * @test
     */
    public function createTradeOffer_withCashTrade_includesCashInTradeText()
    {
        // Arrange
        $tradeData = $this->getValidTradeData();
        $tradeData['userSendsCash'] = [1 => 100, 2 => 200, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // Act
        $result = $this->tradeOffer->createTradeOffer($tradeData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('cash', $result['tradeText']);
        $this->assertStringContainsString('Team A', $result['tradeText']);
        $this->assertStringContainsString('Team B', $result['tradeText']);
    }

    /**
     * @test
     */
    public function createTradeOffer_withComplexTrade_includesAllComponents()
    {
        // Arrange
        $tradeData = $this->getValidTradeData();
        
        // Add player trade
        $tradeData['check'] = [0 => 'on', 1 => 'on'];
        $tradeData['type'] = [0 => 1, 1 => 0]; // Player and pick
        $tradeData['index'] = [0 => 12345, 1 => 67890];
        $tradeData['contract'] = [0 => 5000, 1 => 0];
        $tradeData['switchCounter'] = 2;
        
        // Add cash
        $tradeData['userSendsCash'] = [1 => 100, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $tradeData['partnerSendsCash'] = [1 => 150, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

        // Mock data for player and pick
        $this->mockDb->setMockData([
            ['name' => 'Star Player', 'pos' => 'SF'], // Player data
            ['teampick' => 'Celtics', 'year' => 2025, 'round' => 2, 'notes' => null] // Pick data
        ]);

        // Act
        $result = $this->tradeOffer->createTradeOffer($tradeData);

        // Assert
        $this->assertTrue($result['success']);
        
        // Should contain player, pick, and cash references
        $this->assertStringContainsString('Star Player', $result['tradeText']);
        $this->assertStringContainsString('Celtics', $result['tradeText']);
        $this->assertStringContainsString('cash', $result['tradeText']);
    }

    /**
     * @test
     */
    public function createTradeOffer_withBilateralTrade_includesBothTeamsItems()
    {
        // Arrange
        $tradeData = $this->getValidTradeData();
        
        // Team A sends player, Team B sends pick
        $tradeData['check'] = [0 => 'on', 2 => 'on']; // Indices 0 and 2
        $tradeData['type'] = [0 => 1, 2 => 0]; // Player and pick
        $tradeData['index'] = [0 => 11111, 2 => 22222];
        $tradeData['contract'] = [0 => 4000, 2 => 0];
        $tradeData['switchCounter'] = 1; // First team has index 0
        $tradeData['fieldsCounter'] = 3; // Total items

        // Mock data
        $this->mockDb->setMockData([
            ['name' => 'Team A Player', 'pos' => 'C'], // Player from Team A
            ['teampick' => 'Warriors', 'year' => 2024, 'round' => 1, 'notes' => null] // Pick from Team B
        ]);

        // Act
        $result = $this->tradeOffer->createTradeOffer($tradeData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Team A Player', $result['tradeText']);
        $this->assertStringContainsString('Warriors', $result['tradeText']);
        $this->assertStringContainsString('Team A', $result['tradeText']);
        $this->assertStringContainsString('Team B', $result['tradeText']);
    }

    /**
     * @test
     */
    public function createTradeOffer_generatesUniqueTradeOfferId()
    {
        // Arrange
        $tradeData1 = $this->getValidTradeData();
        $tradeData2 = $this->getValidTradeData();

        // Mock auto-counter to return different values
        $this->mockDb->setMockData([
            ['counter' => 100], // First call
            ['counter' => 101]  // Second call
        ]);

        // Act
        $result1 = $this->tradeOffer->createTradeOffer($tradeData1);
        $result2 = $this->tradeOffer->createTradeOffer($tradeData2);

        // Assert
        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);
        $this->assertNotEquals($result1['tradeOfferId'], $result2['tradeOfferId']);
    }

    /**
     * Helper method to create valid trade data
     */
    private function getValidTradeData()
    {
        return [
            'offeringTeam' => 'Team A',
            'receivingTeam' => 'Team B',
            'switchCounter' => 0,
            'fieldsCounter' => 0,
            'userSendsCash' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
            'partnerSendsCash' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0],
            'check' => [],
            'contract' => [],
            'index' => [],
            'type' => []
        ];
    }

    /**
     * Data provider for various trade scenarios
     */

    /**
     * @test
     * @dataProvider tradeScenarioProvider
     */
    public function createTradeOffer_withVariousScenarios_handlesCorrectly($scenarioData, $expectedOutcome)
    {
        // Arrange
        $tradeData = array_merge($this->getValidTradeData(), $scenarioData);
        
        if (isset($scenarioData['mockData'])) {
            $this->mockDb->setMockData($scenarioData['mockData']);
        }

        // Act
        $result = $this->tradeOffer->createTradeOffer($tradeData);

        // Assert
        $this->assertEquals($expectedOutcome['success'], $result['success']);
        
        if (isset($expectedOutcome['containsText'])) {
            foreach ($expectedOutcome['containsText'] as $text) {
                $this->assertStringContainsString($text, $result['tradeText'] ?? '');
            }
        }
        
        if (isset($expectedOutcome['hasKey'])) {
            foreach ($expectedOutcome['hasKey'] as $key) {
                $this->assertArrayHasKey($key, $result);
            }
        }
    }

    public function tradeScenarioProvider()
    {
        return [
            'Cash only trade' => [
                [
                    'userSendsCash' => [1 => 500, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0]
                ],
                [
                    'success' => true,
                    'containsText' => ['cash', 'Team A', 'Team B'],
                    'hasKey' => ['tradeText', 'tradeOfferId']
                ]
            ],
            'Player only trade' => [
                [
                    'check' => [0 => 'on'],
                    'type' => [0 => 1],
                    'index' => [0 => 999],
                    'contract' => [0 => 3000],
                    'switchCounter' => 1,
                    'mockData' => [['name' => 'Test Player', 'pos' => 'PF']]
                ],
                [
                    'success' => true,
                    'containsText' => ['Test Player', 'PF'],
                    'hasKey' => ['tradeText']
                ]
            ],
            'Pick only trade' => [
                [
                    'check' => [0 => 'on'],
                    'type' => [0 => 0],
                    'index' => [0 => 888],
                    'switchCounter' => 1,
                    'mockData' => [['teampick' => 'Nets', 'year' => 2026, 'round' => 3, 'notes' => 'Lottery protected']]
                ],
                [
                    'success' => true,
                    'containsText' => ['Nets', '2026', 'Round 3', 'Lottery protected'],
                    'hasKey' => ['tradeText']
                ]
            ]
        ];
    }
}