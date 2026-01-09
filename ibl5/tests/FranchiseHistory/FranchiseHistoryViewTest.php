<?php

declare(strict_types=1);

namespace Tests\FranchiseHistory;

use PHPUnit\Framework\TestCase;
use FranchiseHistory\FranchiseHistoryView;
use FranchiseHistory\Contracts\FranchiseHistoryViewInterface;

/**
 * FranchiseHistoryViewTest - Tests for FranchiseHistoryView HTML rendering
 *
 * @covers \FranchiseHistory\FranchiseHistoryView
 */
class FranchiseHistoryViewTest extends TestCase
{
    private FranchiseHistoryViewInterface $view;

    protected function setUp(): void
    {
        $this->view = new FranchiseHistoryView();
    }

    public function testImplementsFranchiseHistoryViewInterface(): void
    {
        $this->assertInstanceOf(FranchiseHistoryViewInterface::class, $this->view);
    }

    public function testRenderReturnsString(): void
    {
        $result = $this->view->render([]);

        $this->assertIsString($result);
    }

    public function testRenderContainsTableStructure(): void
    {
        $result = $this->view->render([]);

        $this->assertStringContainsString('<table', $result);
        $this->assertStringContainsString('</table>', $result);
    }

    public function testRenderContainsTableHeaders(): void
    {
        $result = $this->view->render([]);

        $this->assertStringContainsString('Team', $result);
        $this->assertStringContainsString('Titles', $result);
    }

    public function testRenderContainsWinLossHeaders(): void
    {
        $result = $this->view->render([]);

        $this->assertStringContainsString('Win', $result);
        $this->assertStringContainsString('Loss', $result);
    }

    public function testRenderEscapesHtmlEntities(): void
    {
        $franchises = [
            [
                'teamid' => 1,
                'team_city' => 'Test<script>',
                'team_name' => 'Team&Name',
                'color1' => '000000',
                'color2' => 'FFFFFF',
                'wins' => 0,
                'losses' => 0,
                'five_season_wins' => 0,
                'five_season_losses' => 0,
                'five_season_winpct' => 0.0,
                'playoff_appearances' => 0,
                'heat_titles' => 0,
                'division_titles' => 0,
                'conference_titles' => 0,
                'ibl_titles' => 0,
            ],
        ];

        $result = $this->view->render($franchises);

        $this->assertStringNotContainsString('<script>', $result);
    }
}
