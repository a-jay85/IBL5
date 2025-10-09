<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Trading_PageRenderer class
 * 
 * Tests HTML rendering methods for trading pages
 */
class PageRendererTest extends TestCase
{
    private $pageRenderer;
    private $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->pageRenderer = new Trading_PageRenderer($this->mockDb);
    }

    protected function tearDown(): void
    {
        $this->pageRenderer = null;
        $this->mockDb = null;
    }

    /**
     * @group page-renderer
     */
    public function testRenderTradeOfferPageOutputsHTML()
    {
        // Arrange
        $userData = ['username' => 'testuser', 'user_ibl_team' => 'Lakers'];
        $userTeamData = [
            'teamID' => 1,
            'teamname' => 'Lakers',
            'players' => $this->mockDb->sql_query('SELECT * FROM ibl_plr'),
            'picks' => $this->mockDb->sql_query('SELECT * FROM ibl_draft_picks')
        ];
        $partnerTeamData = [
            'teamID' => 2,
            'teamname' => 'Celtics',
            'players' => $this->mockDb->sql_query('SELECT * FROM ibl_plr'),
            'picks' => $this->mockDb->sql_query('SELECT * FROM ibl_draft_picks')
        ];
        $futureSalaryUser = ['k' => 5, 'player' => [5000, 5000, 5000, 5000, 5000, 5000]];
        $futureSalaryPartner = ['k' => 10, 'player' => [6000, 6000, 6000, 6000, 6000, 6000]];
        $allTeams = [
            ['name' => 'Lakers', 'city' => 'Los Angeles', 'fullName' => 'Los Angeles Lakers'],
            ['name' => 'Celtics', 'city' => 'Boston', 'fullName' => 'Boston Celtics']
        ];

        // Act
        ob_start();
        $this->pageRenderer->renderTradeOfferPage(
            $userData,
            $userTeamData,
            $partnerTeamData,
            $futureSalaryUser,
            $futureSalaryPartner,
            $allTeams
        );
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('Trade_Offer', $output);
        $this->assertStringContainsString('Lakers', $output);
        $this->assertStringContainsString('Celtics', $output);
        $this->assertStringContainsString('TRADING MENU', $output);
        $this->assertStringContainsString('Make Trade Offer', $output);
    }

    /**
     * @group page-renderer
     */
    public function testRenderTradeReviewPageOutputsHTML()
    {
        // Arrange
        $userData = ['username' => 'testuser', 'user_ibl_team' => 'Lakers'];
        $teamID = 1;
        $this->mockDb->setMockData([
            [
                'tradeofferid' => 1,
                'itemid' => 123,
                'itemtype' => 1,
                'from' => 'Lakers',
                'to' => 'Celtics',
                'approval' => 'Lakers'
            ]
        ]);
        $tradeOffersResult = $this->mockDb->sql_query('SELECT * FROM ibl_trade_info');
        $allTeams = [
            ['name' => 'Lakers', 'city' => 'Los Angeles', 'fullName' => 'Los Angeles Lakers']
        ];

        // Act
        ob_start();
        $this->pageRenderer->renderTradeReviewPage(
            $userData,
            $teamID,
            $tradeOffersResult,
            $allTeams
        );
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('REVIEW TRADE OFFERS', $output);
        $this->assertStringContainsString('Lakers', $output);
        $this->assertStringContainsString('Make Trade Offer To', $output);
    }

    /**
     * @group page-renderer
     */
    public function testRenderTradesNotAllowedMessageWithWaivers()
    {
        // Act
        ob_start();
        $this->pageRenderer->renderTradesNotAllowedMessage('Yes');
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('trades are not allowed', $output);
        $this->assertStringContainsString('Added From Waivers', $output);
        $this->assertStringContainsString('Dropped to Waivers', $output);
    }

    /**
     * @group page-renderer
     */
    public function testRenderTradesNotAllowedMessageWithoutWaivers()
    {
        // Act
        ob_start();
        $this->pageRenderer->renderTradesNotAllowedMessage('No');
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('trades are not allowed', $output);
        $this->assertStringContainsString('waiver wire is also closed', $output);
        $this->assertStringNotContainsString('Added From Waivers', $output);
    }

    /**
     * @group page-renderer
     */
    public function testRenderTradeOfferPageIncludesCashInputs()
    {
        // Arrange
        $userData = ['username' => 'testuser', 'user_ibl_team' => 'Lakers'];
        $userTeamData = [
            'teamID' => 1,
            'teamname' => 'Lakers',
            'players' => $this->mockDb->sql_query('SELECT * FROM ibl_plr'),
            'picks' => $this->mockDb->sql_query('SELECT * FROM ibl_draft_picks')
        ];
        $partnerTeamData = [
            'teamID' => 2,
            'teamname' => 'Celtics',
            'players' => $this->mockDb->sql_query('SELECT * FROM ibl_plr'),
            'picks' => $this->mockDb->sql_query('SELECT * FROM ibl_draft_picks')
        ];
        $futureSalaryUser = ['k' => 5, 'player' => [5000, 5000, 5000, 5000, 5000, 5000]];
        $futureSalaryPartner = ['k' => 10, 'player' => [6000, 6000, 6000, 6000, 6000, 6000]];
        $allTeams = [];

        // Act
        ob_start();
        $this->pageRenderer->renderTradeOfferPage(
            $userData,
            $userTeamData,
            $partnerTeamData,
            $futureSalaryUser,
            $futureSalaryPartner,
            $allTeams
        );
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('userSendsCash', $output);
        $this->assertStringContainsString('partnerSendsCash', $output);
        $this->assertStringContainsString('type="number"', $output);
    }

    /**
     * @group page-renderer
     */
    public function testRenderTradeOfferPageIncludesSalaryCapTotals()
    {
        // Arrange
        $userData = ['username' => 'testuser', 'user_ibl_team' => 'Lakers'];
        $userTeamData = [
            'teamID' => 1,
            'teamname' => 'Lakers',
            'players' => $this->mockDb->sql_query('SELECT * FROM ibl_plr'),
            'picks' => $this->mockDb->sql_query('SELECT * FROM ibl_draft_picks')
        ];
        $partnerTeamData = [
            'teamID' => 2,
            'teamname' => 'Celtics',
            'players' => $this->mockDb->sql_query('SELECT * FROM ibl_plr'),
            'picks' => $this->mockDb->sql_query('SELECT * FROM ibl_draft_picks')
        ];
        $futureSalaryUser = ['k' => 5, 'player' => [5000, 5000, 5000, 5000, 5000, 5000]];
        $futureSalaryPartner = ['k' => 10, 'player' => [6000, 6000, 6000, 6000, 6000, 6000]];
        $allTeams = [];

        // Act
        ob_start();
        $this->pageRenderer->renderTradeOfferPage(
            $userData,
            $userTeamData,
            $partnerTeamData,
            $futureSalaryUser,
            $futureSalaryPartner,
            $allTeams
        );
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('Cap Total', $output);
        $this->assertStringContainsString('5000', $output);
        $this->assertStringContainsString('6000', $output);
    }
}
