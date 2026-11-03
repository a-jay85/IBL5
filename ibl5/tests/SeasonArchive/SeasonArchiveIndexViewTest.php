<?php

declare(strict_types=1);

namespace Tests\SeasonArchive;

use PHPUnit\Framework\TestCase;
use SeasonArchive\SeasonArchiveIndexView;

/**
 * SeasonArchiveIndexViewTest - Tests for SeasonArchiveIndexView HTML rendering
 *
 * @covers \SeasonArchive\SeasonArchiveIndexView
 */
class SeasonArchiveIndexViewTest extends TestCase
{
    private SeasonArchiveIndexView $view;

    protected function setUp(): void
    {
        $this->view = new SeasonArchiveIndexView();
    }

    public function testRenderIndexContainsTableStructure(): void
    {
        $result = $this->view->renderIndex([]);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('</table>', $result);
        $this->assertStringContainsString('ibl-data-table', $result);
    }

    public function testRenderIndexContainsTitle(): void
    {
        $result = $this->view->renderIndex([]);

        $this->assertStringContainsString('IBL Season Archive', $result);
        $this->assertStringContainsString('ibl-title', $result);
    }

    public function testRenderIndexContainsTableHeaders(): void
    {
        $result = $this->view->renderIndex([]);

        $this->assertStringContainsString('Season', $result);
        $this->assertStringContainsString('IBL Champion', $result);
        $this->assertStringContainsString('HEAT Champion', $result);
        $this->assertStringContainsString('MVP', $result);
    }

    public function testRenderIndexContainsSeasonLinks(): void
    {
        $seasons = [
            ['year' => 1989, 'label' => 'Season I (1988-89)', 'iblChampion' => 'Clippers', 'heatChampion' => 'Rockets', 'mvp' => 'Arvydas Sabonis'],
        ];

        $result = $this->view->renderIndex($seasons);

        $this->assertStringContainsString('modules.php?name=SeasonArchive&amp;year=1989', $result);
        $this->assertStringContainsString('Season I (1988-89)', $result);
        $this->assertStringContainsString('Clippers', $result);
        $this->assertStringContainsString('Rockets', $result);
        $this->assertStringContainsString('Arvydas Sabonis', $result);
    }

    public function testRenderIndexEscapesHtmlEntities(): void
    {
        $seasons = [
            ['year' => 1989, 'label' => 'Season <script>alert(1)</script>', 'iblChampion' => 'Team&Name', 'heatChampion' => 'Test', 'mvp' => 'Test'],
        ];

        $result = $this->view->renderIndex($seasons);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&amp;Name', $result);
    }

    public function testRenderIndexUsesTeamCellsForChampions(): void
    {
        $seasons = [
            ['year' => 1989, 'label' => 'Season I (1988-89)', 'iblChampion' => 'Clippers', 'heatChampion' => 'Rockets', 'mvp' => 'Arvydas Sabonis'],
        ];
        $teamColors = [
            'Clippers' => ['color1' => 'C8102E', 'color2' => 'FFFFFF', 'teamid' => 5],
            'Rockets' => ['color1' => 'CE1141', 'color2' => '000000', 'teamid' => 12],
        ];

        $result = $this->view->renderIndex($seasons, $teamColors);

        $this->assertStringContainsString('ibl-team-cell--colored', $result);
        $this->assertStringContainsString('C8102E', $result);
        $this->assertStringContainsString('CE1141', $result);
        $this->assertStringContainsString('teamid=5', $result);
        $this->assertStringContainsString('teamid=12', $result);
        $this->assertStringContainsString('new5.png', $result);
        $this->assertStringContainsString('new12.png', $result);
    }

    public function testRenderIndexPrefersEraColorsForThatSeasonsRow(): void
    {
        $seasons = [
            ['year' => 1995, 'label' => 'Season VII (1994-95)', 'iblChampion' => 'Nets', 'heatChampion' => 'Supersonics', 'mvp' => ''],
            ['year' => 2003, 'label' => 'Season XV (2002-03)', 'iblChampion' => 'Nets', 'heatChampion' => 'Clippers', 'mvp' => ''],
        ];
        $teamColors = [
            'Nets' => ['color1' => 'AAAAAA', 'color2' => 'BBBBBB', 'teamid' => 4],
            'Clippers' => ['color1' => 'C8102E', 'color2' => 'FFFFFF', 'teamid' => 5],
        ];
        $teamColorsByYear = [
            1995 => [
                'Nets' => ['color1' => '000001', 'color2' => 'FFFFFE', 'teamid' => 4],
                'Supersonics' => ['color1' => '00653A', 'color2' => 'FFC200', 'teamid' => 22],
            ],
        ];

        $result = $this->view->renderIndex($seasons, $teamColors, [], [], $teamColorsByYear);

        $this->assertStringContainsString('00653A', $result);
        $this->assertStringContainsString('teamid=22', $result);
        $this->assertStringContainsString('000001', $result);
        // 2003 row has no era map, so Nets falls back to the current colors
        $this->assertStringContainsString('AAAAAA', $result);
    }

    public function testRenderIndexUsesPlayerCellForMvp(): void
    {
        $seasons = [
            ['year' => 1989, 'label' => 'Season I (1988-89)', 'iblChampion' => 'Clippers', 'heatChampion' => 'Rockets', 'mvp' => 'Arvydas Sabonis'],
        ];
        $playerIds = ['Arvydas Sabonis' => 100];

        $result = $this->view->renderIndex($seasons, [], $playerIds);

        $this->assertStringContainsString('ibl-player-cell', $result);
        $this->assertStringContainsString('pid=100', $result);
        $this->assertStringContainsString('Arvydas Sabonis', $result);
    }

    public function testRenderIndexOmitsInlineStylesAfterCssCentralization(): void
    {
        $seasons = [
            ['year' => 1989, 'label' => 'Season I (1988-89)', 'iblChampion' => 'Clippers', 'heatChampion' => 'Rockets', 'mvp' => 'Arvydas Sabonis'],
        ];
        $teamColors = [
            'Clippers' => ['color1' => 'C8102E', 'color2' => 'FFFFFF', 'teamid' => 5],
        ];

        $result = $this->view->renderIndex($seasons, $teamColors);

        // CSS is now centralized in design/components/season-archive.css
        $this->assertStringNotContainsString('<style>', $result);
    }

    public function testRenderIndexWithoutEnrichmentDataOmitsStyles(): void
    {
        $result = $this->view->renderIndex([]);

        $this->assertStringNotContainsString('<style>', $result);
    }
}
