<?php

declare(strict_types=1);

namespace Tests\LeagueSchedule;

use LeagueSchedule\LeagueScheduleView;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type MonthData from \LeagueSchedule\Contracts\LeagueScheduleServiceInterface
 * @phpstan-import-type SchedulePageData from \LeagueSchedule\Contracts\LeagueScheduleServiceInterface
 */
class LeagueScheduleViewTest extends TestCase
{
    private LeagueScheduleView $view;

    protected function setUp(): void
    {
        $this->view = new LeagueScheduleView();
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
                            'home_record' => '20-15',
                            'boxid' => 101,
                            'gameOfThatDay' => 1,
                            'boxScoreUrl' => 'boxscore.php?id=101',
                            'isUnplayed' => false,
                            'isUpcoming' => false,
                            'visitorWon' => true,
                            'homeWon' => false,
                            'visitorTier' => '',
                            'homeTier' => '',
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
                            'home_record' => '',
                            'boxid' => 101,
                            'gameOfThatDay' => 0,
                            'boxScoreUrl' => '',
                            'isUnplayed' => true,
                            'isUpcoming' => true,
                            'visitorWon' => false,
                            'homeWon' => false,
                            'visitorTier' => '',
                            'homeTier' => '',
                        ],
                    ],
                ],
            ],
        ]);

        $html = $this->view->render($pageData);

        $this->assertStringContainsString('schedule-game--upcoming', $html);
    }

    public function testRenderIncludesSharedScrollScriptTag(): void
    {
        $html = $this->view->render($this->createPageData());

        $this->assertSame(1, substr_count($html, '<script src="jslib/schedule-scroll.js" defer></script>'));
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRenderOmitsInlineScrollHandlers(): void
    {
        $html = $this->view->render($this->createPageData(
            gamesByMonth: ['2026-02' => ['label' => 'February', 'dates' => []]],
            firstUnplayedId: 'game-77',
        ));

        foreach (['onclick=', 'scrollToMonth', 'scrollToNextGames', 'headerOffset'] as $legacy) {
            $this->assertStringNotContainsString($legacy, $html);
        }
    }

    public function testMonthLinkCarriesScrollTargetAttribute(): void
    {
        $html = $this->view->render($this->createPageData(
            gamesByMonth: ['2026-02' => ['label' => 'February', 'dates' => []]],
        ));

        $this->assertStringContainsString(
            '<a href="#month-2026-02" class="ibl-jump-menu__link schedule-months__link" data-scroll-target="month-2026-02">',
            $html,
        );
        $this->assertStringNotContainsString('data-scroll-next', $html);
    }

    public function testNextGamesLinkCarriesTargetAndNextFlag(): void
    {
        $html = $this->view->render($this->createPageData(firstUnplayedId: 'game-77'));

        $this->assertStringContainsString(
            '<a href="#game-77" class="schedule-jump-btn" data-scroll-target="game-77" data-scroll-next>',
            $html,
        );
    }

    public function testNextGamesLinkAbsentWithoutUpcomingGame(): void
    {
        $html = $this->view->render($this->createPageData(firstUnplayedId: null));

        $this->assertStringNotContainsString('schedule-jump-btn', $html);
        $this->assertStringNotContainsString('data-scroll-next', $html);
        // The script tag is unconditional, so a page with no upcoming game still loads it.
        $this->assertStringContainsString('<script src="jslib/schedule-scroll.js" defer></script>', $html);
    }

    public function testRenderEscapesHostileScrollIds(): void
    {
        $html = $this->view->render($this->createPageData(
            gamesByMonth: ['2026-02"><b>' => ['label' => 'February', 'dates' => []]],
            firstUnplayedId: 'game-1"><b>',
        ));

        $this->assertStringNotContainsString('"><b>', $html);
        $this->assertStringContainsString('href="#game-1&quot;&gt;&lt;b&gt;"', $html);
        $this->assertStringContainsString('data-scroll-target="game-1&quot;&gt;&lt;b&gt;"', $html);
        $this->assertStringContainsString('href="#month-2026-02&quot;&gt;&lt;b&gt;"', $html);
        $this->assertStringContainsString('data-scroll-target="month-2026-02&quot;&gt;&lt;b&gt;"', $html);
        $this->assertStringContainsString('id="month-2026-02&quot;&gt;&lt;b&gt;"', $html);
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

    public function testMonthHeaderCarriesIblCardHeaderClass(): void
    {
        $regular = $this->view->render($this->createPageData(gamesByMonth: [
            '2025-11' => ['label' => 'November', 'dates' => []],
        ]));
        $this->assertStringContainsString('class="ibl-card__header schedule-month__header"', $regular);

        $playoffs = $this->view->render($this->createPageData(
            gamesByMonth: [
                '2026-06' => ['label' => 'Playoffs', 'dates' => []],
            ],
            isPlayoffPhase: true,
            playoffMonthKey: '2026-06',
        ));
        $this->assertStringContainsString(
            'class="ibl-card__header schedule-month__header schedule-month__header--playoffs"',
            $playoffs
        );
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
                            'home_record' => '',
                            'boxid' => 101,
                            'gameOfThatDay' => 0,
                            'boxScoreUrl' => 'boxscore.php?id=101',
                            'isUnplayed' => true,
                            'isUpcoming' => false,
                            'visitorWon' => false,
                            'homeWon' => false,
                            'visitorTier' => '',
                            'homeTier' => '',
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
     * @param array<string, MonthData> $gamesByMonth
     * @return SchedulePageData
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
