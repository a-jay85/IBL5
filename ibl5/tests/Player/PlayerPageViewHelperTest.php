<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Player\PlayerPageViewHelper;
use Player\Player;
use Player\PlayerStats;

class PlayerPageViewHelperTest extends TestCase
{
    private $viewHelper;

    protected function setUp(): void
    {
        $this->viewHelper = new PlayerPageViewHelper();
    }

    public function testRenderPlayerHeaderWithoutNickname()
    {
        $player = $this->createMockPlayer('John Doe', null, 'PG', 1, 'Test Team');
        
        $html = $this->viewHelper->renderPlayerHeader($player, 123);
        
        $this->assertStringContainsString('PG John Doe', $html);
        $this->assertStringContainsString('Test Team', $html);
        $this->assertStringNotContainsString('Nickname', $html);
        $this->assertStringContainsString('images/player/123.jpg', $html);
    }

    public function testRenderPlayerHeaderWithNickname()
    {
        $player = $this->createMockPlayer('John Doe', 'Johnny', 'PG', 1, 'Test Team');
        
        $html = $this->viewHelper->renderPlayerHeader($player, 123);
        
        $this->assertStringContainsString('PG John Doe', $html);
        $this->assertStringContainsString('Nickname: "Johnny"', $html);
        $this->assertStringContainsString('Test Team', $html);
    }

    public function testRenderRookieOptionUsedMessage()
    {
        $html = $this->viewHelper->renderRookieOptionUsedMessage();
        
        $this->assertStringContainsString('ROOKIE OPTION', $html);
        $this->assertStringContainsString('USED; RENEGOTIATION', $html);
        $this->assertStringContainsString('IMPOSSIBLE', $html);
        $this->assertStringContainsString('background-color: #ff0000', $html);
    }

    public function testRenderRenegotiationButton()
    {
        $html = $this->viewHelper->renderRenegotiationButton(123);
        
        $this->assertStringContainsString('RENEGOTIATE', $html);
        $this->assertStringContainsString('CONTRACT', $html);
        $this->assertStringContainsString('modules.php?name=Player&pa=negotiate&pid=123', $html);
        $this->assertStringContainsString('background-color: #ff0000', $html);
    }

    public function testRenderRookieOptionButton()
    {
        $html = $this->viewHelper->renderRookieOptionButton(123);
        
        $this->assertStringContainsString('ROOKIE', $html);
        $this->assertStringContainsString('OPTION', $html);
        $this->assertStringContainsString('modules.php?name=Player&pa=rookieoption&pid=123', $html);
        $this->assertStringContainsString('background-color: #ffbb00', $html);
    }

    public function testRenderPlayerBioSection()
    {
        $player = $this->createMockPlayerWithBio();
        $contractDisplay = '1000/1100/1200';
        
        $html = $this->viewHelper->renderPlayerBioSection($player, $contractDisplay);
        
        // Check bio information
        $this->assertStringContainsString('Age: 25', $html);
        $this->assertStringContainsString('Height: 6-4', $html);
        $this->assertStringContainsString('Weight: 200', $html);
        $this->assertStringContainsString('College: Test University', $html);
        
        // Check draft information
        $this->assertStringContainsString('Drafted by the Test Franchise', $html);
        $this->assertStringContainsString('# 10 pick of round 1', $html);
        $this->assertStringContainsString('2020 Draft', $html);
        
        // Check contract information - use more flexible checks
        $this->assertStringContainsString('BIRD YEARS', $html);
        $this->assertStringContainsString('Remaining Contract', $html);
        $this->assertStringContainsString('1000/1100/1200', $html);
        
        // Check ratings table headers are present
        $this->assertStringContainsString('<strong>2ga</strong>', $html);
        $this->assertStringContainsString('<strong>fta</strong>', $html);
        $this->assertStringContainsString('<strong>3ga</strong>', $html);
    }

    public function testRenderPlayerHighsTable()
    {
        $playerStats = $this->createMockPlayerStats();
        
        $html = $this->viewHelper->renderPlayerHighsTable($playerStats);
        
        // Check table structure
        $this->assertStringContainsString('PLAYER HIGHS', $html);
        $this->assertStringContainsString('Regular-Season', $html);
        $this->assertStringContainsString('Playoffs', $html);
        
        // Check regular season stats (with stat-value class)
        $this->assertStringContainsString('class="stat-value">35</td>', $html); // seasonHighPoints
        $this->assertStringContainsString('class="stat-value">42</td>', $html); // careerSeasonHighPoints
        $this->assertStringContainsString('class="stat-value">15</td>', $html); // seasonHighRebounds
        $this->assertStringContainsString('class="stat-value">18</td>', $html); // careerSeasonHighRebounds
        
        // Check playoff stats
        $this->assertStringContainsString('class="stat-value">40</td>', $html); // seasonPlayoffHighPoints
        $this->assertStringContainsString('class="stat-value">45</td>', $html); // careerPlayoffHighPoints
        
        // Check double/triple doubles
        $this->assertStringContainsString('Double-Doubles', $html);
        $this->assertStringContainsString('Triple-Doubles', $html);
    }

    public function testRenderPlayerMenu()
    {
        $html = $this->viewHelper->renderPlayerMenu(123);
        
        // Check menu structure
        $this->assertStringContainsString('PLAYER MENU', $html);
        
        // Check all menu links are present (using actual descriptions from PlayerPageType)
        $this->assertStringContainsString('Player Overview', $html);
        $this->assertStringContainsString('Awards and News', $html);
        $this->assertStringContainsString('One-on-One Results', $html);
        $this->assertStringContainsString('Season Sim Stats', $html);
        $this->assertStringContainsString('Regular Season Totals', $html);
        $this->assertStringContainsString('Regular Season Averages', $html);
        $this->assertStringContainsString('Playoff Totals', $html);
        $this->assertStringContainsString('Playoff Averages', $html);
        $this->assertStringContainsString('H.E.A.T. Totals', $html);
        $this->assertStringContainsString('H.E.A.T. Averages', $html);
        $this->assertStringContainsString('Olympic Totals', $html);
        $this->assertStringContainsString('Olympic Averages', $html);
        $this->assertStringContainsString('Ratings and Salary History', $html);
        
        // Check URLs contain player ID
        $this->assertStringContainsString('pid=123', $html);
    }

    // Helper methods to create mock objects

    private function createMockPlayer(
        string $name,
        ?string $nickname,
        string $position,
        int $teamID,
        string $teamName
    ): Player {
        $player = $this->createMock(Player::class);
        $player->name = $name;
        $player->nickname = $nickname;
        $player->position = $position;
        $player->teamID = $teamID;
        $player->teamName = $teamName;
        return $player;
    }

    private function createMockPlayerWithBio(): Player
    {
        $player = $this->createMock(Player::class);
        
        // Basic player info (needed for renderPlayerHeader)
        $player->position = 'PG';
        $player->name = 'Test Player';
        $player->nickname = null;
        $player->teamID = 1;
        $player->teamName = 'Test Team';
        
        // Bio info
        $player->age = '25';
        $player->heightFeet = '6';
        $player->heightInches = '4';
        $player->weightPounds = '200';
        $player->collegeName = 'Test University';
        $player->draftTeamOriginalName = 'Test Franchise';
        $player->draftPickNumber = '10';
        $player->draftRound = '1';
        $player->draftYear = 2020;
        $player->birdYears = '3';
        
        // Add rating properties
        $player->ratingFieldGoalAttempts = '80';
        $player->ratingFieldGoalPercentage = '75';
        $player->ratingFreeThrowAttempts = '70';
        $player->ratingFreeThrowPercentage = '85';
        $player->ratingThreePointAttempts = '65';
        $player->ratingThreePointPercentage = '60';
        $player->ratingOffensiveRebounds = '50';
        $player->ratingDefensiveRebounds = '70';
        $player->ratingAssists = '80';
        $player->ratingSteals = '75';
        $player->ratingTurnovers = '60';
        $player->ratingBlocks = '40';
        $player->ratingFouls = '55';
        $player->ratingOutsideOffense = '85';
        $player->ratingDriveOffense = '80';
        $player->ratingPostOffense = '60';
        $player->ratingTransitionOffense = '75';
        $player->ratingOutsideDefense = '70';
        $player->ratingDriveDefense = '75';
        $player->ratingPostDefense = '65';
        $player->ratingTransitionDefense = '80';
        
        return $player;
    }

    private function createMockPlayerStats(): PlayerStats
    {
        $stats = $this->createMock(PlayerStats::class);
        
        // Regular season highs
        $stats->seasonHighPoints = 35;
        $stats->careerSeasonHighPoints = 42;
        $stats->seasonHighRebounds = 15;
        $stats->careerSeasonHighRebounds = 18;
        $stats->seasonHighAssists = 12;
        $stats->careerSeasonHighAssists = 14;
        $stats->seasonHighSteals = 5;
        $stats->careerSeasonHighSteals = 6;
        $stats->seasonHighBlocks = 4;
        $stats->careerSeasonHighBlocks = 5;
        $stats->seasonDoubleDoubles = 25;
        $stats->careerDoubleDoubles = 150;
        $stats->seasonTripleDoubles = 2;
        $stats->careerTripleDoubles = 10;
        
        // Playoff highs
        $stats->seasonPlayoffHighPoints = 40;
        $stats->careerPlayoffHighPoints = 45;
        $stats->seasonPlayoffHighRebounds = 16;
        $stats->careerPlayoffHighRebounds = 20;
        $stats->seasonPlayoffHighAssists = 13;
        $stats->careerPlayoffHighAssists = 15;
        $stats->seasonPlayoffHighSteals = 6;
        $stats->careerPlayoffHighSteals = 7;
        $stats->seasonPlayoffHighBlocks = 5;
        $stats->careerPlayoffHighBlocks = 6;
        
        return $stats;
    }

    public function testCompleteHtmlStructureIsBalanced()
    {
        // This test validates that the combined HTML from renderPlayerHeader,
        // renderPlayerBioSection, renderPlayerHighsTable, and renderPlayerMenu produces balanced HTML tags
        $player = $this->createMockPlayerWithBio();
        $playerStats = $this->createMockPlayerStats();
        
        // Simulate the actual rendering sequence from index.php (lines 36-60, 116)
        $html = $this->viewHelper->renderPlayerHeader($player, 123);
        $html .= $this->viewHelper->renderPlayerBioSection($player, '1000/1100/1200');
        $html .= $this->viewHelper->renderPlayerHighsTable($playerStats);
        $html .= '</tr>'; // Close outer row from renderPlayerHeader (index.php line 60)
        $html .= $this->viewHelper->renderPlayerMenu(123);
        $html .= '</table>'; // Final closing tag from index.php line 116
        
        // Count opening and closing tags
        $openTable = substr_count($html, '<table');
        $closeTable = substr_count($html, '</table>');
        $openTr = substr_count($html, '<tr');
        $closeTr = substr_count($html, '</tr>');
        $openTd = substr_count($html, '<td');
        $closeTd = substr_count($html, '</td>');
        
        // Assert all tags are balanced
        $this->assertEquals($openTable, $closeTable, 
            "Table tags are not balanced. Opening: $openTable, Closing: $closeTable");
        $this->assertEquals($openTr, $closeTr, 
            "TR tags are not balanced. Opening: $openTr, Closing: $closeTr");
        $this->assertEquals($openTd, $closeTd, 
            "TD tags are not balanced. Opening: $openTd, Closing: $closeTd");
        
        // Additional validation: the HTML should contain key elements in proper structure
        $this->assertStringContainsString('player-title', $html);
        $this->assertStringContainsString('player-bio', $html);
        $this->assertStringContainsString('player-highs', $html);
        
        // Critical: Verify the outer row is properly closed before the menu starts
        // The structure should have: </td></tr> (from closing outer row) then <tr> (from menu)
        // NOT: </td><tr> (which would indicate missing </tr>)
        $this->assertStringContainsString('</tr><tr>', $html,
            'Missing </tr> before menu <tr> - indicates unclosed outer row from renderPlayerHeader'
        );
    }
}
