<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PlayerSearch\PlayerSearchService;
use PlayerSearch\PlayerSearchValidator;
use PlayerSearch\PlayerSearchRepository;
use PlayerSearch\PlayerSearchView;
use Player\PlayerRepository;
use Player\PlayerData;

/**
 * Tests for PlayerSearchView
 * 
 * Tests HTML rendering functionality for player search.
 */
final class PlayerSearchViewTest extends TestCase
{
    private PlayerSearchView $view;
    private PlayerSearchService $service;

    protected function setUp(): void
    {
        $validator = new PlayerSearchValidator();
        $mockSearchRepository = $this->createMock(PlayerSearchRepository::class);
        $mockPlayerRepository = $this->createMock(PlayerRepository::class);
        $this->service = new PlayerSearchService($validator, $mockSearchRepository, $mockPlayerRepository);
        $this->view = new PlayerSearchView($this->service);
    }

    // ========== Search Form Rendering Tests ==========

    public function testRenderSearchFormCreatesValidHtml(): void
    {
        $html = $this->view->renderSearchForm([]);

        // Check form structure
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('name="Search"', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('action="modules.php?name=Player_Search"', $html);
    }

    public function testRenderSearchFormContainsAllInputFields(): void
    {
        $html = $this->view->renderSearchForm([]);

        // Check for position select
        $this->assertStringContainsString('name="pos"', $html);
        
        // Check for main filters
        $this->assertStringContainsString('name="age"', $html);
        $this->assertStringContainsString('name="college"', $html);
        $this->assertStringContainsString('name="search_name"', $html);
        
        // Check for rating filters
        $this->assertStringContainsString('name="r_fga"', $html);
        $this->assertStringContainsString('name="r_fgp"', $html);
        
        // Check for skill filters
        $this->assertStringContainsString('name="oo"', $html);
        $this->assertStringContainsString('name="do"', $html);
        
        // Check for hidden submit field
        $this->assertStringContainsString('name="submitted"', $html);
        $this->assertStringContainsString('value="1"', $html);
    }

    public function testRenderSearchFormPopulatesExistingValues(): void
    {
        $params = [
            'pos' => 'PG',
            'age' => 25,
            'search_name' => 'Jordan',
            'college' => 'North Carolina',
            'oo' => 80,
        ];

        $html = $this->view->renderSearchForm($params);

        // Check that values are populated and escaped
        $this->assertStringContainsString('value="25"', $html);
        $this->assertStringContainsString('value="Jordan"', $html);
        $this->assertStringContainsString('value="North Carolina"', $html);
        $this->assertStringContainsString('value="80"', $html);
        
        // Check that position is selected
        $this->assertStringContainsString('value="PG" selected', $html);
    }

    public function testRenderSearchFormEscapesHtmlInValues(): void
    {
        $params = [
            'search_name' => '<script>alert("XSS")</script>',
            'college' => '<b>Test</b>',
        ];

        $html = $this->view->renderSearchForm($params);

        // Should not contain raw script tags
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
    }

    public function testRenderSearchFormContainsPositionOptions(): void
    {
        $html = $this->view->renderSearchForm([]);

        // Check for all position options
        $this->assertStringContainsString('value="PG"', $html);
        $this->assertStringContainsString('value="SG"', $html);
        $this->assertStringContainsString('value="SF"', $html);
        $this->assertStringContainsString('value="PF"', $html);
        $this->assertStringContainsString('value="C"', $html);
    }

    // ========== Table Header Rendering Tests ==========

    public function testRenderTableHeaderCreatesValidTable(): void
    {
        $html = $this->view->renderTableHeader();

        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('class="sortable"', $html);
        $this->assertStringContainsString('<tr>', $html);
        $this->assertStringContainsString('<th>', $html);
    }

    public function testRenderTableHeaderContainsAllColumns(): void
    {
        $html = $this->view->renderTableHeader();

        // Check for all column headers
        $this->assertStringContainsString('>Pos<', $html);
        $this->assertStringContainsString('>Player<', $html);
        $this->assertStringContainsString('>Age<', $html);
        $this->assertStringContainsString('>Team<', $html);
        $this->assertStringContainsString('>Exp<', $html);
        $this->assertStringContainsString('>Bird<', $html);
        $this->assertStringContainsString('>2ga<', $html);
        $this->assertStringContainsString('>2gp<', $html);
        $this->assertStringContainsString('>oo<', $html);
        $this->assertStringContainsString('>Talent<', $html);
        $this->assertStringContainsString('>College<', $html);
    }

    // ========== Player Row Rendering Tests ==========

    public function testRenderPlayerRowCreatesValidRow(): void
    {
        $player = $this->createTestPlayer();

        $html = $this->view->renderPlayerRow($player, 0);

        $this->assertStringContainsString('<tr', $html);
        $this->assertStringContainsString('</tr>', $html);
        $this->assertStringContainsString('<td', $html);
    }

    public function testRenderPlayerRowIncludesPlayerLink(): void
    {
        $player = $this->createTestPlayer();

        $html = $this->view->renderPlayerRow($player, 0);

        $this->assertStringContainsString('href="modules.php?name=Player&amp;pa=showpage&amp;pid=123"', $html);
        $this->assertStringContainsString('>Test Player<', $html);
    }

    public function testRenderPlayerRowIncludesTeamLink(): void
    {
        $player = $this->createTestPlayer();

        $html = $this->view->renderPlayerRow($player, 0);

        $this->assertStringContainsString('href="team.php?tid=5"', $html);
        $this->assertStringContainsString('>Test Team<', $html);
    }

    public function testRenderPlayerRowAlternatesRowColors(): void
    {
        $player = $this->createTestPlayer();

        $htmlEven = $this->view->renderPlayerRow($player, 0);
        $htmlOdd = $this->view->renderPlayerRow($player, 1);

        $this->assertStringContainsString('#e6e7e2', $htmlEven);
        $this->assertStringContainsString('#ffffff', $htmlOdd);
    }

    public function testRenderPlayerRowShowsRetiredStatus(): void
    {
        $player = $this->createTestPlayer();
        $player->isRetired = 1;

        $html = $this->view->renderPlayerRow($player, 0);

        $this->assertStringContainsString('Retired', $html);
        $this->assertStringContainsString('colspan="31"', $html);
    }

    public function testRenderPlayerRowEscapesPlayerName(): void
    {
        $player = $this->createTestPlayer();
        $player->name = '<script>alert("XSS")</script>';

        $html = $this->view->renderPlayerRow($player, 0);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderPlayerRowEscapesTeamName(): void
    {
        $player = $this->createTestPlayer();
        $player->teamName = '<img src=x onerror=alert(1)>';

        $html = $this->view->renderPlayerRow($player, 0);

        // Should escape the < and > characters, preventing XSS
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function testRenderPlayerRowDisplaysAllStats(): void
    {
        $player = $this->createTestPlayer();

        $html = $this->view->renderPlayerRow($player, 0);

        // Check various stats are displayed
        $this->assertStringContainsString('>25<', $html); // age
        $this->assertStringContainsString('>5<', $html); // exp/tid
    }

    // ========== Table Footer Rendering Tests ==========

    public function testRenderTableFooterClosesTable(): void
    {
        $html = $this->view->renderTableFooter();

        $this->assertStringContainsString('</table>', $html);
    }

    // ========== Helper Methods ==========

    /**
     * Create a test PlayerData object with all required fields
     */
    private function createTestPlayer(): PlayerData
    {
        $player = $this->createMock(PlayerData::class);
        $player->playerID = 123;
        $player->name = 'Test Player';
        $player->position = 'PG';
        $player->teamID = 5;
        $player->teamName = 'Test Team';
        $player->isRetired = 0;
        $player->age = 25;
        $player->yearsOfExperience = 5;
        $player->collegeName = 'UCLA';
        $player->birdYears = 3;
        $player->ratingFieldGoalAttempts = 60;
        $player->ratingFieldGoalPercentage = 55;
        $player->ratingFreeThrowAttempts = 70;
        $player->ratingFreeThrowPercentage = 85;
        $player->ratingThreePointAttempts = 40;
        $player->ratingThreePointPercentage = 38;
        $player->ratingOffensiveRebounds = 45;
        $player->ratingDefensiveRebounds = 50;
        $player->ratingAssists = 75;
        $player->ratingSteals = 65;
        $player->ratingTurnovers = 30;
        $player->ratingBlocks = 35;
        $player->ratingFouls = 40;
        $player->ratingOutsideOffense = 80;
        $player->ratingOutsideDefense = 75;
        $player->ratingDriveOffense = 60;
        $player->ratingDriveDefense = 85;
        $player->ratingPostOffense = 70;
        $player->ratingPostDefense = 65;
        $player->ratingTransitionOffense = 55;
        $player->ratingTransitionDefense = 78;
        $player->ratingTalent = 85;
        $player->ratingSkill = 80;
        $player->ratingIntangibles = 75;
        $player->ratingClutch = 90;
        $player->ratingConsistency = 85;
        
        return $player;
    }
}

