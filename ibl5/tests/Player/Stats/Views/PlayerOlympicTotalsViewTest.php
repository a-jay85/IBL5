<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStatsRepository;
use Player\Stats\Views\PlayerOlympicTotalsView;
use Player\Stats\Views\PlayerSeasonTableRenderer;

/**
 * @covers \Player\Stats\Views\PlayerOlympicTotalsView
 */
class PlayerOlympicTotalsViewTest extends TestCase
{
    use SnapshotTestTrait;

    public function testRenderTotalsMatchesSnapshot(): void
    {
        $repository = $this->createStub(PlayerStatsRepository::class);
        $repository->method('getOlympicsStats')->willReturn(TournamentViewFixtures::twoSeasonRows());

        $view = new PlayerOlympicTotalsView($repository, new PlayerSeasonTableRenderer());
        $html = $view->renderTotals(100);

        $this->assertSnapshotMatches($html, 'PlayerOlympicTotalsView.html');
    }
}
