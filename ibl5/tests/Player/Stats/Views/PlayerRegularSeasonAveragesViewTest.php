<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStatsRepository;
use Player\Stats\Views\PlayerRegularSeasonAveragesView;
use Tests\Support\GoldenSnapshotTrait;

/** @covers \Player\Stats\Views\PlayerRegularSeasonAveragesView */
class PlayerRegularSeasonAveragesViewTest extends TestCase
{
    use GoldenSnapshotTrait;

    /**
     * @param list<array<string, int|string>> $history
     * @param array<string, float|int|string>|null $career
     */
    private function makeView(array $history, ?array $career): PlayerRegularSeasonAveragesView
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getHistoricalStats')->willReturn($history);
        $repository->method('getSeasonCareerAveragesById')->willReturn($career);

        return new PlayerRegularSeasonAveragesView($repository);
    }

    public function testCareerRowFromRepositoryTakesPrecedence(): void
    {
        $view = $this->makeView(
            RegularSeasonViewFixtures::seasonRows(),
            TournamentViewFixtures::careerAveragesRow()
        );

        $html = $view->renderAverages(42);

        $this->assertStringContainsString('<td>8</td>', $html);      // repository games
        $this->assertStringContainsString('<td>21.5</td>', $html);   // repository pts
        // The computed fallback (132 games) must never run when the repository answers.
        $this->assertStringNotContainsString('<td>132</td>', $html);
        $this->assertSnapshotMatches($html, 'PlayerRegularSeasonAveragesView.html');
    }

    public function testCareerAveragesComputedFromHistoryWhenRepositoryHasNone(): void
    {
        $view = $this->makeView(RegularSeasonViewFixtures::seasonRows(), null);

        $html = $view->renderAverages(42);

        $this->assertStringContainsString('<td>132</td>', $html);   // 41 + 58 + 33
        // 2481 / 132 rounded to 2dp = 18.8. The 2481 includes the pts === 0 correction
        // for season 2031 ((2 * 187) + 94 + 41 = 509); without it the value would differ.
        $this->assertStringContainsString('<td>18.8</td>', $html);
        $this->assertSnapshotMatches($html, 'PlayerRegularSeasonAveragesView-computed.html');
    }

    public function testNoCareerRowWhenHistoryIsEmpty(): void
    {
        $view = $this->makeView([], null);

        $html = $view->renderAverages(42);

        $this->assertStringNotContainsString('>Career<', $html);
        $this->assertSnapshotMatches($html, 'PlayerRegularSeasonAveragesView-empty.html');
    }

    public function testZeroGamesSeasonRendersLiteralZeroesAndNoCareerRow(): void
    {
        $view = $this->makeView(RegularSeasonViewFixtures::zeroGamesRows(), null);

        $html = $view->renderAverages(42);

        // totalGames === 0 is the SECOND null path, distinct from the empty-array path.
        $this->assertStringNotContainsString('>Career<', $html);
        // With games === 0 every column takes the ternary's literal '0.0' branch.
        // If `$gm > 0` were relaxed to `$gm >= 0`, the three percentage columns would
        // instead call formatPercentageWithDecimals(0, 0), which returns '0.000'.
        $this->assertStringNotContainsString('0.000', $html);
        $this->assertStringContainsString('<td>0.0</td>', $html);
    }

    public function testCareerRowSurvivesSeasonWithNoShotAttempts(): void
    {
        $view = $this->makeView(RegularSeasonViewFixtures::noAttemptsRows(), null);

        // 9 games but zero attempts: computeCareerAveragesFromHistory() must take the
        // `: 0.0` side of each `$fga > 0 ? round($fgm / $fga, 3) : 0.0` guard. Relaxing
        // any of those guards to `>=` evaluates 0 / 0 and throws DivisionByZeroError,
        // so simply rendering without error is the assertion.
        $html = $view->renderAverages(42);

        $this->assertStringContainsString('>Career<', $html);
    }

    public function testZeroStoredPointsSeasonAverageFallsBackToMadeShotFormula(): void
    {
        $history = [
            [
                'year' => 2034, 'team' => 'MIA', 'teamid' => 15,
                'pid' => 42, 'name' => 'Test Player',
                'games' => 10, 'minutes' => 300,
                'fgm' => 50, 'fga' => 100, 'ftm' => 20, 'fta' => 30,
                'tgm' => 10, 'tga' => 20,
                'orb' => 0, 'reb' => 0, 'ast' => 0, 'stl' => 0,
                'tvr' => 0, 'blk' => 0, 'pf' => 0, 'pts' => 0,
            ],
        ];
        $view = $this->makeView($history, TournamentViewFixtures::careerAveragesRow());

        $html = $view->renderAverages(42);

        // (2 * 50 + 20 + 10) / 10 games = 13.0 per game from the made-shot formula.
        $this->assertStringContainsString('<td>13.0</td>', $html);
        $this->assertSame(1, substr_count($html, '<td>13.0</td>'));
    }

    public function testRenderReturnsEmptyStringBecauseItNeedsContext(): void
    {
        $view = $this->makeView([], null);

        $this->assertSame('', $view->render());
    }
}
