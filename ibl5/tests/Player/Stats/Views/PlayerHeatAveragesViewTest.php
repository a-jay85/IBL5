<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStatsRepository;
use Player\Stats\Views\PlayerHeatAveragesView;
use Player\Stats\Views\PlayerSeasonTableRenderer;

/**
 * @covers \Player\Stats\Views\PlayerHeatAveragesView
 */
class PlayerHeatAveragesViewTest extends TestCase
{
    use SnapshotTestTrait;

    public function testRenderAveragesMatchesSnapshot(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getHeatStats')->willReturn(TournamentViewFixtures::twoSeasonRows());
        $repository->method('getHeatCareerAverages')->willReturn(TournamentViewFixtures::careerAveragesRow());

        $view = new PlayerHeatAveragesView($repository, new PlayerSeasonTableRenderer());
        $html = $view->renderAverages('Test Player');

        $this->assertSnapshotMatches($html, 'PlayerHeatAveragesView.html');
    }

    public function testRenderAveragesWithNoCareerRow(): void
    {
        $repository = self::createStub(PlayerStatsRepository::class);
        $repository->method('getHeatStats')->willReturn(TournamentViewFixtures::twoSeasonRows());
        $repository->method('getHeatCareerAverages')->willReturn(null);

        $view = new PlayerHeatAveragesView($repository, new PlayerSeasonTableRenderer());
        $html = $view->renderAverages('Test Player');

        $this->assertSnapshotMatches($html, 'PlayerHeatAveragesView_noCareer.html');
    }
}
