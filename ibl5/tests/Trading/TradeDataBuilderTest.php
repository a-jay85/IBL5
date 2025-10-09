<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Trading_TradeDataBuilder class
 * 
 * Tests data retrieval and preparation methods
 */
class TradeDataBuilderTest extends TestCase
{
    private $dataBuilder;
    private $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->dataBuilder = new Trading_TradeDataBuilder($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->dataBuilder = null;
        $this->mockDb = null;
    }

    /**
     * @group data-builder
     */
    public function testGetBoardConfigReturnsConfiguration()
    {
        // Arrange
        $this->mockDb->setMockData([
            ['config_name' => 'site_name', 'config_value' => 'IBL5'],
            ['config_name' => 'allow_trades', 'config_value' => 'Yes']
        ]);

        // Act
        $config = $this->dataBuilder->getBoardConfig('nuke');

        // Assert
        $this->assertIsArray($config);
        $this->assertArrayHasKey('site_name', $config);
        $this->assertEquals('IBL5', $config['site_name']);
        $this->assertArrayHasKey('allow_trades', $config);
        $this->assertEquals('Yes', $config['allow_trades']);
    }

    /**
     * @group data-builder
     */
    public function testGetUserInfoReturnsUserData()
    {
        // Arrange
        $this->mockDb->setMockData([
            ['username' => 'testuser', 'user_ibl_team' => 'Lakers']
        ]);

        // Act
        $userInfo = $this->dataBuilder->getUserInfo('testuser', 'nuke');

        // Assert
        $this->assertIsArray($userInfo);
        $this->assertEquals('testuser', $userInfo['username']);
        $this->assertEquals('Lakers', $userInfo['user_ibl_team']);
    }

    /**
     * @group data-builder
     */
    public function testGetTeamTradeDataReturnsCompleteTeamInfo()
    {
        // Arrange
        $this->mockDb->setReturnTrue(true);

        // Act
        $teamData = $this->dataBuilder->getTeamTradeData('Lakers');

        // Assert
        $this->assertIsArray($teamData);
        $this->assertArrayHasKey('teamID', $teamData);
        $this->assertArrayHasKey('teamname', $teamData);
        $this->assertArrayHasKey('players', $teamData);
        $this->assertArrayHasKey('picks', $teamData);
        $this->assertEquals('Lakers', $teamData['teamname']);
    }

    /**
     * @group data-builder
     */
    public function testGetCashDetailsReturnsAllSixYears()
    {
        // Arrange
        $this->mockDb->setMockData([
            [
                'cy1' => 100,
                'cy2' => 200,
                'cy3' => 0,
                'cy4' => 0,
                'cy5' => 0,
                'cy6' => 0
            ]
        ]);

        // Act
        $cashDetails = $this->dataBuilder->getCashDetails(1, 'Lakers');

        // Assert
        $this->assertIsArray($cashDetails);
        $this->assertCount(6, $cashDetails);
        $this->assertEquals(100, $cashDetails[1]);
        $this->assertEquals(200, $cashDetails[2]);
        $this->assertEquals(0, $cashDetails[3]);
    }

    /**
     * @group data-builder
     */
    public function testGetCashDetailsHandlesNoCashData()
    {
        // Arrange
        $this->mockDb->setMockData([]);

        // Act
        $cashDetails = $this->dataBuilder->getCashDetails(1, 'Lakers');

        // Assert
        $this->assertIsArray($cashDetails);
        $this->assertCount(6, $cashDetails);
        $this->assertEquals(0, $cashDetails[1]);
        $this->assertEquals(0, $cashDetails[2]);
        $this->assertEquals(0, $cashDetails[6]);
    }

    /**
     * @group data-builder
     */
    public function testGetDraftPickDetailsReturnsPickInfo()
    {
        // Arrange
        $this->mockDb->setMockData([
            [
                'pickid' => 1,
                'year' => 2024,
                'round' => 1,
                'teampick' => 'Lakers',
                'notes' => 'Top 3 protected'
            ]
        ]);

        // Act
        $pickDetails = $this->dataBuilder->getDraftPickDetails(1);

        // Assert
        $this->assertIsArray($pickDetails);
        $this->assertEquals(1, $pickDetails['pickid']);
        $this->assertEquals(2024, $pickDetails['year']);
        $this->assertEquals(1, $pickDetails['round']);
        $this->assertEquals('Lakers', $pickDetails['teampick']);
        $this->assertEquals('Top 3 protected', $pickDetails['notes']);
    }

    /**
     * @group data-builder
     */
    public function testGetPlayerDetailsReturnsPlayerInfo()
    {
        // Arrange
        $this->mockDb->setMockData([
            [
                'pid' => 123,
                'name' => 'LeBron James',
                'pos' => 'SF',
                'teamname' => 'Lakers'
            ]
        ]);

        // Act
        $playerDetails = $this->dataBuilder->getPlayerDetails(123);

        // Assert
        $this->assertIsArray($playerDetails);
        $this->assertEquals(123, $playerDetails['pid']);
        $this->assertEquals('LeBron James', $playerDetails['name']);
        $this->assertEquals('SF', $playerDetails['pos']);
        $this->assertEquals('Lakers', $playerDetails['teamname']);
    }

    /**
     * @group data-builder
     */
    public function testGetAllTradeOffersReturnsResult()
    {
        // Act
        $result = $this->dataBuilder->getAllTradeOffers();

        // Assert
        $this->assertNotNull($result);
    }

    /**
     * @group data-builder
     */
    public function testGetTradeOfferDetailsReturnsItemsArray()
    {
        // Arrange
        $this->mockDb->setMockData([
            ['tradeofferid' => 1, 'itemid' => 123, 'itemtype' => 1, 'from' => 'Lakers', 'to' => 'Celtics'],
            ['tradeofferid' => 1, 'itemid' => 456, 'itemtype' => 0, 'from' => 'Celtics', 'to' => 'Lakers']
        ]);

        // Act
        $items = $this->dataBuilder->getTradeOfferDetails(1);

        // Assert
        $this->assertIsArray($items);
        $this->assertCount(2, $items);
        $this->assertEquals(123, $items[0]['itemid']);
        $this->assertEquals(456, $items[1]['itemid']);
    }
}
