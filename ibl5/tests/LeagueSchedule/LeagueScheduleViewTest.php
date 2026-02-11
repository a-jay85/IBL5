<?php

declare(strict_types=1);

namespace Tests\LeagueSchedule;

use LeagueSchedule\LeagueScheduleView;
use LeagueSchedule\Contracts\LeagueScheduleViewInterface;
use PHPUnit\Framework\TestCase;

class LeagueScheduleViewTest extends TestCase
{
    private LeagueScheduleView $view;

    protected function setUp(): void
    {
        $this->view = new LeagueScheduleView();
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(LeagueScheduleViewInterface::class, $this->view);
    }

    public function testRenderReturnsHtmlWithScheduleContainer(): void
    {
        $pageData = $this->createPageData();

        $html = $this->view->render($pageData);

        $this->assertStringContainsString('<div class="schedule-container">', $html);
        $this->assertStringContainsString('</div>', $html);
    }

    public function testRenderShowsSimLengthDays(): void
    {
        $pageData = $this->createPageData(simLengthDays: 7);

        $html = $this->view->render($pageData);

        $this->assertStringContainsString('Next sim length: 7 days', $html);
    }

    public function testRenderShowsJumpButtonWhenUnplayedGamesExist(): void
    {
        $pageData = $this->createPageData(firstUnplayedId: 'game-101');

        $html = $this->view->render($pageData);

        $this->assertStringContainsString('Next Games', $html);
        $this->assertStringContainsString('schedule-jump-btn', $html);
        $this->assertStringContainsString('#game-101', $html);
    }

    public function testRenderHidesJumpButtonWhenNoUnplayedGames(): void
    {
        $pageData = $this->createPageData(firstUnplayedId: null);

        $html = $this->view->render($pageData);

        $this->assertStringNotContainsString('schedule-jump-btn', $html);
    }

    public function testRenderShowsMonthNavigation(): void
    {
        $pageData = $this->createPageData(gamesByMonth: [
            '2025-11' => [
                'label' => 'November',
                'dates' => [],
            ],
            '2025-12' => [
                'label' => 'December',
                'dates' => [],
            ],
        ]);

        $html = $this->view->render($pageData);

        $this->assertStringContainsString('November', $html);
        $this->assertStringContainsString('December', $html);
        $this->assertStringContainsString('schedule-months', $html);
    }

    public function testRenderShowsGameData(): void
    {
        $pageData = $this->createPageData(gamesByMonth: [
            '2025-11' => [
                'label' => 'November',
                'dates' => [
                    '2025-11-01' => [
                        [
                            'date' => '2025-11-01',
                            'visitor' => 1,
                            'visitorScore' => 100,
                            'visitorTeam' => 'Team A',
                            'visitorRecord' => '25-10',
                            'home' => 2,
                            'homeScore' => 95,
                            'homeTeam' => 'Team B',
                            'homeRecord' => '20-15',
                            'boxid' => 101,
                            'gameOfThatDay' => 1,
                            'boxScoreUrl' => 'boxscore.php?id=101',
                            'isUnplayed' => false,
                            'isUpcoming' => false,
                            'visitorWon' => true,
                            'homeWon' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $html = $this->view->render($pageData);

        $this->assertStringContainsString('Team A', $html);
        $this->assertStringContainsString('Team B', $html);
        $this->assertStringContainsString('25-10', $html);
        $this->assertStringContainsString('20-15', $html);
        $this->assertStringContainsString('100', $html);
        $this->assertStringContainsString('95', $html);
    }

    public function testRenderMarksUpcomingGames(): void
    {
        $pageData = $this->createPageData(gamesByMonth: [
            '2025-11' => [
                'label' => 'November',
                'dates' => [
                    '2025-11-10' => [
                        [
                            'date' => '2025-11-10',
                            'visitor' => 1,
                            'visitorScore' => 0,
                            'visitorTeam' => 'Team A',
                            'visitorRecord' => '',
                            'home' => 2,
                            'homeScore' => 0,
                            'homeTeam' => 'Team B',
                            'homeRecord' => '',
                            'boxid' => 101,
                            'gameOfThatDay' => 0,
                            'boxScoreUrl' => '',
                            'isUnplayed' => true,
                            'isUpcoming' => true,
                            'visitorWon' => false,
                            'homeWon' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $html = $this->view->render($pageData);

        $this->assertStringContainsString('schedule-game--upcoming', $html);
    }

    public function testRenderIncludesScrollScripts(): void
    {
        $pageData = $this->createPageData();

        $html = $this->view->render($pageData);

        $this->assertStringContainsString('<script>', $html);
        $this->assertStringContainsString('scrollToMonth', $html);
        $this->assertStringContainsString('scrollToNextGames', $html);
    }

    public function testRenderShowsPlayoffHeader(): void
    {
        $pageData = $this->createPageData(
            gamesByMonth: [
                '2026-06' => [
                    'label' => 'Playoffs',
                    'dates' => [],
                ],
            ],
            isPlayoffPhase: true,
            playoffMonthKey: '2026-06',
        );

        $html = $this->view->render($pageData);

        $this->assertStringContainsString('schedule-month__header--playoffs', $html);
        $this->assertStringContainsString('Playoffs', $html);
    }

    public function testRenderShowsDashForUnplayedScores(): void
    {
        $pageData = $this->createPageData(gamesByMonth: [
            '2025-11' => [
                'label' => 'November',
                'dates' => [
                    '2025-11-10' => [
                        [
                            'date' => '2025-11-10',
                            'visitor' => 1,
                            'visitorScore' => 0,
                            'visitorTeam' => 'Team A',
                            'visitorRecord' => '',
                            'home' => 2,
                            'homeScore' => 0,
                            'homeTeam' => 'Team B',
                            'homeRecord' => '',
                            'boxid' => 101,
                            'gameOfThatDay' => 0,
                            'boxScoreUrl' => 'boxscore.php?id=101',
                            'isUnplayed' => true,
                            'isUpcoming' => false,
                            'visitorWon' => false,
                            'homeWon' => false,
                        ],
                    ],
                ],
            ],
        ]);

        $html = $this->view->render($pageData);

        // The en-dash character for unplayed games
        $this->assertStringContainsString('–', $html);
    }

    /**
     * @param array<string, array{label: string, dates: array<string, list<array<string, mixed>>>}> $gamesByMonth
     */
    private function createPageData(
        array $gamesByMonth = [],
        ?string $firstUnplayedId = null,
        bool $isPlayoffPhase = false,
        ?string $playoffMonthKey = null,
        int $simLengthDays = 7,
    ): array {
        return [
            'gamesByMonth' => $gamesByMonth,
            'firstUnplayedId' => $firstUnplayedId,
            'isPlayoffPhase' => $isPlayoffPhase,
            'playoffMonthKey' => $playoffMonthKey,
            'simLengthDays' => $simLengthDays,
        ];
    }
}
