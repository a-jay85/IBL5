<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStatsRepository;
use Player\Stats\Views\PlayerPlayoffAveragesView;
use Player\Stats\Views\PlayerSeasonTableRenderer;

/**
 * @covers \Player\Stats\Views\PlayerPlayoffAveragesView
 */
class PlayerPlayoffAveragesViewTest extends TestCase
{
    use SnapshotTestTrait;

    public function testRenderAveragesMatchesSnapshot(): void
    {
        $repository = $this->createStub(PlayerStatsRepository::class);
        $repository->method('getPlayoffStats')->willReturn(TournamentViewFixtures::twoSeasonRows());
        $repository->method('getPlayoffCareerAverages')->willReturn(TournamentViewFixtures::careerAveragesRow());

        $view = new PlayerPlayoffAveragesView($repository, new PlayerSeasonTableRenderer());
        $html = $view->renderAverages('Test Player');

        $this->assertSnapshotMatches($html, 'PlayerPlayoffAveragesView.html');
    }

    public function testRenderAveragesWithNoCareerRow(): void
    {
        $repository = $this->createStub(PlayerStatsRepository::class);
        $repository->method('getPlayoffStats')->willReturn(TournamentViewFixtures::twoSeasonRows());
        $repository->method('getPlayoffCareerAverages')->willReturn(null);

        $view = new PlayerPlayoffAveragesView($repository, new PlayerSeasonTableRenderer());
        $html = $view->renderAverages('Test Player');

        $this->assertSnapshotMatches($html, 'PlayerPlayoffAveragesView_noCareer.html');
    }
}
