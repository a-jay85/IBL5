<?php

declare(strict_types=1);

namespace Tests\SeriesRecords;

use PHPUnit\Framework\TestCase;
use SeriesRecords\SeriesRecordsService;
use SeriesRecords\SeriesRecordsView;

/**
 * Tests for SeriesRecordsView
 * 
 * @covers \SeriesRecords\SeriesRecordsView
 */
class SeriesRecordsViewTest extends TestCase
{
    private SeriesRecordsView $view;
    private SeriesRecordsService $service;

    protected function setUp(): void
    {
        $this->service = new SeriesRecordsService();
        $this->view = new SeriesRecordsView($this->service);
    }

    // =========================================================================
    // renderHeaderCell Tests
    // =========================================================================

    public function testRenderHeaderCellRendersImageTag(): void
    {
        $result = $this->view->renderHeaderCell(1);

        $this->assertStringContainsString('<th', $result);
        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('new1.png', $result);
        $this->assertStringContainsString('width="50"', $result);
        $this->assertStringContainsString('height="50"', $result);
    }

    public function testRenderHeaderCellUsesTeamIdInImagePath(): void
    {
        $result = $this->view->renderHeaderCell(25);

        $this->assertStringContainsString('new25.png', $result);
    }

    // =========================================================================
    // renderTeamNameCell Tests
    // =========================================================================

    public function testRenderTeamNameCellRendersTeamInfo(): void
    {
        $team = [
            'teamid' => 5,
            'team_city' => 'Chicago',
            'team_name' => 'Bulls',
            'color1' => '#FF0000',
            'color2' => '#FFFFFF',
        ];

        $result = $this->view->renderTeamNameCell($team, false);

        $this->assertStringContainsString('<td', $result);
        $this->assertStringContainsString('Bulls', $result);
        $this->assertStringContainsString('teamID=5', $result);
        $this->assertStringNotContainsString('<strong>', $result);
    }

    public function testRenderTeamNameCellBoldsUserTeam(): void
    {
        $team = [
            'teamid' => 5,
            'team_city' => 'Chicago',
            'team_name' => 'Bulls',
            'color1' => '#FF0000',
            'color2' => '#FFFFFF',
        ];

        $result = $this->view->renderTeamNameCell($team, true);

        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('</strong>', $result);
    }

    public function testRenderTeamNameCellEscapesSpecialCharacters(): void
    {
        $team = [
            'teamid' => 5,
            'team_city' => 'O\'Fallon',
            'team_name' => 'Test<Team>',
            'color1' => '#FF0000',
            'color2' => '#FFFFFF',
        ];

        $result = $this->view->renderTeamNameCell($team, false);

        // City is no longer displayed; team name should be HTML escaped
        $this->assertStringContainsString('Test&lt;Team&gt;', $result);
    }

    // =========================================================================
    // renderRecordCell Tests
    // =========================================================================

    public function testRenderRecordCellRendersWinLossFormat(): void
    {
        $result = $this->view->renderRecordCell(10, 5, '#8f8', false);

        $this->assertStringContainsString('<td', $result);
        $this->assertStringContainsString('10 - 5', $result);
        $this->assertStringContainsString('background-color: #8f8', $result);
    }

    public function testRenderRecordCellBoldsWhenRequired(): void
    {
        $result = $this->view->renderRecordCell(10, 5, '#8f8', true);

        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringContainsString('</strong>', $result);
    }

    public function testRenderRecordCellDoesNotBoldWhenNotRequired(): void
    {
        $result = $this->view->renderRecordCell(10, 5, '#8f8', false);

        $this->assertStringNotContainsString('<strong>', $result);
    }

    // =========================================================================
    // renderDiagonalCell Tests
    // =========================================================================

    public function testRenderDiagonalCellRendersX(): void
    {
        $result = $this->view->renderDiagonalCell(false);

        $this->assertStringContainsString('<td', $result);
        $this->assertStringContainsString('x', $result);
        $this->assertStringNotContainsString('<strong>', $result);
    }

    public function testRenderDiagonalCellBoldsForUserTeam(): void
    {
        $result = $this->view->renderDiagonalCell(true);

        $this->assertStringContainsString('<strong>x</strong>', $result);
    }

    // =========================================================================
    // renderSeriesRecordsTable Tests
    // =========================================================================

    public function testRenderSeriesRecordsTableRendersCompleteTable(): void
    {
        $teams = [
            ['teamid' => 1, 'team_city' => 'Boston', 'team_name' => 'Celtics', 'color1' => '#007A33', 'color2' => '#FFFFFF'],
            ['teamid' => 2, 'team_city' => 'Los Angeles', 'team_name' => 'Lakers', 'color1' => '#552583', 'color2' => '#FDB927'],
        ];

        $seriesMatrix = [
            1 => [2 => ['wins' => 10, 'losses' => 8]],
            2 => [1 => ['wins' => 8, 'losses' => 10]],
        ];

        $result = $this->view->renderSeriesRecordsTable($teams, $seriesMatrix, 0, 2);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('</table>', $result);
        $this->assertStringContainsString('sortable', $result);
        $this->assertStringContainsString('ibl-data-table', $result);
        $this->assertStringContainsString('Celtics', $result);
        $this->assertStringContainsString('Lakers', $result);
    }

    public function testRenderSeriesRecordsTableIncludesHeaderLogos(): void
    {
        $teams = [
            ['teamid' => 1, 'team_city' => 'Boston', 'team_name' => 'Celtics', 'color1' => '#007A33', 'color2' => '#FFFFFF'],
        ];

        $result = $this->view->renderSeriesRecordsTable($teams, [], 0, 1);

        $this->assertStringContainsString('new1.png', $result);
    }

    public function testRenderSeriesRecordsTableIncludesDiagonalCells(): void
    {
        $teams = [
            ['teamid' => 1, 'team_city' => 'Boston', 'team_name' => 'Celtics', 'color1' => '#007A33', 'color2' => '#FFFFFF'],
        ];

        $result = $this->view->renderSeriesRecordsTable($teams, [], 0, 1);

        // Should have 'x' for diagonal
        $this->assertStringContainsString('>x<', $result);
    }

    public function testRenderSeriesRecordsTableHighlightsUserTeam(): void
    {
        $teams = [
            ['teamid' => 1, 'team_city' => 'Boston', 'team_name' => 'Celtics', 'color1' => '#007A33', 'color2' => '#FFFFFF'],
        ];

        $result = $this->view->renderSeriesRecordsTable($teams, [], 1, 1);

        // User team should be bold
        $this->assertStringContainsString('<strong>', $result);
    }
}
