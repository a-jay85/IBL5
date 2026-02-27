<?php

declare(strict_types=1);

namespace Tests\Team\Views;

use PHPUnit\Framework\TestCase;
use Team\Views\FranchiseHistoryView;

class FranchiseHistoryViewTest extends TestCase
{
    private FranchiseHistoryView $view;

    protected function setUp(): void
    {
        $this->view = new FranchiseHistoryView();
    }

    /**
     * @return array{records: list<array{year: int, label: string, wins: int, losses: int, urlYear: int, isBest: bool}>, totalWins: int, totalLosses: int, teamID: int}
     */
    private function buildWinLossData(): array
    {
        return [
            'records' => [
                ['year' => 2024, 'label' => '2023-2024 Heat', 'wins' => 50, 'losses' => 10, 'urlYear' => 2024, 'isBest' => true],
                ['year' => 2023, 'label' => '2022-2023 Heat', 'wins' => 40, 'losses' => 20, 'urlYear' => 2023, 'isBest' => false],
            ],
            'totalWins' => 90,
            'totalLosses' => 30,
            'teamID' => 5,
        ];
    }

    public function testRegularSeasonBoldsBestRecord(): void
    {
        $html = $this->view->renderRegularSeason($this->buildWinLossData());

        $this->assertStringContainsString('<strong><a href=', $html);
        // The best record (50-10) should be bolded
        $this->assertStringContainsString('<strong><a href="./modules.php?name=Team&amp;op=team&amp;teamID=5&amp;yr=2024">2023-2024 Heat</a>', $html);
    }

    public function testRegularSeasonShowsTotals(): void
    {
        $html = $this->view->renderRegularSeason($this->buildWinLossData());

        $this->assertStringContainsString('Totals: 90-30', $html);
        $this->assertStringContainsString('team-card__footer', $html);
    }

    public function testHeatShowsTotals(): void
    {
        $data = [
            'records' => [
                ['year' => 2024, 'label' => '2024 Heat', 'wins' => 3, 'losses' => 1, 'urlYear' => 2025, 'isBest' => true],
            ],
            'totalWins' => 3,
            'totalLosses' => 1,
            'teamID' => 5,
        ];

        $html = $this->view->renderHeat($data);

        $this->assertStringContainsString('Totals: 3-1', $html);
    }

    public function testPlayoffsRendersRoundSections(): void
    {
        $data = [
            'rounds' => [
                [
                    'name' => 'First Round',
                    'gameWins' => 8,
                    'gameLosses' => 4,
                    'seriesWins' => 2,
                    'seriesLosses' => 0,
                    'results' => [
                        ['year' => '2024', 'winner' => 'Heat', 'loser' => 'Celtics', 'winnerGames' => 4, 'loserGames' => 2, 'isWin' => true],
                        ['year' => '2023', 'winner' => 'Heat', 'loser' => 'Hawks', 'winnerGames' => 4, 'loserGames' => 2, 'isWin' => true],
                    ],
                ],
                [
                    'name' => 'Conference Semis',
                    'gameWins' => 0,
                    'gameLosses' => 0,
                    'seriesWins' => 0,
                    'seriesLosses' => 0,
                    'results' => [],
                ],
            ],
            'totalGameWins' => 8,
            'totalGameLosses' => 4,
            'totalSeriesWins' => 2,
            'totalSeriesLosses' => 0,
        ];

        $html = $this->view->renderPlayoffs($data);

        $this->assertStringContainsString('First Round', $html);
        $this->assertStringNotContainsString('Conference Semis', $html); // Empty round skipped
    }

    public function testPlayoffsHighlightsWins(): void
    {
        $data = [
            'rounds' => [
                [
                    'name' => 'First Round',
                    'gameWins' => 8,
                    'gameLosses' => 6,
                    'seriesWins' => 1,
                    'seriesLosses' => 1,
                    'results' => [
                        ['year' => '2024', 'winner' => 'Heat', 'loser' => 'Celtics', 'winnerGames' => 4, 'loserGames' => 2, 'isWin' => true],
                        ['year' => '2023', 'winner' => 'Celtics', 'loser' => 'Heat', 'winnerGames' => 4, 'loserGames' => 2, 'isWin' => false],
                    ],
                ],
            ],
            'totalGameWins' => 8,
            'totalGameLosses' => 6,
            'totalSeriesWins' => 1,
            'totalSeriesLosses' => 1,
        ];

        $html = $this->view->renderPlayoffs($data);

        $this->assertStringContainsString('playoff-result playoff-result--win', $html);
        // The loss should just have 'playoff-result' without '--win'
        $this->assertSame(1, substr_count($html, 'playoff-result--win'));
    }

    public function testPlayoffsShowsTotalsFooter(): void
    {
        $data = [
            'rounds' => [
                [
                    'name' => 'First Round',
                    'gameWins' => 4,
                    'gameLosses' => 2,
                    'seriesWins' => 1,
                    'seriesLosses' => 0,
                    'results' => [
                        ['year' => '2024', 'winner' => 'Heat', 'loser' => 'Celtics', 'winnerGames' => 4, 'loserGames' => 2, 'isWin' => true],
                    ],
                ],
            ],
            'totalGameWins' => 4,
            'totalGameLosses' => 2,
            'totalSeriesWins' => 1,
            'totalSeriesLosses' => 0,
        ];

        $html = $this->view->renderPlayoffs($data);

        $this->assertStringContainsString('Post-Season: 4-2', $html);
        $this->assertStringContainsString('Series: 1-0', $html);
    }
}
