<?php

declare(strict_types=1);

namespace Tests\Integration\Trading;

use Tests\Integration\IntegrationTestCase;
use Trading\TradeProcessor;

/**
 * Integration tests for complete trade execution workflows
 *
 * Tests end-to-end scenarios combining validation, item transfers,
 * news creation, and notifications:
 * - Player-for-player trades
 * - Player-for-pick trades
 * - Multi-player/multi-pick trades
 * - Cash transactions
 * - Trade cleanup and notifications
 *
 * @covers \Trading\TradeProcessor
 * @covers \Trading\TradingRepository
 * @covers \Trading\CashTransactionHandler
 * @covers \Trading\TradeValidator
 */
class TradeIntegrationTest extends IntegrationTestCase
{
    private TradeProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new TradeProcessor($this->mockDb);
        
        // Prevent Discord notifications during tests
        $_SERVER['SERVER_NAME'] = 'localhost';
    }

    protected function tearDown(): void
    {
        unset($this->processor);
        unset($_SERVER['SERVER_NAME']);
        parent::tearDown();
    }

    // ========== PLAYER TRANSFER SCENARIOS ==========

    /**
     * @group integration
     * @group player-trades
     */
    public function testCompletePlayerForPlayerTrade(): void
    {
        // Arrange
        $offerId = 123;
        $this->mockDb->setMockTradeInfo([
            ['itemid' => 1001, 'itemtype' => '1', 'from' => 'Lakers', 'to' => 'Celtics'],
            ['itemid' => 1002, 'itemtype' => '1', 'from' => 'Celtics', 'to' => 'Lakers'],
        ]);
        $this->mockDb->setMockData([
            ['pid' => 1001, 'pos' => 'PG', 'name' => 'Player One', 'tid' => 1],
            ['pid' => 1002, 'pos' => 'SG', 'name' => 'Player Two', 'tid' => 2],
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success'], 'Trade processing should succeed');
        $this->assertStringContainsString('Lakers', $result['storytext']);
        $this->assertStringContainsString('Celtics', $result['storytext']);
        $this->assertStringContainsString('Player One', $result['storytext']);
        $this->assertStringContainsString('Player Two', $result['storytext']);

        // Verify player UPDATE queries were executed
        $this->assertGreaterThanOrEqual(2, $this->countQueriesMatching('UPDATE ibl_plr'));
    }

    /**
     * @group integration
     * @group player-trades
     */
    public function testPlayerTransferUpdatesTeamAssignment(): void
    {
        // Arrange
        $offerId = 123;
        $this->mockDb->setMockTradeInfo([
            ['itemid' => 1001, 'itemtype' => '1', 'from' => 'Bulls', 'to' => 'Heat']
        ]);
        $this->mockDb->setMockData([
            ['pos' => 'SF', 'name' => 'Michael Jordan']
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);
        $expectedText = "The Bulls send SF Michael Jordan to the Heat.";
        $this->assertStringContainsString($expectedText, $result['storytext']);

        // Verify UPDATE query includes teamname and tid
        $this->assertQueryExecuted('UPDATE ibl_plr');
        $this->assertQueryExecuted('teamname');
        $this->assertQueryExecuted('tid');
    }

    // ========== DRAFT PICK TRANSFER SCENARIOS ==========

    /**
     * @group integration
     * @group pick-trades
     */
    public function testPlayerForDraftPickTrade(): void
    {
        // Arrange
        $offerId = 124;
        $this->mockDb->setMockTradeInfo([
            ['itemid' => 1001, 'itemtype' => '1', 'from' => 'Knicks', 'to' => 'Nets'],
            ['itemid' => 2001, 'itemtype' => '0', 'from' => 'Nets', 'to' => 'Knicks'],
        ]);
        $this->mockDb->setMockData([
            ['pos' => 'C', 'name' => 'Patrick Ewing'],
            [
                'pickid' => 2001,
                'year' => 2024,
                'teampick' => 'Nets',
                'round' => 1,
                'pick' => 1,
                'ownerofpick' => 'Nets',
                'currentteam' => 'Nets',
                'notes' => null
            ]
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Patrick Ewing', $result['storytext']);
        $this->assertStringContainsString('draft pick', $result['storytext']);

        // Verify both update queries
        $this->assertQueryExecuted('UPDATE ibl_plr');
        $this->assertQueryExecuted('UPDATE ibl_draft_picks');
    }

    /**
     * @group integration
     * @group pick-trades
     */
    public function testDraftPickTransferUpdatesOwnership(): void
    {
        // Arrange
        $offerId = 124;
        $this->mockDb->setMockTradeInfo([
            ['itemid' => 2001, 'itemtype' => '0', 'from' => 'Knicks', 'to' => 'Nets']
        ]);
        $this->mockDb->setMockData([
            [
                'pickid' => 2001,
                'year' => 2024,
                'teampick' => 'Knicks',
                'round' => 1,
                'pick' => 1,
                'ownerofpick' => 'Knicks',
                'currentteam' => 'Knicks',
                'notes' => null
            ]
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);
        $expectedText = "The Knicks send the 2024 Knicks Round 1 draft pick to the Nets.";
        $this->assertStringContainsString($expectedText, $result['storytext']);

        // Verify pick UPDATE query
        $this->assertQueryExecuted('UPDATE ibl_draft_picks');
    }

    // ========== CASH TRANSACTION SCENARIOS ==========

    /**
     * @group integration
     * @group cash-trades
     */
    public function testCashTransactionCreatesPositiveAndNegativeEntries(): void
    {
        // Arrange
        $offerId = 125;
        $this->mockDb->setMockTradeInfo([
            ['itemid' => 30013000, 'itemtype' => 'cash', 'from' => 'Warriors', 'to' => 'Spurs']
        ]);
        $this->mockDb->setMockData([
            [
                'cy1' => 1000,
                'cy2' => 1500,
                'cy3' => 0,
                'cy4' => 0,
                'cy5' => 0,
                'cy6' => 0,
                0 => 1000,
                1 => 1500,
                2 => 0,
                3 => 0,
                4 => 0,
                5 => 0
            ]
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('cash', $result['storytext']);
        $this->assertStringContainsString('Warriors', $result['storytext']);
        $this->assertStringContainsString('Spurs', $result['storytext']);

        // Verify two INSERT queries for positive and negative cash entries
        $this->assertEquals(2, $this->countQueriesMatching('INSERT INTO `ibl_plr`'));
    }

    // ========== MULTI-ASSET TRADES ==========

    /**
     * @group integration
     * @group multi-asset
     */
    public function testMultiPlayerMultiPickTrade(): void
    {
        // Arrange - 2 players and 2 picks
        $offerId = 126;
        $this->mockDb->setMockTradeInfo([
            ['itemid' => 1001, 'itemtype' => '1', 'from' => 'Lakers', 'to' => 'Celtics'],
            ['itemid' => 1002, 'itemtype' => '1', 'from' => 'Lakers', 'to' => 'Celtics'],
            ['itemid' => 2001, 'itemtype' => '0', 'from' => 'Celtics', 'to' => 'Lakers'],
            ['itemid' => 2002, 'itemtype' => '0', 'from' => 'Celtics', 'to' => 'Lakers'],
        ]);
        $this->mockDb->setMockData([
            ['pos' => 'PG', 'name' => 'Magic Johnson'],
            ['pos' => 'SF', 'name' => 'James Worthy'],
            ['pickid' => 2001, 'year' => 2024, 'teampick' => 'Celtics', 'round' => 1],
            ['pickid' => 2002, 'year' => 2025, 'teampick' => 'Celtics', 'round' => 1],
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);

        // Verify all items processed
        $this->assertGreaterThanOrEqual(2, $this->countQueriesMatching('UPDATE ibl_plr'));
        $this->assertGreaterThanOrEqual(2, $this->countQueriesMatching('UPDATE ibl_draft_picks'));
    }

    // ========== VALIDATION FAILURES ==========

    /**
     * @group integration
     * @group validation-failures
     */
    public function testInvalidTradeOfferIdReturnsError(): void
    {
        // Arrange
        $offerId = 999;
        $this->mockDb->setNumRows(0); // No trade data

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('No trade data found', $result['error']);
    }

    // ========== NEWS AND NOTIFICATIONS ==========

    /**
     * @group integration
     * @group notifications
     */
    public function testTradeCreatesNewsStory(): void
    {
        // Arrange
        $offerId = 127;
        $this->mockDb->setMockTradeInfo([
            ['itemid' => 1001, 'itemtype' => '1', 'from' => 'Rockets', 'to' => 'Mavs']
        ]);
        $this->mockDb->setMockData([
            ['pos' => 'C', 'name' => 'Hakeem Olajuwon']
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertQueryExecuted('INSERT INTO nuke_stories');
    }

    // ========== CLEANUP ==========

    /**
     * @group integration
     * @group cleanup
     */
    public function testTradeCleanupDeletesTradeData(): void
    {
        // Arrange
        $offerId = 128;
        $this->mockDb->setMockTradeInfo([
            ['itemid' => 1001, 'itemtype' => '1', 'from' => 'Jazz', 'to' => 'Suns']
        ]);
        $this->mockDb->setMockData([
            ['pos' => 'PF', 'name' => 'Karl Malone']
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertQueryExecuted('DELETE FROM ibl_trade_info');
        $this->assertQueryExecuted('DELETE FROM ibl_trade_cash');
    }
}
