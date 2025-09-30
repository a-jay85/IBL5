<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Trading_TradeProcessor class
 * 
 * Tests trade processing workflow including player transfers,
 * draft pick trades, cash transactions, and notifications.
 */
class TradeProcessorTest extends TestCase
{
    private $processor;
    private $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->processor = new Trading_TradeProcessor($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->processor = null;
        $this->mockDb = null;
    }

    /**
     * @test
     */
    public function processTrade_withValidTradeId_returnsSuccess()
    {
        // Arrange
        $offerId = 123;
        $this->mockDb->setMockData([
            [
                'itemid' => '456',
                'itemtype' => '1', // Player
                'from' => 'Team A',
                'to' => 'Team B'
            ]
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('storytext', $result);
        $this->assertArrayHasKey('storytitle', $result);
    }

    /**
     * @test
     */
    public function processTrade_withInvalidTradeId_returnsError()
    {
        // Arrange
        $offerId = 999;
        $this->mockDb->setMockData([]); // No trade data

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('No trade data found', $result['error']);
    }

    /**
     * @test
     */
    public function processTrade_withPlayerTrade_includesPlayerInStoryText()
    {
        // Arrange
        $offerId = 123;
        $this->mockDb->setMockData([
            [
                'itemid' => '456',
                'itemtype' => '1', // Player
                'from' => 'Lakers',
                'to' => 'Celtics'
            ]
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Lakers', $result['storytext']);
        $this->assertStringContainsString('Celtics', $result['storytext']);
        $this->assertStringContainsString('Lakers and Celtics make a trade', $result['storytitle']);
    }

    /**
     * @test
     */
    public function processTrade_withDraftPickTrade_includesPickInStoryText()
    {
        // Arrange
        $offerId = 123;
        $this->mockDb->setMockData([
            [
                'itemid' => '789',
                'itemtype' => '0', // Draft pick
                'from' => 'Warriors',
                'to' => 'Knicks'
            ]
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Warriors', $result['storytext']);
        $this->assertStringContainsString('Knicks', $result['storytext']);
        $this->assertStringContainsString('draft pick', $result['storytext']);
    }

    /**
     * @test
     */
    public function processTrade_withCashTrade_includesCashInStoryText()
    {
        // Arrange
        $offerId = 123;
        $this->mockDb->setMockData([
            [
                'itemid' => '12345',
                'itemtype' => 'cash',
                'from' => 'Bulls',
                'to' => 'Heat'
            ]
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Bulls', $result['storytext']);
        $this->assertStringContainsString('Heat', $result['storytext']);
        $this->assertStringContainsString('cash', $result['storytext']);
    }

    /**
     * @test
     */
    public function processTrade_withMultipleItems_processesAllItems()
    {
        // Arrange
        $offerId = 123;
        $this->mockDb->setMockData([
            [
                'itemid' => '456',
                'itemtype' => '1', // Player
                'from' => 'Spurs',
                'to' => 'Rockets'
            ],
            [
                'itemid' => '789',
                'itemtype' => '0', // Draft pick
                'from' => 'Rockets',
                'to' => 'Spurs'
            ]
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Spurs', $result['storytext']);
        $this->assertStringContainsString('Rockets', $result['storytext']);
        
        // Should contain both player and draft pick references
        $storyLines = explode('<br>', $result['storytext']);
        $this->assertGreaterThanOrEqual(2, count($storyLines));
    }

    /**
     * Test edge cases and error handling
     */

    /**
     * @test
     */
    public function processTrade_withEmptyOfferId_handlesGracefully()
    {
        // Arrange
        $offerId = null;

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert - Should handle gracefully without throwing exceptions
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * @test
     */
    public function processTrade_withUnknownItemType_handlesGracefully()
    {
        // Arrange
        $offerId = 123;
        $this->mockDb->setMockData([
            [
                'itemid' => '456',
                'itemtype' => 'unknown', // Unknown type
                'from' => 'Team A',
                'to' => 'Team B'
            ]
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert - Should not crash, but may not process the item
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
    }

    /**
     * Integration-style tests for complete workflows
     */

    /**
     * @test
     */
    public function processTrade_completePlayerTradeWorkflow_executesAllSteps()
    {
        // Arrange
        $offerId = 123;
        $this->mockDb->setMockData([
            [
                'itemid' => '456',
                'itemtype' => '1',
                'from' => 'Miami Heat',
                'to' => 'Boston Celtics'
            ]
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success'], 'Trade processing should succeed');
        
        // Verify story creation
        $this->assertNotEmpty($result['storytext'], 'Story text should be generated');
        $this->assertNotEmpty($result['storytitle'], 'Story title should be generated');
        
        // Verify story content
        $this->assertStringContainsString('Miami Heat', $result['storytext']);
        $this->assertStringContainsString('Boston Celtics', $result['storytext']);
        $this->assertEquals('Miami Heat and Boston Celtics make a trade.', $result['storytitle']);
    }

    /**
     * @test
     */
    public function processTrade_withComplexMultiTeamTrade_generatesCorrectStory()
    {
        // Arrange - Simulate a three-way trade scenario
        $offerId = 456;
        $this->mockDb->setMockData([
            [
                'itemid' => '101',
                'itemtype' => '1', // Player A to Team B
                'from' => 'Team A',
                'to' => 'Team B'
            ],
            [
                'itemid' => '102', 
                'itemtype' => '0', // Pick from Team B to Team A
                'from' => 'Team B',
                'to' => 'Team A'
            ]
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);
        
        // Should reference both teams
        $this->assertStringContainsString('Team A', $result['storytext']);
        $this->assertStringContainsString('Team B', $result['storytext']);
        
        // Story should contain multiple lines for multiple items
        $storyLines = array_filter(explode('<br>', $result['storytext']));
        $this->assertGreaterThanOrEqual(2, count($storyLines), 'Should have multiple trade lines');
    }
}