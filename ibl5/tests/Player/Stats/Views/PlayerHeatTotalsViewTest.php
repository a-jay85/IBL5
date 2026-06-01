<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStatsRepository;
use Player\Stats\Views\PlayerHeatTotalsView;
use Player\Stats\Views\PlayerSeasonTableRenderer;

/**
 * @covers \Player\Stats\Views\PlayerHeatTotalsView
 */
class PlayerHeatTotalsViewTest extends TestCase
{
    use SnapshotTestTrait;

    public function testRenderTotalsMatchesSnapshot(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getHeatStats')->willReturn(TournamentViewFixtures::twoSeasonRows());

        $view = new PlayerHeatTotalsView($repository, new PlayerSeasonTableRenderer());
        $html = $view->renderTotals('Test Player');

        $this->assertSnapshotMatches($html, 'PlayerHeatTotalsView.html');
    }
}
