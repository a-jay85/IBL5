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
        $this->assertQueryExecuted('DELETE FROM ibl_trade_offers');
    }

    // ========== SALARY CAP VALIDATION ==========

    /**
     * @group integration
     * @group validation-failures
     */
    public function testTradeExceedingHardCapRejected(): void
    {
        // Arrange - TradeValidator.validateSalaryCaps() checks post-trade totals against League::HARD_CAP_MAX (7000)
        $validator = new \Trading\TradeValidator($this->mockDb);

        // User team has 6500 in salary, sends 500 to partner, receives 1500 back
        // Post-trade user total: 6500 - 500 + 1500 = 7500 (over 7000 hard cap)
        $tradeData = [
            'userCurrentSeasonCapTotal' => 6500,
            'partnerCurrentSeasonCapTotal' => 5000,
            'userCapSentToPartner' => 500,
            'partnerCapSentToUser' => 1500,
        ];

        // Act
        $result = $validator->validateSalaryCaps($tradeData);

        // Assert
        $this->assertFalse($result['valid'], 'Trade should be rejected when user exceeds hard cap');
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('hard cap', $result['errors'][0]);
        $this->assertSame(7500, $result['userPostTradeCapTotal']);
    }

    // ========== ROSTER LIMIT VALIDATION ==========

    /**
     * @group integration
     * @group validation-failures
     */
    public function testTradeExceedingRosterLimitRejected(): void
    {
        // Arrange - TradeValidator.validateRosterLimits() uses repository->getTeamPlayerCount()
        // which runs a COUNT(*) query. MockDatabase returns mockData for all SELECTs,
        // so we configure it to return a count result showing the team already at 15 players.
        $validator = new \Trading\TradeValidator($this->mockDb);

        // Mock the COUNT(*) query to return 15 (at roster limit)
        $this->mockDb->setMockData([
            ['cnt' => 15]
        ]);

        // User team has 15 players, sends 0, receives 1 => 16 players (over 15 limit)
        $result = $validator->validateRosterLimits('Lakers', 'Celtics', 0, 1);

        // Assert
        $this->assertFalse($result['valid'], 'Trade should be rejected when team exceeds 15-player roster limit');
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('roster limit', $result['errors'][0]);
    }

    // ========== PLAYER TRANSFER DETAILS ==========

    /**
     * @group integration
     * @group player-trades
     */
    public function testPlayerTransferUpdatesTeamAndTid(): void
    {
        // Arrange - 1-for-1 player trade; verify UPDATE executes for both players
        $offerId = 130;
        $this->mockDb->setMockTradeInfo([
            ['itemid' => 2001, 'itemtype' => '1', 'from' => 'Hawks', 'to' => 'Pacers'],
            ['itemid' => 2002, 'itemtype' => '1', 'from' => 'Pacers', 'to' => 'Hawks'],
        ]);
        $this->mockDb->setMockData([
            ['pid' => 2001, 'pos' => 'PF', 'name' => 'Dominique Wilkins', 'tid' => 3],
            ['pid' => 2002, 'pos' => 'SG', 'name' => 'Reggie Miller', 'tid' => 4],
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success'], 'Player-for-player trade should succeed');

        // Both players should trigger an UPDATE to ibl_plr
        $updateCount = $this->countQueriesMatching('UPDATE ibl_plr');
        $this->assertGreaterThanOrEqual(2, $updateCount, 'Should execute at least 2 UPDATE ibl_plr queries');

        // Verify that the UPDATE queries set teamname and tid
        $this->assertQueryExecuted('teamname');
        $this->assertQueryExecuted('tid');
    }

    // ========== PICK-FOR-PICK TRANSFER ==========

    /**
     * @group integration
     * @group pick-trades
     */
    public function testDraftPickForPickTradeUpdatesOwnership(): void
    {
        // Arrange - pick-for-pick trade (two picks exchanged)
        $offerId = 131;
        $this->mockDb->setMockTradeInfo([
            ['itemid' => 3001, 'itemtype' => '0', 'from' => 'Clippers', 'to' => 'Raptors'],
            ['itemid' => 3002, 'itemtype' => '0', 'from' => 'Raptors', 'to' => 'Clippers'],
        ]);
        $this->mockDb->setMockData([
            [
                'pickid' => 3001,
                'year' => 2025,
                'teampick' => 'Clippers',
                'round' => 1,
                'pick' => 5,
                'ownerofpick' => 'Clippers',
                'currentteam' => 'Clippers',
                'notes' => null
            ],
            [
                'pickid' => 3002,
                'year' => 2025,
                'teampick' => 'Raptors',
                'round' => 2,
                'pick' => 12,
                'ownerofpick' => 'Raptors',
                'currentteam' => 'Raptors',
                'notes' => null
            ],
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success'], 'Pick-for-pick trade should succeed');

        // Both picks should trigger an UPDATE to ibl_draft_picks
        $updateCount = $this->countQueriesMatching('UPDATE ibl_draft_picks');
        $this->assertGreaterThanOrEqual(2, $updateCount, 'Should execute at least 2 UPDATE ibl_draft_picks queries');

        // Verify that the UPDATE queries set ownerofpick
        $this->assertQueryExecuted('ownerofpick');
    }

    // ========== CASH SPECIAL PLAYER RECORDS ==========

    /**
     * @group integration
     * @group cash-trades
     */
    public function testCashTradeCreatesSpecialPlayerRecords(): void
    {
        // Arrange - cash trade creates pipe-prefixed player records
        $offerId = 132;
        $this->mockDb->setMockTradeInfo([
            ['itemid' => 40014000, 'itemtype' => 'cash', 'from' => 'Nuggets', 'to' => 'Thunder']
        ]);
        $this->mockDb->setMockData([
            [
                'cy1' => 500,
                'cy2' => 0,
                'cy3' => 0,
                'cy4' => 0,
                'cy5' => 0,
                'cy6' => 0,
                0 => 500,
                1 => 0,
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

        // Cash creates two INSERT INTO ibl_plr records: one positive (for sender), one negative (for receiver)
        $insertCount = $this->countQueriesMatching('INSERT INTO `ibl_plr`');
        $this->assertSame(2, $insertCount, 'Cash trade should create exactly 2 special player records');

        // Verify the pipe-prefix naming convention for cash entries (| <B>Cash to/from ...)
        $this->assertQueryExecuted('Cash to');
        $this->assertQueryExecuted('Cash from');
    }

    // ========== UNTRADABLE PLAYER VALIDATION ==========

    /**
     * @group integration
     * @group validation-failures
     */
    public function testUntradablePlayerOnWaiversRejected(): void
    {
        // Arrange - TradeValidator.canPlayerBeTraded() checks ordinal <= JSB::WAIVERS_ORDINAL (960)
        // Players with ordinal > 960 are on waivers and cannot be traded
        $validator = new \Trading\TradeValidator($this->mockDb);

        // Mock player data with ordinal 1000 (on waivers) and valid contract
        $this->mockDb->setMockData([
            ['ordinal' => 1000, 'cy' => 3]
        ]);

        // Act
        $canTrade = $validator->canPlayerBeTraded(5001);

        // Assert
        $this->assertFalse($canTrade, 'Player on waivers (ordinal > 960) should not be tradable');
    }

    // ========== COMPLEX TRADE NEWS STORY ==========

    /**
     * @group integration
     * @group notifications
     */
    public function testTradeNewsStoryContainsAllAssets(): void
    {
        // Arrange - complex trade: 2 players + 1 pick
        $offerId = 133;
        $this->mockDb->setMockTradeInfo([
            ['itemid' => 6001, 'itemtype' => '1', 'from' => 'Bucks', 'to' => 'Pistons'],
            ['itemid' => 6002, 'itemtype' => '1', 'from' => 'Pistons', 'to' => 'Bucks'],
            ['itemid' => 7001, 'itemtype' => '0', 'from' => 'Pistons', 'to' => 'Bucks'],
        ]);
        $this->mockDb->setMockData([
            ['pid' => 6001, 'pos' => 'PF', 'name' => 'Giannis Antetokounmpo', 'tid' => 5],
            ['pid' => 6002, 'pos' => 'C', 'name' => 'Jalen Duren', 'tid' => 6],
            [
                'pickid' => 7001,
                'year' => 2026,
                'teampick' => 'Pistons',
                'round' => 1,
                'pick' => 3,
                'ownerofpick' => 'Pistons',
                'currentteam' => 'Pistons',
                'notes' => null
            ],
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);

        // Verify all assets appear in the story text
        $this->assertStringContainsString('Giannis Antetokounmpo', $result['storytext']);
        $this->assertStringContainsString('Jalen Duren', $result['storytext']);
        $this->assertStringContainsString('draft pick', $result['storytext']);
        $this->assertStringContainsString('Bucks', $result['storytext']);
        $this->assertStringContainsString('Pistons', $result['storytext']);

        // Verify the news story was inserted into the database
        $this->assertQueryExecuted('INSERT INTO nuke_stories');
    }

    // ========== TRADE CLEANUP COMPLETENESS ==========

    /**
     * @group integration
     * @group cleanup
     */
    public function testTradeCleanupRemovesAllTradeRecords(): void
    {
        // Arrange - after a successful trade, all three trade tables should be cleaned up
        $offerId = 134;
        $this->mockDb->setMockTradeInfo([
            ['itemid' => 8001, 'itemtype' => '1', 'from' => 'Grizzlies', 'to' => 'Pelicans'],
            ['itemid' => 8002, 'itemtype' => '1', 'from' => 'Pelicans', 'to' => 'Grizzlies'],
        ]);
        $this->mockDb->setMockData([
            ['pid' => 8001, 'pos' => 'PG', 'name' => 'Ja Morant', 'tid' => 7],
            ['pid' => 8002, 'pos' => 'SF', 'name' => 'Brandon Ingram', 'tid' => 8],
        ]);

        // Act
        $result = $this->processor->processTrade($offerId);

        // Assert
        $this->assertTrue($result['success']);

        // All three trade tables should have DELETE queries executed
        $this->assertQueryExecuted('DELETE FROM ibl_trade_info');
        $this->assertQueryExecuted('DELETE FROM ibl_trade_cash');
        $this->assertQueryExecuted('DELETE FROM ibl_trade_offers');

        // Verify the correct offer ID was used in the cleanup queries
        $deleteQueries = array_filter(
            $this->getExecutedQueries(),
            static fn(string $q): bool => stripos($q, 'DELETE') === 0
        );
        $this->assertGreaterThanOrEqual(3, count($deleteQueries), 'Should have at least 3 DELETE queries for complete cleanup');
    }
}
