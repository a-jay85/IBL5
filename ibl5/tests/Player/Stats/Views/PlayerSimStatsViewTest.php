<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStatsRepository;
use Player\Stats\Views\PlayerSimStatsView;

/** @covers \Player\Stats\Views\PlayerSimStatsView */
class PlayerSimStatsViewTest extends TestCase
{
    use SnapshotTestTrait;

    private function makeView(): PlayerSimStatsView
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getSimDates')
            ->willReturn(RegularSeasonViewFixtures::simDates());
        $repository->method('getBoxScoresBetweenDates')->willReturnMap([
            [42, '2030-01-05', '2030-01-11', RegularSeasonViewFixtures::boxScoresForSim12()],
            [42, '2030-01-12', '2030-01-18', []],
            [42, '2030-01-19', '2030-01-25', RegularSeasonViewFixtures::boxScoresForSim14()],
        ]);

        return new PlayerSimStatsView($repository);
    }

    public function testRenderSimStatsMatchesSnapshot(): void
    {
        $html = $this->makeView()->renderSimStats(42);

        $this->assertSnapshotMatches($html, 'PlayerSimStatsView.html');
    }

    public function testEmptySimIsSkippedButLaterSimsStillRender(): void
    {
        $html = $this->makeView()->renderSimStats(42);

        $this->assertStringContainsString('<td>12</td>', $html);
        // Sim 13 has no box scores -> `continue`.
        $this->assertStringNotContainsString('<td>13</td>', $html);
        // Sim 14 comes AFTER the skipped sim. If `continue` became `break`, this fails.
        $this->assertStringContainsString('<td>14</td>', $html);
    }

    public function testPerGameAveragesDivideByThatSimsGameCount(): void
    {
        $html = $this->makeView()->renderSimStats(42);

        // Sim 12: 2 games -> (34 + 29) / 2 = 31.5 minutes, 50 / 2 = 25.0 points.
        $this->assertStringContainsString('<td>31.5</td>', $html);
        $this->assertStringContainsString('<td>25.0</td>', $html);
        // Sim 14: 1 game -> 41.0 minutes, (2*9) + (3*4) + 6 = 36 -> 36.0 points.
        $this->assertStringContainsString('<td>41.0</td>', $html);
        $this->assertStringContainsString('<td>36.0</td>', $html);
    }

    public function testAllSimsEmptyRendersHeaderOnlyTable(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getSimDates')
            ->willReturn(RegularSeasonViewFixtures::simDates());
        $repository->method('getBoxScoresBetweenDates')->willReturn([]);

        $view = new PlayerSimStatsView($repository);
        $html = $view->renderSimStats(42);

        $this->assertStringContainsString('Sim Averages', $html);
        $this->assertStringNotContainsString('<td>12</td>', $html);
        $this->assertStringNotContainsString('<td>14</td>', $html);
        $this->assertSnapshotMatches($html, 'PlayerSimStatsView-nogames.html');
    }

    public function testNoSimDatesRendersHeaderOnlyTable(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getSimDates')->willReturn([]);

        $view = new PlayerSimStatsView($repository);
        $html = $view->renderSimStats(42);

        $this->assertStringContainsString('Sim Averages', $html);
    }

    public function testRenderReturnsEmptyStringBecauseItNeedsContext(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);

        $view = new PlayerSimStatsView($repository);

        $this->assertSame('', $view->render());
    }
}
