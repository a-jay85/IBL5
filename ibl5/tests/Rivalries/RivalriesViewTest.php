<?php

declare(strict_types=1);

namespace Tests\Rivalries;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rivalries\Contracts\RivalriesServiceInterface;
use Rivalries\Contracts\RivalryScorerInterface;
use Rivalries\RivalriesView;

/**
 * @phpstan-import-type PairPage from RivalriesServiceInterface
 * @phpstan-import-type ScoredRivalry from RivalryScorerInterface
 */
final class RivalriesViewTest extends TestCase
{
    private const HOSTILE = '<script>alert(1)</script>';

    private RivalriesView $view;

    protected function setUp(): void
    {
        $this->view = new RivalriesView();
    }

    public function testHostileGmNameIsEscapedEverywhere(): void
    {
        $outputs = [
            $this->view->renderPair(self::page(['kind' => 'gms', 'a' => self::HOSTILE, 'a_label' => self::HOSTILE]), 'gms'),
            $this->view->renderGm(self::HOSTILE, [self::rivalry(self::HOSTILE, 'Rival Gm')], true, null),
            $this->view->renderIndex(['franchises' => [], 'gms' => [self::rivalry(self::HOSTILE, 'Rival Gm')]], null, null),
        ];

        foreach ($outputs as $html) {
            self::assertStringContainsString('&lt;script&gt;', $html);
            self::assertStringNotContainsString('<script>alert', $html);
        }
    }

    public function testPairLinksUseUrlencodedParams(): void
    {
        $html = $this->view->renderIndex(['franchises' => [], 'gms' => [self::rivalry('Amy', 'Zed <b>')]], null, null);

        self::assertStringContainsString('a=Amy&amp;b=Zed+%3Cb%3E', $html);
    }

    public function testTeamPageEscapesNameLinksRowsAndShowsEmptyState(): void
    {
        $html = $this->view->renderTeam(self::HOSTILE, [self::rivalry('3', '7')], [], null, [], [], null, null);

        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;&apos;s Rivals</h2>', $html);
        self::assertStringNotContainsString('<script>alert', $html);
        self::assertStringContainsString('op=teams&amp;a=3&amp;b=7', $html);

        $empty = $this->view->renderTeam('New York Metros', [], [], null, [], [], null, null);

        self::assertStringContainsString('This franchise has no rival with 10 or more games yet.', $empty);
        self::assertStringContainsString('<h1 class="ibl-title">Rivalries</h1>', $empty);
        self::assertStringContainsString('>New York Metros&apos;s Rivals</h2>', $empty);
        self::assertStringNotContainsString("Metros' rivals", $empty);
    }

    public function testTeamPageRendersTeamAndGmTitles(): void
    {
        $html = $this->view->renderTeam(
            'Metros',
            [self::rivalry('3', '7')],
            [],
            'Mover Gm',
            [self::rivalry('Mover Gm', 'Rival Gm')],
            [],
            null,
            null,
        );

        self::assertStringContainsString('>Metros&apos;s Rivals</h2>', $html);
        self::assertStringContainsString('Mover Gm&apos;s Rivals', $html);
        self::assertSame(2, substr_count($html, '<table'));
        self::assertStringContainsString('op=gms&amp;a=Mover+Gm&amp;b=Rival+Gm', $html);
    }

    public function testTeamPageWithoutGmShowsEmptyGmTable(): void
    {
        $html = $this->view->renderTeam('Metros', [], [], null, [], [], null, null);

        self::assertStringContainsString('>GM Rivals</h2>', $html);
        self::assertStringContainsString('This franchise has no general manager right now.', $html);
    }

    public function testTeamPageEscapesGmNameInTitle(): void
    {
        $html = $this->view->renderTeam('Metros', [], [], self::HOSTILE, [], [], null, null);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert', $html);
    }

    public function testTeamPageWrapsTablesInViewerColorWhenCaptionColorGiven(): void
    {
        $html = $this->view->renderTeam('Metros', [], [], null, [], [], null, null, '112233');

        self::assertStringContainsString('<div class="rivalries-team-colored" style="--team-tab-bg-color: #112233;">', $html);
        self::assertStringEndsWith('</div>', $html);
        self::assertStringStartsWith('<h1 class="ibl-title">Rivalries</h1><div', $html);
    }

    public function testTeamPageHasNoColorWrapperWithoutCaptionColor(): void
    {
        $html = $this->view->renderTeam('Metros', [], [], null, [], [], null, null);

        self::assertStringNotContainsString('rivalries-team-colored', $html);
        self::assertStringNotContainsString('--team-tab-bg-color', $html);
    }

    public function testCaptionColorIsSanitized(): void
    {
        $html = $this->view->renderTeam('Metros', [], [], null, [], [], null, null, '"><script>alert(1)</script>');

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('--team-tab-bg-color: #000000;', $html);
    }

    public function testTeamPageHighlightsViewerGmRow(): void
    {
        $html = $this->view->renderTeam('Metros', [], [], 'Mover Gm', [self::rivalry('Mover Gm', 'Rival Gm')], [], null, 'Rival Gm');

        self::assertSame(1, substr_count($html, 'user-team-row'));
    }

    public function testRowWithBothSideColorsGetsGradientCell(): void
    {
        $html = $this->view->renderTeam('Metros', [self::rivalry('3', '7')], ['3' => '002B5C', '7' => 'CE1141'], null, [], [], null, null);

        self::assertStringContainsString('<td class="rivalries-gradient" style="', $html);
        self::assertStringContainsString('--rivalry-color-a: #002B5C', $html);
        self::assertStringContainsString('--rivalry-color-b: #CE1141', $html);
        self::assertStringContainsString('--rivalry-text: #ffffff; --rivalry-text-shadow: #000000;', $html);
    }

    public function testGmRowsGetGradientFromGmColorMap(): void
    {
        $html = $this->view->renderGm('Amy', [self::rivalry('Amy', 'Zed')], true, null, ['Amy' => '112233', 'Zed' => 'e9eaeb']);

        self::assertStringContainsString('<td class="rivalries-gradient" style="--rivalry-color-a: #112233; --rivalry-color-b: #e9eaeb;', $html);
    }

    public function testRowMissingASideColorGetsPlainCell(): void
    {
        $html = $this->view->renderTeam('Metros', [self::rivalry('3', '7')], ['3' => '002B5C'], null, [], [], null, null);

        self::assertStringNotContainsString('rivalries-gradient', $html);
        self::assertMatchesRegularExpression('/<td>1<\/td><td><a href=/', $html);
    }

    public function testIndexHasNoGradientCells(): void
    {
        $html = $this->view->renderIndex(['franchises' => [self::rivalry('3', '7')], 'gms' => [self::rivalry('Amy', 'Zed')]], null, null);

        self::assertStringNotContainsString('rivalries-gradient', $html);
    }

    public function testNullPairRendersEmptyState(): void
    {
        $html = $this->view->renderPair(null, 'teams');

        self::assertStringContainsString('ibl-empty-state', $html);
        self::assertStringNotContainsString('<table', $html);
    }

    public function testBelowCutoffNoteShowsWhenNotQualifying(): void
    {
        $note = 'Fewer than 10 meetings';

        self::assertStringContainsString($note, $this->view->renderPair(self::page(['qualifies' => false]), 'teams'));
        self::assertStringNotContainsString($note, $this->view->renderPair(self::page(['qualifies' => true]), 'teams'));
    }

    public function testEmptyLeagueListsRenderEmptyState(): void
    {
        $html = $this->view->renderIndex(['franchises' => [], 'gms' => []], null, null);

        self::assertSame(2, substr_count($html, 'ibl-empty-state'));
    }

    public function testPairPageRendersRecordStreakSeriesGamesAndPerformers(): void
    {
        $html = $this->view->renderPair(self::page([]), 'teams');

        self::assertStringContainsString('8-4', $html);
        self::assertStringContainsString('Round 2', $html);
        self::assertStringContainsString('won 3 straight', $html);
        self::assertStringContainsString('pid=9001', $html);
        self::assertStringContainsString('<span class="award-year">All-time</span> 8-4', $html);
        self::assertStringNotContainsString('ibl-badge', $html);
        self::assertStringContainsString('101-99', $html);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function captionTextProvider(): array
    {
        return [
            'two dark colors' => ['002B5C', 'CE1141', '--rivalry-text: #ffffff; --rivalry-text-shadow: #000000;'],
            'two light colors' => ['FDB927', 'FFFFFF', '--rivalry-text: #000000; --rivalry-text-shadow: #ffffff;'],
            // The blended middle (#807593) is dark enough that white wins the worst case.
            'dark into light' => ['1D1160', 'E3D9C6', '--rivalry-text: #ffffff; --rivalry-text-shadow: #000000;'],
        ];
    }

    #[DataProvider('captionTextProvider')]
    public function testTeamColorCaptionsPickReadableText(string $a, string $b, string $expected): void
    {
        $html = $this->view->renderPair(self::page([]), 'teams', ['a' => $a, 'b' => $b]);

        self::assertStringContainsString(
            '<div class="rivalries-pair" style="--rivalry-color-a: #' . $a . '; --rivalry-color-b: #' . $b . '; ' . $expected . '">',
            $html,
        );
        self::assertStringEndsWith('</div></div>', $html);
    }

    public function testTeamColorsAreSanitized(): void
    {
        $html = $this->view->renderPair(self::page([]), 'teams', ['a' => '"><script>', 'b' => '#abc']);

        self::assertStringContainsString('--rivalry-color-a: #000000; --rivalry-color-b: #abc;', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testPairWithoutColorsHasNoColorWrapper(): void
    {
        self::assertStringNotContainsString('rivalries-pair', $this->view->renderPair(self::page([]), 'teams'));
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function marginProvider(): array
    {
        return [
            'visitor side won' => ['3', '<td class="rivalries-margin rivalries-margin--win">+2</td>'],
            'home side lost' => ['7', '<td class="rivalries-margin rivalries-margin--loss">-2</td>'],
            'no viewer' => [null, '<td>2</td></tr>'],
            'viewer outside the pair' => ['9', '<td>2</td></tr>'],
        ];
    }

    #[DataProvider('marginProvider')]
    public function testGameMarginIsSignedFromViewerSide(?string $viewer, string $expected): void
    {
        $html = $this->view->renderPair(self::page([]), 'teams', null, [], $viewer);

        self::assertStringContainsString('<th>+/-</th>', $html);
        self::assertStringContainsString($expected, $html);
    }

    public function testSummaryAndSeriesAreColoredFromWinningViewerSide(): void
    {
        $html = $this->view->renderPair(self::page(['closest' => []]), 'teams', null, [], '3');

        self::assertStringContainsString('All-time</span> <span class="rivalries-margin rivalries-margin--win">8-4</span> (12 games)', $html);
        self::assertStringContainsString('Playoffs</span> <span class="rivalries-margin rivalries-margin--win">4-2</span>', $html);
        self::assertStringContainsString('Threes has <span class="rivalries-margin rivalries-margin--win">won 3 straight</span>', $html);
        self::assertStringContainsString('<td class="rivalries-margin rivalries-margin--win">4-2</td></tr>', $html);
    }

    public function testSummaryAndSeriesFlipToLosingViewerSide(): void
    {
        $html = $this->view->renderPair(self::page(['closest' => []]), 'teams', null, [], '7');

        self::assertStringContainsString('All-time</span> <span class="rivalries-margin rivalries-margin--loss">4-8</span> (12 games)', $html);
        self::assertStringContainsString('Playoffs</span> <span class="rivalries-margin rivalries-margin--loss">2-4</span>', $html);
        self::assertStringContainsString('Threes has <span class="rivalries-margin rivalries-margin--loss">won 3 straight</span>', $html);
        self::assertStringContainsString('<td class="rivalries-margin rivalries-margin--loss">2-4</td></tr>', $html);
        self::assertStringNotContainsString('rivalries-margin--win', $html);
    }

    public function testEvenRecordAndOutsideViewerGetNoColor(): void
    {
        $even = $this->view->renderPair(self::page(['closest' => [], 'playoff_b_wins' => 4, 'streak' => null, 'series' => []]), 'teams', null, [], '3');
        self::assertStringContainsString('Playoffs</span> 4-4</li>', $even);

        $outside = $this->view->renderPair(self::page(['closest' => []]), 'teams', null, [], '9');
        self::assertStringContainsString('All-time</span> 8-4 (12 games)', $outside);
        self::assertStringContainsString('Threes has won 3 straight', $outside);
        self::assertStringContainsString('<td>4-2</td></tr>', $outside);
        self::assertStringNotContainsString('rivalries-margin', $outside);
    }

    public function testTiedGameMarginIsZeroWithNoColor(): void
    {
        $tie = self::page([])['closest'][0];
        $tie['home_points'] = 101;

        $html = $this->view->renderPair(self::page(['closest' => [$tie]]), 'teams', null, [], '3');

        self::assertStringContainsString('101-101</a></td>', $html);
        self::assertStringContainsString('</span></a></td><td>0</td></tr>', $html);
        self::assertStringNotContainsString('rivalries-margin--win">0', $html);
        self::assertStringNotContainsString('rivalries-margin--loss">0', $html);
    }

    public function testGameDatesAndScoresLinkToBoxScore(): void
    {
        $html = $this->view->renderPair(self::page([]), 'teams');
        $game = 'href="modules.php?name=GameBoxscore&amp;date=2001-12-01&amp;game=1"';

        self::assertStringContainsString('<a ' . $game . '>2001-12-01</a>', $html);
        self::assertStringContainsString('<a ' . $game . '>101-99</a>', $html);
        self::assertStringContainsString('<a href="modules.php?name=GameBoxscore&amp;date=2001-12-01&amp;game=2">2001-12-01</a>', $html);
    }

    public function testTeamPairShowsColoredTeamCells(): void
    {
        $html = $this->view->renderPair(self::page([]), 'teams', null, [3 => ['color1' => '112233', 'color2' => 'FFFFFF']]);

        self::assertStringContainsString('<th>Team</th>', $html);
        self::assertStringNotContainsString('<th>Side</th>', $html);
        self::assertStringContainsString('ibl-team-cell--colored', $html);
        self::assertStringContainsString('112233', $html);
        self::assertStringContainsString('<td class="rivalries-nowrap">Round 2</td>', $html);
    }

    public function testGameFromRetiredIdentityUsesEraColors(): void
    {
        $game = self::page([])['closest'][0];
        $game['visitor_era_colors'] = ['color1' => '00653A', 'color2' => 'FFC200'];
        $teamColors = [3 => ['color1' => '2D7BB9', 'color2' => 'FFFFFF'], 7 => ['color1' => 'AABBCC', 'color2' => '000000']];

        $html = $this->view->renderPair(self::page(['closest' => [$game], 'blowouts' => []]), 'teams', null, $teamColors);
        $start = (int) strpos($html, 'Closest games');
        $games = substr($html, $start, (int) strpos($html, 'Biggest blowouts') - $start);

        self::assertStringContainsString('00653A', $games);
        self::assertStringNotContainsString('2D7BB9', $games);
        self::assertStringContainsString('AABBCC', $games);
    }

    public function testTeamPairTableCellsUseShortLabels(): void
    {
        $html = $this->view->renderPair(self::page([]), 'teams', null, [], null, ['3' => 'Tres']);

        // Series winner, top scorer, and best single game; the streak line keeps the full label.
        self::assertSame(3, substr_count($html, 'Tres'));
        self::assertStringContainsString('Threes has', $html);
    }

    public function testPairCardsShareOneGrid(): void
    {
        $html = $this->view->renderPair(self::page([]), 'teams');
        $grid = (string) strstr($html, '<div class="ibl-grid rivalries-cards">');

        self::assertSame(4, substr_count($grid, '<section class="rivalries-section">'));
    }

    public function testTeamPairHeadingShowsBothTeamBanners(): void
    {
        $teamColors = [3 => ['color1' => '112233', 'color2' => 'FFFFFF'], 7 => ['color1' => 'AABBCC', 'color2' => '000000']];
        $html = $this->view->renderPair(self::page(['b_label' => self::HOSTILE]), 'teams', null, $teamColors);

        self::assertStringStartsWith(
            '<h1 class="rivalries-banner-title"><a class="team-banner-box" style="--team-color-primary: #112233;" href="modules.php?name=Team&amp;op=team&amp;teamid=3">'
            . '<img src="images/logo/3.jpg" alt="Threes" width="415" height="50"></a>'
            . '<span class="rivalries-banner-vs">vs</span><a class="team-banner-box" style="--team-color-primary: #AABBCC;" href="modules.php?name=Team&amp;op=team&amp;teamid=7">'
            . '<img src="images/logo/7.jpg" alt="&lt;script&gt;',
            $html,
        );
    }

    public function testGmPairLabelsPerformersByGm(): void
    {
        $html = $this->view->renderPair(self::page(['kind' => 'gms', 'a' => 'Mover Gm', 'b' => 'Rival Gm', 'a_label' => 'Mover Gm', 'b_label' => 'Rival Gm']), 'gms');

        self::assertStringContainsString('<h1 class="ibl-title">Mover Gm vs Rival Gm</h1>', $html);
        self::assertStringContainsString('<th>GM</th>', $html);
        self::assertStringNotContainsString('<th>Team</th>', $html);
    }

    public function testTitledTableKeepsTitleOutsideScrollBox(): void
    {
        $html = $this->view->renderPair(self::page([]), 'teams');

        self::assertStringContainsString(
            '<section class="rivalries-section"><h2 class="rivalries-caption">Closest games</h2><div class="table-scroll-wrapper">',
            $html,
        );
        self::assertStringNotContainsString('<caption', $html);
    }

    public function testIndexHighlightsUserTeamRow(): void
    {
        $league = ['franchises' => [self::rivalry('3', '7'), self::rivalry('1', '9')], 'gms' => []];

        $html = $this->view->renderIndex($league, '3', null);

        self::assertSame(1, substr_count($html, 'user-team-row'));
        self::assertMatchesRegularExpression('/<tr class="user-team-row"><td>1<\/td><td><a href="[^"]*a=3&amp;b=7"/', $html);
    }

    public function testIndexHighlightsUserGmRow(): void
    {
        $league = ['franchises' => [], 'gms' => [self::rivalry('Mover Gm', 'Rival Gm'), self::rivalry('Alpha Gm', 'Zed Gm')]];

        $html = $this->view->renderIndex($league, null, 'Rival Gm');

        self::assertSame(1, substr_count($html, 'user-team-row'));
    }

    public function testRenderedClassesExistInDesignSystem(): void
    {
        $html = $this->view->renderPair(self::page(['kind' => 'gms']), 'gms')
            . $this->view->renderPair(self::page(['series' => []]), 'teams', ['a' => '002B5C', 'b' => 'CE1141'])
            . $this->view->renderIndex(['franchises' => [self::rivalry('3', '7')], 'gms' => []], '3', null)
            . $this->view->renderGm('Mover Gm', [], true, null)
            . $this->view->renderTeam('Metros', [self::rivalry('3', '7')], ['3' => '002B5C', '7' => 'CE1141'], 'Mover Gm', [], [], null, null)
            . $this->view->renderTeam('Metros', [], [], null, [], [], null, null, '002B5C');
        self::assertStringContainsString('rivalries-gradient', $html);
        self::assertStringContainsString('rivalries-team-colored', $html);

        $css = '';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/design'));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'css') {
                $css .= (string) file_get_contents($file->getPathname());
            }
        }

        preg_match_all('/class="([^"]+)"/', $html, $matches);
        $tokens = array_unique(array_merge(...array_map(
            static fn (string $c): array => explode(' ', trim($c)),
            $matches[1],
        )));
        self::assertNotSame([], $tokens);
        foreach ($tokens as $token) {
            self::assertStringContainsString('.' . $token, $css, "Class {$token} is not in ibl5/design");
        }
    }

    /**
     * @return ScoredRivalry
     */
    private static function rivalry(string $a, string $b): array
    {
        return ['a' => $a, 'b' => $b, 'a_label' => $a, 'b_label' => $b, 'games' => 12, 'a_wins' => 7, 'b_wins' => 5, 'score' => 40];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return PairPage
     */
    private static function page(array $overrides): array
    {
        $page = [
            'kind' => 'teams',
            'a' => '3',
            'b' => '7',
            'a_label' => 'Threes',
            'b_label' => 'Sevens',
            'games' => 12,
            'a_wins' => 8,
            'b_wins' => 4,
            'playoff_a_wins' => 4,
            'playoff_b_wins' => 2,
            'score' => 55,
            'qualifies' => true,
            'streak' => ['side' => '3', 'length' => 3],
            'series' => [['year' => 2002, 'round' => 2, 'winner_tid' => 3, 'loser_tid' => 7, 'winner_games' => 4, 'loser_games' => 2, 'winner_side' => '3']],
            'closest' => [[
                'game_date' => '2001-12-01', 'game_of_that_day' => 1, 'season_year' => 2002, 'game_type' => 1,
                'visitor_teamid' => 3, 'home_teamid' => 7, 'visitor_label' => 'Threes', 'home_label' => 'Sevens', 'visitor_era_colors' => null, 'home_era_colors' => null,
                'visitor_points' => 101, 'home_points' => 99, 'visitor_side' => '3', 'home_side' => '7',
            ]],
            'blowouts' => [],
            'totals' => [['pid' => 9001, 'name' => 'Star Player', 'side' => '3', 'games' => 10, 'total_points' => 250]],
            'highs' => [['pid' => 9001, 'name' => 'Star Player', 'side' => '3', 'points' => 41, 'game_date' => '2001-12-01', 'game_of_that_day' => 2]],
        ];

        /** @var PairPage $merged */
        $merged = array_merge($page, $overrides);

        return $merged;
    }
}
