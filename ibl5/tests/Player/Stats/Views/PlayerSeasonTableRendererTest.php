<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\Views\PlayerSeasonTableConfig;
use Player\Stats\Views\PlayerSeasonTableMode;
use Player\Stats\Views\PlayerSeasonTableRenderer;

/**
 * @covers \Player\Stats\Views\PlayerSeasonTableRenderer
 */
class PlayerSeasonTableRendererTest extends TestCase
{
    use SnapshotTestTrait;

    private PlayerSeasonTableRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new PlayerSeasonTableRenderer();
    }

    public function testRenderAveragesWithCareer(): void
    {
        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::AVERAGES,
            title: 'H.E.A.T. Averages',
            careerLabel: 'H.E.A.T. Career',
        );

        $html = $this->renderer->render($config, TournamentViewFixtures::twoSeasonRows(), TournamentViewFixtures::careerAveragesRow());

        $this->assertSnapshotMatches($html, 'PlayerHeatAveragesView.html');
    }

    public function testRenderAveragesWithoutCareer(): void
    {
        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::AVERAGES,
            title: 'H.E.A.T. Averages',
            careerLabel: 'H.E.A.T. Career',
        );

        $html = $this->renderer->render($config, TournamentViewFixtures::twoSeasonRows());

        $this->assertSnapshotMatches($html, 'PlayerHeatAveragesView_noCareer.html');
    }

    public function testRenderTotalsWithoutRecalculatePoints(): void
    {
        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::TOTALS,
            title: 'H.E.A.T. Totals',
            careerLabel: 'H.E.A.T. Totals',
        );

        $html = $this->renderer->render($config, TournamentViewFixtures::twoSeasonRows());

        $this->assertSnapshotMatches($html, 'PlayerHeatTotalsView.html');
    }

    public function testRenderTotalsWithRecalculatePoints(): void
    {
        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::TOTALS,
            title: 'Olympics Totals',
            careerLabel: 'Olympics Totals',
            recalculatePoints: true,
        );

        $html = $this->renderer->render($config, TournamentViewFixtures::twoSeasonRows());

        $this->assertSnapshotMatches($html, 'PlayerOlympicTotalsView.html');
    }

    public function testAveragesUsesColspan16(): void
    {
        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::AVERAGES,
            title: 'Test',
            careerLabel: 'Career',
        );

        $html = $this->renderer->render($config, TournamentViewFixtures::twoSeasonRows());

        $this->assertStringContainsString('colspan=16', $html);
        $this->assertStringNotContainsString('colspan=15', $html);
    }

    public function testTotalsUsesColspan15(): void
    {
        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::TOTALS,
            title: 'Test',
            careerLabel: 'Career',
        );

        $html = $this->renderer->render($config, TournamentViewFixtures::twoSeasonRows());

        $this->assertStringContainsString('colspan=15', $html);
        $this->assertStringNotContainsString('colspan=16', $html);
    }

    public function testAveragesZeroGamesShowsZero(): void
    {
        $row = TournamentViewFixtures::twoSeasonRows()[0];
        $row['games'] = 0;

        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::AVERAGES,
            title: 'Test',
            careerLabel: 'Career',
        );

        $html = $this->renderer->render($config, [$row]);

        $this->assertStringContainsString('<td>0.0</td>', $html);
    }
}
