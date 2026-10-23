<?php

declare(strict_types=1);

namespace Tests\TeamSchedule;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TeamSchedule\TeamScheduleView;
use Team\Team;

/**
 * TeamScheduleViewTest - Tests for TeamScheduleView HTML rendering
 *
 * Tests the rendering of team schedule cards including:
 * - Style blocks with team colors
 * - Team banner with logo
 * - Game cards with win/loss formatting
 * - Month headers and navigation
 *
 * @phpstan-import-type ScheduleGameRow from \TeamSchedule\Contracts\TeamScheduleServiceInterface
 * @phpstan-type GoldenSpec array{visitorId: int, homeId: int, gameOfThatDay: int, boxScoreID: int, isUnplayed: bool, winLossColor: string, streak: string, highlight: string, opponentTier: string, opponentName: string, opponentRecord: ?string, userTeamName: string}
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

        $result = $this->view->render($mockTeam, $games, $simLengthInDays, 'Regular Season');

        $this->assertIsString($result);
    }

    public function testRenderContainsTeamColorCustomProperties(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [];

        $result = $this->view->render($mockTeam, $games, 7, 'Regular Season');

        $this->assertStringNotContainsString('<style>', $result);
        $this->assertStringContainsString('--team-color-primary:', $result);
        $this->assertStringContainsString('--team-color-secondary:', $result);
        $this->assertStringContainsString('--team-color-on-primary: #', $result);
    }

    public function testRenderContainsTeamLogo(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [];

        $result = $this->view->render($mockTeam, $games, 7, 'Regular Season');

        $this->assertStringContainsString('images/logo/', $result);
        $this->assertStringContainsString('.jpg', $result);
    }

    public function testRenderContainsScheduleContainer(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [];

        $result = $this->view->render($mockTeam, $games, 7, 'Regular Season');

        $this->assertStringContainsString('schedule-container', $result);
        $this->assertStringContainsString('schedule-container--team', $result);
        $this->assertStringContainsString('schedule-team-banner', $result);
    }

    public function testRenderDisplaysSimLengthInDays(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [];
        $simLengthInDays = 14;

        $result = $this->view->render($mockTeam, $games, $simLengthInDays, 'Regular Season');

        $this->assertStringContainsString('14 days', $result);
    }

    public function testRenderWithGamesDisplaysMonthHeader(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [
            $this->createMockGame('October', '2025-10-15'),
        ];

        $result = $this->view->render($mockTeam, $games, 7, 'Regular Season');

        $this->assertStringContainsString('October', $result);
    }

    public function testMonthHeaderCarriesIblCardHeaderClass(): void
    {
        $mockTeam = $this->createMockTeam();

        $regular = $this->view->render($mockTeam, [$this->createMockGame('October', '2025-10-15')], 7, 'Regular Season');
        $this->assertStringContainsString('class="ibl-card__header schedule-month__header"', $regular);

        $playoffs = $this->view->render($mockTeam, [$this->createMockGame('June', '2026-06-05')], 7, 'Playoffs');
        $this->assertStringContainsString(
            'class="ibl-card__header schedule-month__header schedule-month__header--playoffs"',
            $playoffs
        );
    }

    public function testRenderWithMultipleMonthsShowsMultipleHeaders(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [
            $this->createMockGame('October', '2025-10-15'),
            $this->createMockGame('November', '2025-11-15'),
        ];

        $result = $this->view->render($mockTeam, $games, 7, 'Regular Season');

        $this->assertStringContainsString('October', $result);
        $this->assertStringContainsString('November', $result);
    }

    public function testRenderUsesTeamColors(): void
    {
        $mockTeam = $this->createMockTeam('FF0000', '000000');
        $games = [];

        $result = $this->view->render($mockTeam, $games, 7, 'Regular Season');

        $this->assertStringContainsString('FF0000', $result);
    }

    public function testRenderEscapesTeamColors(): void
    {
        // Test that team colors are escaped for XSS protection
        $mockTeam = $this->createMockTeam('<script>alert(1)</script>', '000000');
        $games = [];

        $result = $this->view->render($mockTeam, $games, 7, 'Regular Season');

        // The malicious script should be escaped
        $this->assertStringNotContainsString('<script>alert(1)</script>', $result);
    }

    public function testRenderIncludesSharedScrollScriptTag(): void
    {
        $html = $this->view->render($this->createMockTeam(), [], 7, 'Regular Season');

        $this->assertSame(1, substr_count($html, '<script src="jslib/schedule-scroll.js" defer></script>'));
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRenderOmitsInlineScrollHandlers(): void
    {
        $nextGame = $this->createMockGame('October', '2025-10-15', true);
        $nextGame['highlight'] = 'next-sim';

        $html = $this->view->render($this->createMockTeam(), [$nextGame], 7, 'Regular Season');

        foreach (['onclick=', 'scrollToMonth', 'scrollToNextGames', 'headerOffset'] as $legacy) {
            $this->assertStringNotContainsString($legacy, $html);
        }
    }

    public function testMonthLinkCarriesTeamScrollTargetAttribute(): void
    {
        $html = $this->view->render(
            $this->createMockTeam(),
            [$this->createMockGame('October', '2025-10-15')],
            7,
            'Regular Season',
        );

        $this->assertStringContainsString(
            '<a href="#team-month-2025-10" class="ibl-jump-menu__link schedule-months__link" data-scroll-target="team-month-2025-10">',
            $html,
        );
        $this->assertStringNotContainsString('data-scroll-next', $html);
    }

    public function testNextGamesLinkCarriesTargetAndNextFlag(): void
    {
        $nextGame = $this->createMockGame('October', '2025-10-15', true);
        $nextGame['highlight'] = 'next-sim';

        $html = $this->view->render($this->createMockTeam(), [$nextGame], 7, 'Regular Season');

        $this->assertStringContainsString(
            '<a href="#team-game-12345" class="schedule-jump-btn" data-scroll-target="team-game-12345" data-scroll-next>',
            $html,
        );
    }

    public function testNextGamesLinkAbsentWithoutNextSimGame(): void
    {
        $html = $this->view->render(
            $this->createMockTeam(),
            [$this->createMockGame('October', '2025-10-15')],
            7,
            'Regular Season',
        );

        $this->assertStringNotContainsString('schedule-jump-btn', $html);
        $this->assertStringNotContainsString('data-scroll-next', $html);
    }

    public function testRenderEscapesHostileScrollIds(): void
    {
        // Month keys come from date('Y-m') and game ids from an integer box score ID, so render()
        // cannot carry a hostile id. Drive the three private emit sites directly.
        $hostile = '"><b>';
        $monthData = ['2025-10' . $hostile => ['label' => 'October', 'dates' => []]];

        $header = new \ReflectionMethod($this->view, 'renderHeader');
        $nav = new \ReflectionMethod($this->view, 'renderMonthNav');
        $months = new \ReflectionMethod($this->view, 'renderGamesByMonth');

        $headerHtml = $header->invoke($this->view, 7, 'team-game-1' . $hostile);
        $navHtml = $nav->invoke($this->view, $monthData, false, null);
        $monthsHtml = $months->invoke($this->view, $monthData, 1, 'Test Team', false, null);
        $this->assertIsString($headerHtml);
        $this->assertIsString($navHtml);
        $this->assertIsString($monthsHtml);
        $html = $headerHtml . $navHtml . $monthsHtml;

        $this->assertStringNotContainsString($hostile, $html);
        $this->assertStringContainsString('href="#team-game-1&quot;&gt;&lt;b&gt;"', $html);
        $this->assertStringContainsString('data-scroll-target="team-game-1&quot;&gt;&lt;b&gt;"', $html);
        $this->assertStringContainsString('href="#team-month-2025-10&quot;&gt;&lt;b&gt;"', $html);
        $this->assertStringContainsString('data-scroll-target="team-month-2025-10&quot;&gt;&lt;b&gt;"', $html);
        $this->assertStringContainsString('id="team-month-2025-10&quot;&gt;&lt;b&gt;"', $html);
    }

    public function testRenderShowsWinResult(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [
            $this->createMockGame('October', '2025-10-15', false, 'green'),
        ];

        $result = $this->view->render($mockTeam, $games, 7, 'Regular Season');

        // Win is now shown by highlighting the winning team's score/record
        $this->assertStringContainsString('schedule-game__team--win', $result);
    }

    public function testRenderShowsLossResult(): void
    {
        $mockTeam = $this->createMockTeam();
        $games = [
            $this->createMockGame('October', '2025-10-15', false, 'red'),
        ];

        $result = $this->view->render($mockTeam, $games, 7, 'Regular Season');

        // Loss is shown by NOT having the --win class on the user's team
        // The opponent's score gets the --win class instead
        $this->assertStringContainsString('schedule-game__team--win', $result);
    }

    /**
     * Golden characterization: the full row HTML is byte-identical for every renderGameRow branch.
     *
     * @param GoldenSpec $spec
     */
    #[DataProvider('goldenRowProvider')]
    public function testRenderGameRowMatchesGoldenHtml(array $spec, string $expectedRow): void
    {
        $team = $this->createMockTeam(name: $spec['userTeamName']);

        $html = $this->view->render($team, [$this->buildGoldenRow($spec)], 7, 'Regular Season');

        $this->assertSame($expectedRow, $this->extractSingleGameRow($html));
    }

    /**
     * @return array<string, array{0: GoldenSpec, 1: string}>
     */
    public static function goldenRowProvider(): array
    {
        return [
            'played_user_away_won' => [self::spec(), '<div class="schedule-game" id="team-game-12345"><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__team-link"><span class="schedule-game__team schedule-game__team--win"><span class="schedule-game__team-text">Test Team</span> <span class="schedule-game__record">(10-5)</span></span></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__logo-link" aria-label="Test Team"><img class="schedule-game__logo" src="images/logo/new1.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link schedule-game__team--win">105</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__vs">@</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link">98</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__logo-link" aria-label="Boston"><img class="schedule-game__logo" src="images/logo/new2.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__team-link"><span class="schedule-game__team"><span class="schedule-game__team-text">Boston</span> <span class="schedule-game__record">(10-5)</span></span></a><span class="schedule-game__streak schedule-game__streak--win">W3</span></div>'],
            'played_user_away_lost' => [self::spec(winLossColor: 'red', streak: 'L 2'), '<div class="schedule-game" id="team-game-12345"><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__team-link"><span class="schedule-game__team"><span class="schedule-game__team-text">Test Team</span> <span class="schedule-game__record">(10-5)</span></span></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__logo-link" aria-label="Test Team"><img class="schedule-game__logo" src="images/logo/new1.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link">105</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__vs">@</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link schedule-game__team--win">98</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__logo-link" aria-label="Boston"><img class="schedule-game__logo" src="images/logo/new2.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__team-link"><span class="schedule-game__team schedule-game__team--win"><span class="schedule-game__team-text">Boston</span> <span class="schedule-game__record">(10-5)</span></span></a><span class="schedule-game__streak schedule-game__streak--loss">L2</span></div>'],
            'played_user_home_won' => [self::spec(visitorId: 2, homeId: 1), '<div class="schedule-game" id="team-game-12345"><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__team-link"><span class="schedule-game__team"><span class="schedule-game__team-text">Boston</span> <span class="schedule-game__record">(10-5)</span></span></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__logo-link" aria-label="Boston"><img class="schedule-game__logo" src="images/logo/new2.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link">105</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__vs">@</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link schedule-game__team--win">98</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__logo-link" aria-label="Test Team"><img class="schedule-game__logo" src="images/logo/new1.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__team-link"><span class="schedule-game__team schedule-game__team--win"><span class="schedule-game__team-text">Test Team</span> <span class="schedule-game__record">(10-5)</span></span></a><span class="schedule-game__streak schedule-game__streak--win">W3</span></div>'],
            'played_user_home_lost' => [self::spec(visitorId: 2, homeId: 1, winLossColor: 'red', streak: 'L 2'), '<div class="schedule-game" id="team-game-12345"><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__team-link"><span class="schedule-game__team schedule-game__team--win"><span class="schedule-game__team-text">Boston</span> <span class="schedule-game__record">(10-5)</span></span></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__logo-link" aria-label="Boston"><img class="schedule-game__logo" src="images/logo/new2.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link schedule-game__team--win">105</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__vs">@</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link">98</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__logo-link" aria-label="Test Team"><img class="schedule-game__logo" src="images/logo/new1.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__team-link"><span class="schedule-game__team"><span class="schedule-game__team-text">Test Team</span> <span class="schedule-game__record">(10-5)</span></span></a><span class="schedule-game__streak schedule-game__streak--loss">L2</span></div>'],
            'unplayed_user_away_next_sim_with_tier' => [self::spec(isUnplayed: true, winLossColor: '', streak: '', highlight: 'next-sim', opponentTier: 'elite'), '<div class="schedule-game schedule-game--upcoming" id="team-game-12345"><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__team-link"><span class="schedule-game__team"><span class="schedule-game__team-text">Test Team</span></span></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__logo-link" aria-label="Test Team"><img class="schedule-game__logo" src="images/logo/new1.png" alt="" width="25" height="25" loading="lazy"></a><span class="schedule-game__score-link">–</span><span class="schedule-game__vs">@</span><span class="schedule-game__score-link">–</span><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__logo-link" aria-label="Boston"><img class="schedule-game__logo" src="images/logo/new2.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__team-link"><span class="schedule-game__team"><span class="schedule-game__team-text">Boston</span> <span class="schedule-game__record">(10-5)</span></span></a><span class="schedule-game__streak"><span class="sos-tier-dot sos-tier--elite" title="elite"></span></span></div>'],
            'unplayed_user_home_without_tier' => [self::spec(visitorId: 2, homeId: 1, isUnplayed: true, winLossColor: '', streak: ''), '<div class="schedule-game" id="team-game-12345"><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__team-link"><span class="schedule-game__team"><span class="schedule-game__team-text">Boston</span> <span class="schedule-game__record">(10-5)</span></span></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__logo-link" aria-label="Boston"><img class="schedule-game__logo" src="images/logo/new2.png" alt="" width="25" height="25" loading="lazy"></a><span class="schedule-game__score-link">–</span><span class="schedule-game__vs">@</span><span class="schedule-game__score-link">–</span><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__logo-link" aria-label="Test Team"><img class="schedule-game__logo" src="images/logo/new1.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__team-link"><span class="schedule-game__team"><span class="schedule-game__team-text">Test Team</span></span></a><span class="schedule-game__streak"></span></div>'],
            'played_no_box_score_url' => [self::spec(gameOfThatDay: 0, boxScoreID: 0), '<div class="schedule-game" id="team-game-0"><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__team-link"><span class="schedule-game__team schedule-game__team--win"><span class="schedule-game__team-text">Test Team</span> <span class="schedule-game__record">(10-5)</span></span></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__logo-link" aria-label="Test Team"><img class="schedule-game__logo" src="images/logo/new1.png" alt="" width="25" height="25" loading="lazy"></a><span class="schedule-game__score-link schedule-game__team--win">105</span><span class="schedule-game__vs">@</span><span class="schedule-game__score-link">98</span><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__logo-link" aria-label="Boston"><img class="schedule-game__logo" src="images/logo/new2.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__team-link"><span class="schedule-game__team"><span class="schedule-game__team-text">Boston</span> <span class="schedule-game__record">(10-5)</span></span></a><span class="schedule-game__streak schedule-game__streak--win">W3</span></div>'],
            'played_opponent_record_null' => [self::spec(opponentRecord: null), '<div class="schedule-game" id="team-game-12345"><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__team-link"><span class="schedule-game__team schedule-game__team--win"><span class="schedule-game__team-text">Test Team</span> <span class="schedule-game__record">(10-5)</span></span></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__logo-link" aria-label="Test Team"><img class="schedule-game__logo" src="images/logo/new1.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link schedule-game__team--win">105</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__vs">@</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link">98</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__logo-link" aria-label="Boston"><img class="schedule-game__logo" src="images/logo/new2.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__team-link"><span class="schedule-game__team"><span class="schedule-game__team-text">Boston</span></span></a><span class="schedule-game__streak schedule-game__streak--win">W3</span></div>'],
            'played_opponent_record_empty_user_home' => [self::spec(visitorId: 2, homeId: 1, opponentRecord: ''), '<div class="schedule-game" id="team-game-12345"><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__team-link"><span class="schedule-game__team"><span class="schedule-game__team-text">Boston</span></span></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__logo-link" aria-label="Boston"><img class="schedule-game__logo" src="images/logo/new2.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link">105</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__vs">@</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link schedule-game__team--win">98</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__logo-link" aria-label="Test Team"><img class="schedule-game__logo" src="images/logo/new1.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__team-link"><span class="schedule-game__team schedule-game__team--win"><span class="schedule-game__team-text">Test Team</span> <span class="schedule-game__record">(10-5)</span></span></a><span class="schedule-game__streak schedule-game__streak--win">W3</span></div>'],
            'escapes_names_and_records_user_away' => [self::spec(opponentName: '"Q" & <O\'s>', opponentRecord: '<10>&5', userTeamName: 'Tom & Jerry\'s <Team>'), '<div class="schedule-game" id="team-game-12345"><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__team-link"><span class="schedule-game__team schedule-game__team--win"><span class="schedule-game__team-text">Tom &amp; Jerry&apos;s &lt;Team&gt;</span> <span class="schedule-game__record">(10-5)</span></span></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__logo-link" aria-label="Tom &amp; Jerry&apos;s &lt;Team&gt;"><img class="schedule-game__logo" src="images/logo/new1.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link schedule-game__team--win">105</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__vs">@</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link">98</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__logo-link" aria-label="&quot;Q&quot; &amp; &lt;O&apos;s&gt;"><img class="schedule-game__logo" src="images/logo/new2.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__team-link"><span class="schedule-game__team"><span class="schedule-game__team-text">&quot;Q&quot; &amp; &lt;O&apos;s&gt;</span> <span class="schedule-game__record">(&lt;10&gt;&amp;5)</span></span></a><span class="schedule-game__streak schedule-game__streak--win">W3</span></div>'],
            'escapes_names_and_records_user_home' => [self::spec(visitorId: 2, homeId: 1, opponentName: '"Q" & <O\'s>', opponentRecord: '<10>&5', userTeamName: 'Tom & Jerry\'s <Team>'), '<div class="schedule-game" id="team-game-12345"><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__team-link"><span class="schedule-game__team"><span class="schedule-game__team-text">&quot;Q&quot; &amp; &lt;O&apos;s&gt;</span> <span class="schedule-game__record">(&lt;10&gt;&amp;5)</span></span></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=2" class="schedule-game__logo-link" aria-label="&quot;Q&quot; &amp; &lt;O&apos;s&gt;"><img class="schedule-game__logo" src="images/logo/new2.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link">105</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__vs">@</a><a href="modules.php?name=GameBoxscore&date=2025-10-15&game=3" class="schedule-game__score-link schedule-game__team--win">98</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__logo-link" aria-label="Tom &amp; Jerry&apos;s &lt;Team&gt;"><img class="schedule-game__logo" src="images/logo/new1.png" alt="" width="25" height="25" loading="lazy"></a><a href="modules.php?name=Team&amp;op=team&amp;teamid=1" class="schedule-game__team-link"><span class="schedule-game__team schedule-game__team--win"><span class="schedule-game__team-text">Tom &amp; Jerry&apos;s &lt;Team&gt;</span> <span class="schedule-game__record">(10-5)</span></span></a><span class="schedule-game__streak schedule-game__streak--win">W3</span></div>'],
        ];
    }

    /**
     * Build one fixture spec; named args override the played, user-away, user-won default.
     *
     * @return GoldenSpec
     */
    private static function spec(
        int $visitorId = 1,
        int $homeId = 2,
        int $gameOfThatDay = 3,
        int $boxScoreID = 12345,
        bool $isUnplayed = false,
        string $winLossColor = 'green',
        string $streak = 'W 3',
        string $highlight = '',
        string $opponentTier = '',
        string $opponentName = 'Boston',
        ?string $opponentRecord = '10-5',
        string $userTeamName = 'Test Team'
    ): array {
        return [
            'visitorId' => $visitorId,
            'homeId' => $homeId,
            'gameOfThatDay' => $gameOfThatDay,
            'boxScoreID' => $boxScoreID,
            'isUnplayed' => $isUnplayed,
            'winLossColor' => $winLossColor,
            'streak' => $streak,
            'highlight' => $highlight,
            'opponentTier' => $opponentTier,
            'opponentName' => $opponentName,
            'opponentRecord' => $opponentRecord,
            'userTeamName' => $userTeamName,
        ];
    }

    /**
     * @param GoldenSpec $spec
     * @return ScheduleGameRow
     */
    private function buildGoldenRow(array $spec): array
    {
        $game = self::createStub(\LeagueSchedule\Game::class);
        $game->date = '2025-10-15';
        $game->visitorScore = 105;
        $game->homeScore = 98;
        $game->boxScoreID = $spec['boxScoreID'];
        $game->gameOfThatDay = $spec['gameOfThatDay'];
        $game->visitor_teamid = $spec['visitorId'];
        $game->home_teamid = $spec['homeId'];

        $opposingTeam = self::createStub(\Team\Team::class);
        $opposingTeam->teamid = $spec['visitorId'] === 1 ? $spec['homeId'] : $spec['visitorId'];
        $opposingTeam->name = $spec['opponentName'];
        $opposingTeam->seasonRecord = $spec['opponentRecord'];

        return [
            'currentMonth' => 'October',
            'game' => $game,
            'opposingTeam' => $opposingTeam,
            'highlight' => $spec['highlight'],
            'opponentText' => 'x',
            'isUnplayed' => $spec['isUnplayed'],
            'winLossColor' => $spec['winLossColor'],
            'gameResult' => $spec['winLossColor'] === 'green' ? 'W' : 'L',
            'wins' => 10,
            'losses' => 5,
            'streak' => $spec['streak'],
            'opponentTier' => $spec['opponentTier'],
        ];
    }

    /**
     * Extract the single schedule-game row. Rows contain no nested <div>, so the lazy match ends at the row's own </div>.
     */
    private function extractSingleGameRow(string $html): string
    {
        $count = preg_match_all('/<div class="schedule-game[^"]*" id="team-game-[^"]*">.*?<\/div>/s', $html, $matches);
        $this->assertSame(1, $count, 'expected exactly one schedule-game row in the rendered page');

        return $matches[0][0];
    }

    /**
     * Create a mock Team object for testing
     *
     * Uses a testable subclass that allows setting public properties
     */
    private function createMockTeam(string $color1 = 'FFFFFF', string $color2 = '000000', string $name = 'Test Team'): Team
    {
        $team = $this->getMockBuilder(Team::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Set public properties directly - these exist on Team class
        $team->teamid = 1;
        $team->name = $name;
        $team->color1 = $color1;
        $team->color2 = $color2;

        return $team;
    }

    /**
     * Create a mock game row for testing
     *
     * Returns the full data structure expected by TeamScheduleView
     *
     * @return ScheduleGameRow
     */
    private function createMockGame(
        string $month,
        string $date = '2025-01-15',
        bool $isUnplayed = false,
        string $winLossColor = 'green'
    ): array {
        // Create stub Game object
        $game = self::createStub(\LeagueSchedule\Game::class);
        $game->date = $date;
        $game->visitorScore = 105;
        $game->homeScore = 98;
        $game->boxScoreID = 12345;
        $game->gameOfThatDay = 3;
        $game->visitor_teamid = 1;  // User's team is visitor
        $game->home_teamid = 2;     // Opponent is home team

        // Create stub opposing Team object
        $opposingTeam = self::createStub(\Team\Team::class);
        $opposingTeam->teamid = 2;
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
            'opponentTier' => '',
        ];
    }
}
