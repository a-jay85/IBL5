<?php

declare(strict_types=1);

namespace Tests\Player\Stats\Views;

use PHPUnit\Framework\TestCase;
use Player\Stats\Views\PlayerSeasonTableConfig;
use Player\Stats\Views\PlayerSeasonTableMode;
use Player\Stats\Views\PlayerSeasonTableRenderer;
use Tests\Support\GoldenSnapshotTrait;

/**
 * @covers \Player\Stats\Views\PlayerSeasonTableRenderer
 */
class PlayerSeasonTableRendererTest extends TestCase
{
    use GoldenSnapshotTrait;

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

    /**
     * @param array<string, int|string> $overrides
     * @return array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int, teamid: int}
     */
    private function makeSeasonRow(array $overrides = []): array
    {
        /** @var array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int, teamid: int} $row */
        $row = array_merge([
            'year' => 2001, 'pos' => 'G', 'pid' => 1, 'name' => 'Test', 'team' => 'Bulls', 'teamid' => 7,
            'games' => 0, 'minutes' => 0, 'fgm' => 0, 'fga' => 0, 'ftm' => 0, 'fta' => 0,
            'tgm' => 0, 'tga' => 0, 'orb' => 0, 'reb' => 0, 'ast' => 0, 'stl' => 0,
            'tvr' => 0, 'blk' => 0, 'pf' => 0, 'pts' => 0,
        ], $overrides);

        return $row;
    }

    private function detailedConfig(): PlayerSeasonTableConfig
    {
        return new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::DETAILED_AVERAGES,
            title: 'Detailed Test',
            careerLabel: 'Career',
        );
    }

    public function testDetailedAveragesUsesColspan21AndShootingColumns(): void
    {
        $html = $this->renderer->render($this->detailedConfig(), [$this->makeSeasonRow()]);

        $this->assertStringContainsString('colspan=21 class="player-view-table__title"', $html);
        $this->assertStringContainsString('<th>fgm</th>', $html);
        $this->assertStringContainsString('<th>3gp</th>', $html);
    }

    public function testDetailedAveragesUsesStoredRowPoints(): void
    {
        $row = $this->makeSeasonRow([
            'games' => 1, 'minutes' => 30, 'fgm' => 5, 'fga' => 10, 'ftm' => 2, 'fta' => 3,
            'tgm' => 1, 'tga' => 2, 'orb' => 4, 'reb' => 6, 'ast' => 7, 'stl' => 8,
            'tvr' => 9, 'blk' => 11, 'pf' => 12, 'pts' => 25,
        ]);

        $html = $this->renderer->render($this->detailedConfig(), [$row]);

        $this->assertStringContainsString('<td>25.0</td>', $html);
        $this->assertStringNotContainsString('<td>13.0</td>', $html);
        $expectedRow = implode("\n", [
            '        <td>2001</td>',
            '        <td>Bulls</td>',
            '        <td>1</td>',
            '        <td>30.0</td>',
            '        <td>5.0</td>',
            '        <td>10.0</td>',
            '        <td>0.500</td>',
            '        <td>2.0</td>',
            '        <td>3.0</td>',
            '        <td>0.667</td>',
            '        <td>1.0</td>',
            '        <td>2.0</td>',
            '        <td>0.500</td>',
            '        <td>4.0</td>',
            '        <td>6.0</td>',
            '        <td>7.0</td>',
            '        <td>8.0</td>',
            '        <td>9.0</td>',
            '        <td>11.0</td>',
            '        <td>12.0</td>',
            '        <td>25.0</td>',
        ]);
        $this->assertStringContainsString($expectedRow, $html);
    }

    public function testDetailedAveragesZeroGamesHidesPercentages(): void
    {
        $row = $this->makeSeasonRow([
            'games' => 0, 'minutes' => 5, 'fgm' => 5, 'fga' => 10, 'ftm' => 5, 'fta' => 5,
            'tgm' => 5, 'tga' => 5, 'orb' => 5, 'reb' => 5, 'ast' => 5, 'stl' => 5,
            'tvr' => 5, 'blk' => 5, 'pf' => 5, 'pts' => 5,
        ]);

        $html = $this->renderer->render($this->detailedConfig(), [$row]);

        $this->assertStringNotContainsString('50', $html);
        // 21 columns minus year, team and g (which prints a bare integer).
        $this->assertSame(18, substr_count($html, '<td>0.0</td>'));
    }

    public function testDetailedAveragesWithoutCareerOmitsTfoot(): void
    {
        $html = $this->renderer->render($this->detailedConfig(), [$this->makeSeasonRow(['games' => 2])], null);

        $this->assertStringNotContainsString('<tfoot>', $html);
    }

    public function testDetailedAveragesCareerRowShowsShootingAverages(): void
    {
        $career = [
            'pid' => 1, 'name' => 'Test', 'games' => 82, 'minutes' => 30.0,
            'fgm' => 5.2, 'fga' => 10.4, 'fgpct' => 0.5, 'ftm' => 2.1, 'fta' => 3.3, 'ftpct' => 0.636,
            'tgm' => 1.4, 'tga' => 2.8, 'tpct' => 0.5, 'orb' => 1.1, 'reb' => 4.2, 'ast' => 3.7,
            'stl' => 1.3, 'tvr' => 2.2, 'blk' => 0.6, 'pf' => 2.9, 'pts' => 14.9, 'retired' => 0,
        ];

        $html = $this->renderer->render($this->detailedConfig(), [$this->makeSeasonRow(['games' => 2])], $career);

        $this->assertStringContainsString('<td>10.4</td>', $html);
        $this->assertStringContainsString('<td colspan=2>Career</td>', $html);
        $expectedTfoot = implode("\n", [
            '<tfoot>',
            '    <tr class="career-row">',
            '        <td colspan=2>Career</td>',
            '        <td>82</td>',
            '        <td>30.0</td>',
            '        <td>5.2</td>',
            '        <td>10.4</td>',
            '        <td>0.500</td>',
            '        <td>2.1</td>',
            '        <td>3.3</td>',
            '        <td>0.636</td>',
            '        <td>1.4</td>',
            '        <td>2.8</td>',
            '        <td>0.500</td>',
            '        <td>1.1</td>',
            '        <td>4.2</td>',
            '        <td>3.7</td>',
            '        <td>1.3</td>',
            '        <td>2.2</td>',
            '        <td>0.6</td>',
            '        <td>2.9</td>',
            '        <td>14.9</td>',
            '    </tr>',
            '</tfoot>',
        ]);
        $this->assertStringContainsString($expectedTfoot, $html);
    }

    public function testTeamCellLinksToTeamSeasonWhenEnabled(): void
    {
        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::TOTALS,
            title: 'Test',
            careerLabel: 'Career',
            linkTeamToSeason: true,
        );

        $html = $this->renderer->render($config, [$this->makeSeasonRow()]);

        $this->assertStringContainsString(
            '<td><a href="modules.php?name=Team&op=team&teamid=7&yr=2001">Bulls</a></td>',
            $html
        );
    }

    public function testTeamCellIsPlainTextByDefault(): void
    {
        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::TOTALS,
            title: 'Test',
            careerLabel: 'Career',
        );

        $html = $this->renderer->render($config, [$this->makeSeasonRow()]);

        $this->assertStringContainsString('<td>Bulls</td>', $html);
        $this->assertStringNotContainsString('<a href', $html);
    }

    public function testTeamLinkEscapesTeamName(): void
    {
        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::TOTALS,
            title: 'Test',
            careerLabel: 'Career',
            linkTeamToSeason: true,
        );

        $html = $this->renderer->render($config, [$this->makeSeasonRow(['team' => 'A&B'])]);

        $this->assertStringContainsString('>A&amp;B</a>', $html);
        $this->assertStringNotContainsString('>A&B</a>', $html);
    }

    /**
     * @return list<array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int, teamid: int}>
     */
    private function careerPointsRows(): array
    {
        return [
            $this->makeSeasonRow([
                'year' => 2001, 'games' => 10, 'minutes' => 250, 'fgm' => 40, 'fga' => 90,
                'ftm' => 10, 'fta' => 12, 'tgm' => 5, 'tga' => 15, 'pts' => 100,
            ]),
            $this->makeSeasonRow([
                'year' => 2002, 'games' => 10, 'minutes' => 250, 'fgm' => 40, 'fga' => 90,
                'ftm' => 10, 'fta' => 12, 'tgm' => 5, 'tga' => 15, 'pts' => 200,
            ]),
        ];
    }

    public function testTotalsCareerPointsFromMadeShotsIgnoresRowPoints(): void
    {
        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::TOTALS,
            title: 'Test',
            careerLabel: 'Career',
            careerPointsFromMadeShots: true,
        );

        $html = $this->renderer->render($config, $this->careerPointsRows());

        // 2 * 80 + 20 + 10 = 190; summing the stored row points would give 300.
        $this->assertStringContainsString('<td>190</td>', $html);
        $this->assertStringNotContainsString('<td>300</td>', $html);
    }

    public function testTotalsCareerPointsSummedFromRowsByDefault(): void
    {
        $config = new PlayerSeasonTableConfig(
            mode: PlayerSeasonTableMode::TOTALS,
            title: 'Test',
            careerLabel: 'Career',
        );

        $html = $this->renderer->render($config, $this->careerPointsRows());

        $this->assertStringContainsString('<td>300</td>', $html);
        $this->assertStringNotContainsString('<td>190</td>', $html);
    }
}
