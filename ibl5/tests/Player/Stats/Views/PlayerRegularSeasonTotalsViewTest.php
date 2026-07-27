<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStatsRepository;
use Player\Stats\Views\PlayerRegularSeasonTotalsView;

/** @covers \Player\Stats\Views\PlayerRegularSeasonTotalsView */
class PlayerRegularSeasonTotalsViewTest extends TestCase
{
    use SnapshotTestTrait;

    public function testRenderTotalsMatchesSnapshot(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getHistoricalStats')
            ->willReturn(RegularSeasonViewFixtures::seasonRows());

        $view = new PlayerRegularSeasonTotalsView($repository);
        $html = $view->renderTotals(42);

        $this->assertSnapshotMatches($html, 'PlayerRegularSeasonTotalsView.html');
    }

    public function testCareerPointsAreRecomputedFromMadeShotsNotSummedFromRows(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getHistoricalStats')
            ->willReturn(RegularSeasonViewFixtures::seasonRows());

        $view = new PlayerRegularSeasonTotalsView($repository);
        $html = $view->renderTotals(42);

        // (2 * 899) + 465 + 198 = 2461 -- the recomputed career total.
        $this->assertStringContainsString('2461', $html);
        // 795 + 1177 + 509 = 2481 -- the row sum. It must NEVER be rendered.
        $this->assertStringNotContainsString('2481', $html);
    }

    public function testZeroStoredPointsFallsBackToMadeShotFormula(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getHistoricalStats')
            ->willReturn(RegularSeasonViewFixtures::seasonRows());

        $view = new PlayerRegularSeasonTotalsView($repository);
        $html = $view->renderTotals(42);

        // Season 2031 stores pts => 0; (2 * 187) + 94 + 41 = 509 is rendered instead.
        $this->assertStringContainsString('509', $html);
    }

    public function testEmptyHistoryStillRendersZeroedCareerRow(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getHistoricalStats')->willReturn([]);

        $view = new PlayerRegularSeasonTotalsView($repository);
        $html = $view->renderTotals(42);

        $this->assertStringContainsString('Career', $html);
        $this->assertStringNotContainsString('2461', $html);
        $this->assertSnapshotMatches($html, 'PlayerRegularSeasonTotalsView-empty.html');
    }

    public function testRenderReturnsEmptyStringBecauseItNeedsContext(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);

        $view = new PlayerRegularSeasonTotalsView($repository);

        $this->assertSame('', $view->render());
    }
}
