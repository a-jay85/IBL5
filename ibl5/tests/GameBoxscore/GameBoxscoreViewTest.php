<?php

declare(strict_types=1);

namespace Tests\GameBoxscore;

use GameBoxscore\Contracts\GameBoxscoreServiceInterface;
use GameBoxscore\GameBoxscoreView;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type GameBoxscoreViewModel from GameBoxscoreServiceInterface
 * @phpstan-import-type GameBoxscorePlayerRow from GameBoxscoreServiceInterface
 * @phpstan-import-type GameBoxscoreTeamHeader from GameBoxscoreServiceInterface
 * @phpstan-import-type GameBoxscoreTotals from GameBoxscoreServiceInterface
 *
 * @covers \GameBoxscore\GameBoxscoreView
 */
class GameBoxscoreViewTest extends TestCase
{
    private GameBoxscoreView $view;

    protected function setUp(): void
    {
        $this->view = new GameBoxscoreView();
    }

    public function testRendersScoreHeaderWithScoresDateAndGame(): void
    {
        $output = $this->view->render($this->buildViewModel());

        self::assertStringContainsString('105', $output);
        self::assertStringContainsString('98', $output);
        self::assertStringContainsString('2026-02-20', $output);
        self::assertStringContainsString('Game 1', $output);
        self::assertStringContainsString('images/logo/new1.png', $output);
        self::assertStringContainsString('images/logo/new2.png', $output);
    }

    public function testRendersBothTeamPanelsWithSortableTables(): void
    {
        $output = $this->view->render($this->buildViewModel());

        self::assertStringContainsString('data-team-panel="away"', $output);
        self::assertStringContainsString('data-team-panel="home"', $output);
        self::assertSame(2, substr_count($output, 'class="ibl-data-table sortable game-boxscore__table"'));

        // Home is the default-selected side.
        self::assertStringContainsString('id="boxscore-team-home" checked', $output);
        self::assertStringNotContainsString('id="boxscore-team-away" checked', $output);
    }

    public function testRendersAllSeventeenColumnsInOrder(): void
    {
        $output = $this->view->render($this->buildViewModel());

        $expectedOrder = [
            '<th>Pos</th>', '<th>Name</th>', '<th>Min</th>', '<th>FGM</th>', '<th>FGA</th>',
            '<th>FTM</th>', '<th>FTA</th>', '<th>3PM</th>', '<th>3PA</th>',
            '<th data-col="pts">PTS</th>', '<th>ORB</th>', '<th>REB</th>', '<th>AST</th>',
            '<th>STL</th>', '<th>BLK</th>', '<th>TOV</th>', '<th>PF</th>',
        ];

        self::assertCount(17, $expectedOrder);
        self::assertStringContainsString(implode('', $expectedOrder), $output);
        self::assertStringContainsString('<td class="game-boxscore__cell--pts">30</td>', $output);
    }

    public function testRendersTotalsRowInTfoot(): void
    {
        $output = $this->view->render($this->buildViewModel());

        self::assertStringContainsString('<tfoot><tr class="game-boxscore__totals">', $output);
        // Away totals: 30 + 20 = 50 points.
        self::assertStringContainsString(
            '<tr class="game-boxscore__totals"><td>Totals</td><td></td>'
                . '<td>60</td><td>18</td><td>34</td><td>8</td><td>10</td><td>4</td><td>11</td>'
                . '<td class="game-boxscore__cell--pts">50</td>',
            $output,
        );
    }

    public function testRendersEmptyStateWhenNoPlayers(): void
    {
        $viewModel = $this->buildViewModel();
        $viewModel['awayPlayers'] = [];

        $output = $this->view->render($viewModel);

        self::assertStringContainsString('colspan="17"', $output);
        self::assertStringContainsString('No player stats recorded for this game.', $output);
        // The home side still renders its players.
        self::assertStringContainsString('Home Guard', $output);
    }

    public function testRendersNotFoundPanelWhenNotFound(): void
    {
        $viewModel = $this->buildViewModel();
        $viewModel['found'] = false;

        $output = $this->view->render($viewModel);

        self::assertStringContainsString('game-boxscore-not-found', $output);
        self::assertStringContainsString('Game Not Found', $output);
        self::assertStringNotContainsString('<table', $output);
        self::assertStringNotContainsString('2026-02-20', $output);
    }

    /**
     * @return GameBoxscoreViewModel
     */
    private function buildViewModel(): array
    {
        return [
            'found' => true,
            'date' => '2026-02-20',
            'gameOfThatDay' => 1,
            'awayTeam' => $this->buildTeam(1, 'Away Team', 'Away City', 'FF0000', '0000FF', 105),
            'homeTeam' => $this->buildTeam(2, 'Home Team', 'Home City', '00FF00', 'FFFF00', 98),
            'awayPlayers' => [
                $this->buildPlayer(10, 'PG', 'Away Guard', 30),
                $this->buildPlayer(11, 'C', 'Away Center', 20),
            ],
            'homePlayers' => [
                $this->buildPlayer(20, 'SG', 'Home Guard', 25),
            ],
            'awayTotals' => $this->buildTotals(50),
            'homeTotals' => $this->buildTotals(25),
        ];
    }

    /**
     * @return GameBoxscoreTeamHeader
     */
    private function buildTeam(int $teamId, string $name, string $city, string $color1, string $color2, int $score): array
    {
        return [
            'teamId' => $teamId,
            'name' => $name,
            'city' => $city,
            'color1' => $color1,
            'color2' => $color2,
            'score' => $score,
        ];
    }

    /**
     * @return GameBoxscorePlayerRow
     */
    private function buildPlayer(int $pid, string $pos, string $name, int $pts): array
    {
        return [
            'pid' => $pid,
            'pos' => $pos,
            'name' => $name,
            'min' => 30,
            'fgm' => 9,
            'fga' => 17,
            'ftm' => 4,
            'fta' => 5,
            'tpm' => 2,
            'tpa' => 6,
            'pts' => $pts,
            'orb' => 2,
            'reb' => 8,
            'ast' => 5,
            'stl' => 1,
            'blk' => 1,
            'tov' => 3,
            'pf' => 2,
        ];
    }

    /**
     * @return GameBoxscoreTotals
     */
    private function buildTotals(int $pts): array
    {
        return [
            'min' => 60,
            'fgm' => 18,
            'fga' => 34,
            'ftm' => 8,
            'fta' => 10,
            'tpm' => 4,
            'tpa' => 11,
            'pts' => $pts,
            'orb' => 4,
            'reb' => 16,
            'ast' => 10,
            'stl' => 2,
            'blk' => 2,
            'tov' => 6,
            'pf' => 4,
        ];
    }
}
