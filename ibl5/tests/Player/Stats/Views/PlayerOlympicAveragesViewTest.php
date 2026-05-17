<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\PlayerStatsRepository;
use Player\Stats\Views\PlayerOlympicAveragesView;
use Player\Stats\Views\PlayerSeasonTableRenderer;

/**
 * @covers \Player\Stats\Views\PlayerOlympicAveragesView
 */
class PlayerOlympicAveragesViewTest extends TestCase
{
    use SnapshotTestTrait;

    public function testRenderAveragesMatchesSnapshot(): void
    {
        $repository = $this->createStub(PlayerStatsRepository::class);
        $repository->method('getOlympicsStats')->willReturn(TournamentViewFixtures::twoSeasonRows());
        $repository->method('getOlympicsCareerAverages')->willReturn(TournamentViewFixtures::careerAveragesRow());

        $view = new PlayerOlympicAveragesView($repository, new PlayerSeasonTableRenderer());
        $html = $view->renderAverages(100);

        $this->assertSnapshotMatches($html, 'PlayerOlympicAveragesView.html');
    }

    public function testRenderAveragesWithNoCareerRow(): void
    {
        $repository = $this->createStub(PlayerStatsRepository::class);
        $repository->method('getOlympicsStats')->willReturn(TournamentViewFixtures::twoSeasonRows());
        $repository->method('getOlympicsCareerAverages')->willReturn(null);

        $view = new PlayerOlympicAveragesView($repository, new PlayerSeasonTableRenderer());
        $html = $view->renderAverages(100);

        $this->assertSnapshotMatches($html, 'PlayerOlympicAveragesView_noCareer.html');
    }
}
