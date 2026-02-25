<?php

declare(strict_types=1);

namespace Tests\NextSim;

use PHPUnit\Framework\TestCase;
use NextSim\NextSimView;
use NextSim\Contracts\NextSimViewInterface;
use Player\Player;

/**
 * NextSimViewTest - Tests for NextSimView HTML rendering
 *
 * @covers \NextSim\NextSimView
 */
class NextSimViewTest extends TestCase
{
    private NextSimView $view;

    /** @var \Team&\PHPUnit\Framework\MockObject\Stub */
    private \Team $userTeam;

    /** @var array<string, Player&\PHPUnit\Framework\MockObject\Stub> */
    private array $userStarters;

    protected function setUp(): void
    {
        $mockSeason = $this->createStub(\Season::class);
        $mockSeason->lastSimEndDate = '2025-01-01';

        $this->view = new NextSimView($mockSeason);

        $this->userTeam = $this->createStub(\Team::class);
        $this->userTeam->teamID = 1;
        $this->userTeam->city = 'Test';
        $this->userTeam->name = 'Team';
        $this->userTeam->color1 = 'FF0000';
        $this->userTeam->color2 = '0000FF';
        $this->userTeam->seasonRecord = '10-5';

        $this->userStarters = [];
        foreach (\JSB::PLAYER_POSITIONS as $position) {
            $player = $this->createStub(Player::class);
            $player->playerID = 100;
            $player->name = 'User ' . $position;
            $player->decoratedName = 'User ' . $position;
            $player->position = $position;
            $player->age = 25;
            $player->daysRemainingForInjury = 0;
            $player->method('getInjuryReturnDate')->willReturn('');
            $this->userStarters[$position] = $player;
        }
    }

    public function testImplementsNextSimViewInterface(): void
    {
        $this->assertInstanceOf(NextSimViewInterface::class, $this->view);
    }

    public function testRenderReturnsString(): void
    {
        $result = $this->view->render([], $this->userTeam, $this->userStarters);

        $this->assertIsString($result);
    }

    public function testRenderContainsTitle(): void
    {
        $result = $this->view->render([], $this->userTeam, $this->userStarters);

        $this->assertStringContainsString('Next Sim', $result);
    }

    public function testRenderShowsNoGamesMessage(): void
    {
        $result = $this->view->render([], $this->userTeam, $this->userStarters);

        $this->assertStringContainsString('No games projected next sim', $result);
    }

    public function testRenderWithGamesContainsScheduleStrip(): void
    {
        $games = $this->createGameData();
        $result = $this->view->render($games, $this->userTeam, $this->userStarters);

        $this->assertStringContainsString('next-sim-schedule-strip', $result);
    }

    public function testRenderWithGamesContainsAllPositionSections(): void
    {
        $games = $this->createGameData();
        $result = $this->view->render($games, $this->userTeam, $this->userStarters);

        $this->assertStringContainsString('Point Guards', $result);
        $this->assertStringContainsString('Shooting Guards', $result);
        $this->assertStringContainsString('Small Forwards', $result);
        $this->assertStringContainsString('Power Forwards', $result);
        $this->assertStringContainsString('Centers', $result);
    }

    public function testRenderContainsUserRow(): void
    {
        $games = $this->createGameData();
        $result = $this->view->render($games, $this->userTeam, $this->userStarters);

        $this->assertStringContainsString('next-sim-row--user', $result);
    }

    public function testRenderContainsOpponentRow(): void
    {
        $games = $this->createGameData();
        $result = $this->view->render($games, $this->userTeam, $this->userStarters);

        $this->assertStringContainsString('next-sim-row--opponent', $result);
    }

    public function testRenderContainsPerRowTeamColorInlineStyles(): void
    {
        $games = $this->createGameData();
        $result = $this->view->render($games, $this->userTeam, $this->userStarters);

        $this->assertStringContainsString('--team-color-primary', $result);
        $this->assertStringContainsString('--team-color-secondary', $result);
    }

    public function testRenderContainsUserRowWithTeamLogo(): void
    {
        $games = $this->createGameData();
        $result = $this->view->render($games, $this->userTeam, $this->userStarters);

        $this->assertStringContainsString('next-sim-row--user', $result);
        $this->assertStringContainsString('next-sim-game-info-cell', $result);
    }

    public function testScheduleStripContainsGameDayInfo(): void
    {
        $games = $this->createGameData();
        $result = $this->view->render($games, $this->userTeam, $this->userStarters);

        $this->assertStringContainsString('Day 1', $result);
    }

    public function testPositionSectionContainsGameColumn(): void
    {
        $games = $this->createGameData();
        $result = $this->view->render($games, $this->userTeam, $this->userStarters);

        $this->assertStringContainsString('<th>Game</th>', $result);
    }

    public function testRenderPositionTableReturnsTableWithHeaders(): void
    {
        $games = $this->createGameData();
        $result = $this->view->renderPositionTable($games, 'PG', $this->userTeam, $this->userStarters);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('<th>Game</th>', $result);
        $this->assertStringContainsString('<th>Age</th>', $result);
        $this->assertStringContainsString('<th>2ga</th>', $result);
    }

    public function testRenderScheduleStripContainsStripClass(): void
    {
        $games = $this->createGameData();
        $result = $this->view->renderScheduleStrip($games);

        $this->assertStringContainsString('next-sim-schedule-strip', $result);
    }

    public function testRenderTabbedPositionTableContainsTabs(): void
    {
        $games = $this->createGameData();
        $result = $this->view->renderTabbedPositionTable($games, 'PG', $this->userTeam, $this->userStarters);

        $this->assertStringContainsString('ibl-tabs', $result);
        foreach (NextSimView::POSITION_LABELS as $key => $label) {
            $this->assertStringContainsString('data-display="' . $key . '"', $result);
            $this->assertStringContainsString($label, $result);
        }
    }

    public function testRenderTabbedPositionTableMarksActiveTab(): void
    {
        $games = $this->createGameData();
        $result = $this->view->renderTabbedPositionTable($games, 'SF', $this->userTeam, $this->userStarters);

        // The active tab should have ibl-tab--active class
        $this->assertMatchesRegularExpression('/class="ibl-tab ibl-tab--active"[^>]*data-display="SF"/', $result);
    }

    public function testRenderTabbedPositionTableWrapsInPositionSection(): void
    {
        $games = $this->createGameData();
        $result = $this->view->renderTabbedPositionTable($games, 'PG', $this->userTeam, $this->userStarters);

        $this->assertStringContainsString('next-sim-position-section', $result);
    }

    public function testRenderColumnHighlightScriptDefinesGlobalFunction(): void
    {
        $result = $this->view->renderColumnHighlightScript();

        $this->assertStringContainsString('IBL_initNextSimHighlight', $result);
    }

    /**
     * @return array<int, array{game: \Game, date: \DateTime, dayNumber: int, opposingTeam: \Team, locationPrefix: string, opposingStarters: array<string, Player>, opponentTier: string, opponentPowerRanking: float}>
     */
    private function createGameData(): array
    {
        $oppTeam = $this->createStub(\Team::class);
        $oppTeam->teamID = 2;
        $oppTeam->city = 'Rival';
        $oppTeam->name = 'Foes';
        $oppTeam->color1 = '00FF00';
        $oppTeam->color2 = 'FFFF00';
        $oppTeam->seasonRecord = '8-7';

        $game = $this->createStub(\Game::class);
        $game->date = '2025-01-02';

        $oppStarters = [];
        foreach (\JSB::PLAYER_POSITIONS as $position) {
            $player = $this->createStub(Player::class);
            $player->playerID = 200;
            $player->name = 'Opp ' . $position;
            $player->decoratedName = 'Opp ' . $position;
            $player->position = $position;
            $player->age = 27;
            $player->daysRemainingForInjury = 0;
            $player->method('getInjuryReturnDate')->willReturn('');
            $oppStarters[$position] = $player;
        }

        return [
            [
                'game' => $game,
                'date' => new \DateTime('2025-01-02'),
                'dayNumber' => 1,
                'opposingTeam' => $oppTeam,
                'locationPrefix' => '@',
                'opposingStarters' => $oppStarters,
                'opponentTier' => 'strong',
                'opponentPowerRanking' => 75.0,
            ],
        ];
    }
}
