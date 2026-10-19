<?php

declare(strict_types=1);

namespace Tests\Rivalries;

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
        self::assertStringContainsString('ibl-badge', $html);
        self::assertStringContainsString('101-99', $html);
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
            . $this->view->renderPair(self::page(['series' => []]), 'teams')
            . $this->view->renderIndex(['franchises' => [self::rivalry('3', '7')], 'gms' => []], '3', null)
            . $this->view->renderGm('Mover Gm', [], true, null);

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
                'visitor_teamid' => 3, 'home_teamid' => 7, 'visitor_label' => 'Threes', 'home_label' => 'Sevens',
                'visitor_points' => 101, 'home_points' => 99, 'visitor_side' => '3', 'home_side' => '7',
            ]],
            'blowouts' => [],
            'totals' => [['pid' => 9001, 'name' => 'Star Player', 'side' => '3', 'games' => 10, 'total_points' => 250]],
            'highs' => [['pid' => 9001, 'name' => 'Star Player', 'side' => '3', 'points' => 41, 'game_date' => '2001-12-01']],
        ];

        /** @var PairPage $merged */
        $merged = array_merge($page, $overrides);

        return $merged;
    }
}
