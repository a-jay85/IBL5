<?php

declare(strict_types=1);

namespace Tests\TeamSchedule;

use PHPUnit\Framework\TestCase;
use TeamSchedule\TeamScheduleView;

/**
 * TeamScheduleViewTest - Tests for TeamScheduleView HTML rendering
 *
 * Tests the rendering of team schedule tables including:
 * - Style blocks
 * - Team logos
 * - Game rows with win/loss formatting
 * - Month headers
 */
class TeamScheduleViewTest extends TestCase
{
    private TeamScheduleView $view;

    protected function setUp(): void
    {
        $this->view = new TeamScheduleView();
    }

    public function testRenderReturnsHtmlString(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [];
        $simLengthInDays = 7;

        $result = $this->view->render($mockTeam, $games, $simLengthInDays);

        $this->assertIsString($result);
    }

    public function testRenderContainsStyleBlock(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [];

        $result = $this->view->render($mockTeam, $games, 7);

        $this->assertStringContainsString('<style>', $result);
        $this->assertStringContainsString('.schedule-table', $result);
        $this->assertStringContainsString('.game-result-win', $result);
        $this->assertStringContainsString('.game-result-loss', $result);
    }

    public function testRenderContainsTeamLogo(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [];

        $result = $this->view->render($mockTeam, $games, 7);

        $this->assertStringContainsString('images/logo/', $result);
        $this->assertStringContainsString('.jpg', $result);
    }

    public function testRenderContainsScheduleTable(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [];

        $result = $this->view->render($mockTeam, $games, 7);

        $this->assertStringContainsString('<table class="schedule-table"', $result);
        $this->assertStringContainsString('</table>', $result);
    }

    public function testRenderDisplaysSimLengthInDays(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [];
        $simLengthInDays = 14;

        $result = $this->view->render($mockTeam, $games, $simLengthInDays);

        $this->assertStringContainsString('14 days', $result);
    }

    public function testRenderWithGamesDisplaysMonthHeader(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [
            $this->createMockGame('October'),
        ];

        $result = $this->view->render($mockTeam, $games, 7);

        $this->assertStringContainsString('October', $result);
    }

    public function testRenderWithMultipleMonthsShowsMultipleHeaders(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [
            $this->createMockGame('October'),
            $this->createMockGame('November'),
        ];

        $result = $this->view->render($mockTeam, $games, 7);

        $this->assertStringContainsString('October', $result);
        $this->assertStringContainsString('November', $result);
    }

    public function testRenderUsesTeamColors(): void
    {
        $mockTeam = $this->createMockTeam('FF0000', '000000');
        $games = [];

        $result = $this->view->render($mockTeam, $games, 7);

        $this->assertStringContainsString('FF0000', $result);
    }

    public function testRenderEscapesTeamColors(): void
    {
        // Test that team colors are escaped for XSS protection
        $mockTeam = $this->createMockTeam('<script>alert(1)</script>', '000000');
        $games = [];

        $result = $this->view->render($mockTeam, $games, 7);

        // The malicious script should be escaped
        $this->assertStringNotContainsString('<script>alert(1)</script>', $result);
    }

    /**
     * Create a mock Team object for testing
     * 
     * Uses a testable subclass that allows setting public properties
     */
    private function createMockTeam(string $color1 = 'FFFFFF', string $color2 = '000000'): \Team
    {
        $team = $this->getMockBuilder(\Team::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        // Set public properties directly - these exist on Team class
        $team->teamID = 1;
        $team->name = 'Test Team';
        $team->color1 = $color1;
        $team->color2 = $color2;

        return $team;
    }

    /**
     * Create a mock game row for testing
     * 
     * Returns the full data structure expected by TeamScheduleView::renderGameRow
     */
    private function createMockGame(string $month, ?string $boxId = null): array
    {
        // Create mock Game object
        $game = new \stdClass();
        $game->date = '2025-01-15';
        $game->visitorScore = 105;
        $game->homeScore = 98;
        $game->boxScoreID = $boxId ? (int) filter_var($boxId, FILTER_SANITIZE_NUMBER_INT) : 12345;

        // Create mock opposing Team object
        $opposingTeam = new \stdClass();
        $opposingTeam->teamID = 2;
        $opposingTeam->name = 'Boston';

        return [
            'currentMonth' => $month,
            'game' => $game,
            'opposingTeam' => $opposingTeam,
            'highlight' => '',
            'opponentText' => 'at Boston',
            'isUnplayed' => false,
            'winLossColor' => 'green',
            'gameResult' => 'W',
            'wins' => 10,
            'losses' => 5,
            'streak' => 'W3',
        ];
    }
}
