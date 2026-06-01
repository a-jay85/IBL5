<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStatsRepository;
use Player\Stats\Views\PlayerPlayoffTotalsView;
use Player\Stats\Views\PlayerSeasonTableRenderer;

/**
 * @covers \Player\Stats\Views\PlayerPlayoffTotalsView
 */
class PlayerPlayoffTotalsViewTest extends TestCase
{
    use SnapshotTestTrait;

    public function testRenderTotalsMatchesSnapshot(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getPlayoffStats')->willReturn(TournamentViewFixtures::twoSeasonRows());

        $view = new PlayerPlayoffTotalsView($repository, new PlayerSeasonTableRenderer());
        $html = $view->renderTotals('Test Player');

        $this->assertSnapshotMatches($html, 'PlayerPlayoffTotalsView.html');
    }
}
