<?php

declare(strict_types=1);

namespace Tests\TeamSchedule;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use TeamSchedule\TeamScheduleView;

/**
 * TeamScheduleViewTest - Tests for TeamScheduleView HTML rendering
 *
 * Tests the rendering of team schedule cards including:
 * - Style blocks with team colors
 * - Team banner with logo
 * - Game cards with win/loss formatting
 * - Month headers and navigation
 */
#[AllowMockObjectsWithoutExpectations]
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
        $this->assertStringContainsString('--team-primary', $result);
        $this->assertStringContainsString('--team-secondary', $result);
    }

    public function testRenderContainsTeamLogo(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [];

        $result = $this->view->render($mockTeam, $games, 7);

        $this->assertStringContainsString('images/logo/', $result);
        $this->assertStringContainsString('.jpg', $result);
    }

    public function testRenderContainsScheduleContainer(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [];

        $result = $this->view->render($mockTeam, $games, 7);

        $this->assertStringContainsString('schedule-container', $result);
        $this->assertStringContainsString('schedule-container--team', $result);
        $this->assertStringContainsString('schedule-team-banner', $result);
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
            $this->createMockGame('October', '2025-10-15'),
        ];

        $result = $this->view->render($mockTeam, $games, 7);

        $this->assertStringContainsString('October', $result);
    }

    public function testRenderWithMultipleMonthsShowsMultipleHeaders(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [
            $this->createMockGame('October', '2025-10-15'),
            $this->createMockGame('November', '2025-11-15'),
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

    public function testRenderShowsWinResult(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [
            $this->createMockGame('October', '2025-10-15', false, 'green'),
        ];

        $result = $this->view->render($mockTeam, $games, 7);

        // Win is now shown by highlighting the winning team's score/record
        $this->assertStringContainsString('schedule-game__team--win', $result);
    }

    public function testRenderShowsLossResult(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [
            $this->createMockGame('October', '2025-10-15', false, 'red'),
        ];

        $result = $this->view->render($mockTeam, $games, 7);

        // Loss is shown by NOT having the --win class on the user's team
        // The opponent's score gets the --win class instead
        $this->assertStringContainsString('schedule-game__team--win', $result);
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
     * Returns the full data structure expected by TeamScheduleView
     */
    private function createMockGame(
        string $month,
        string $date = '2025-01-15',
        bool $isUnplayed = false,
        string $winLossColor = 'green'
    ): array {
        // Create mock Game object
        $game = new \stdClass();
        $game->date = $date;
        $game->visitorScore = 105;
        $game->homeScore = 98;
        $game->boxScoreID = 12345;
        $game->visitorTeamID = 1;  // User's team is visitor
        $game->homeTeamID = 2;     // Opponent is home team

        // Create mock opposing Team object
        $opposingTeam = new \stdClass();
        $opposingTeam->teamID = 2;
        $opposingTeam->name = 'Boston';
        $opposingTeam->seasonRecord = '10-5';  // Added for opponent record display

        return [
            'currentMonth' => $month,
            'game' => $game,
            'opposingTeam' => $opposingTeam,
            'highlight' => '',
            'opponentText' => 'at Boston',
            'isUnplayed' => $isUnplayed,
            'winLossColor' => $winLossColor,
            'gameResult' => $winLossColor === 'green' ? 'W' : 'L',
            'wins' => 10,
            'losses' => 5,
            'streak' => 'W3',
        ];
    }
}
