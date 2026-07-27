<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStatsRepository;
use Player\Stats\Views\PlayerRegularSeasonAveragesView;

/** @covers \Player\Stats\Views\PlayerRegularSeasonAveragesView */
class PlayerRegularSeasonAveragesViewTest extends TestCase
{
    use SnapshotTestTrait;

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

    public function testRenderReturnsEmptyStringBecauseItNeedsContext(): void
    {
        $view = $this->makeView([], null);

        $this->assertSame('', $view->render());
    }
}
