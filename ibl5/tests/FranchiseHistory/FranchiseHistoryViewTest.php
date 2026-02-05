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
                'totwins' => 0,
                'totloss' => 0,
                'winpct' => 0.0,
                'five_season_wins' => 0,
                'five_season_losses' => 0,
                'five_season_winpct' => 0.0,
                'playoff_appearances' => 0,
                'playoffs' => 0,
                'playoff_total_wins' => 0,
                'playoff_total_losses' => 0,
                'playoff_winpct' => '.000',
                'heat_total_wins' => 0,
                'heat_total_losses' => 0,
                'heat_winpct' => '0.000',
                'heat_titles' => 0,
                'division_titles' => 0,
                'div_titles' => 0,
                'conference_titles' => 0,
                'conf_titles' => 0,
                'ibl_titles' => 0,
            ],
        ];

        $result = $this->view->render($franchises);

        // Should escape HTML entities - verify the escaped versions appear
        $this->assertStringContainsString('Team&amp;Name', $result);
        // City is no longer displayed, so only team name escaping matters
        // Should NOT contain the raw dangerous characters
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testRenderDisplaysTitleCounts(): void
    {
        $franchises = [
            [
                'teamid' => 1,
                'team_city' => 'Los Angeles',
                'team_name' => 'Lakers',
                'color1' => '552583',
                'color2' => 'FDB927',
                'totwins' => 100,
                'totloss' => 50,
                'winpct' => 0.667,
                'five_season_wins' => 50,
                'five_season_losses' => 25,
                'five_season_winpct' => 0.667,
                'playoffs' => 10,
                'playoff_total_wins' => 32,
                'playoff_total_losses' => 20,
                'playoff_winpct' => '0.615',
                'heat_total_wins' => 80,
                'heat_total_losses' => 40,
                'heat_winpct' => '0.667',
                'heat_titles' => 2,
                'div_titles' => 3,
                'conf_titles' => 4,
                'ibl_titles' => 1,
            ],
        ];

        $result = $this->view->render($franchises);

        // Verify playoff record is rendered
        $this->assertStringContainsString('32-20 (0.615)', $result, 'Playoff record should be displayed');

        // Verify all title types are rendered
        $this->assertStringContainsString('>2<', $result, 'HEAT titles should be displayed');
        $this->assertStringContainsString('>3<', $result, 'Division titles should be displayed');
        $this->assertStringContainsString('>4<', $result, 'Conference titles should be displayed');
        $this->assertStringContainsString('>1<', $result, 'IBL titles should be displayed');
    }

    public function testRenderHandlesZeroTitles(): void
    {
        $franchises = [
            [
                'teamid' => 2,
                'team_city' => 'Charlotte',
                'team_name' => 'Bobcats',
                'color1' => '000000',
                'color2' => 'FFFFFF',
                'totwins' => 20,
                'totloss' => 60,
                'winpct' => 0.250,
                'five_season_wins' => 10,
                'five_season_losses' => 30,
                'five_season_winpct' => 0.250,
                'playoffs' => 0,
                'playoff_total_wins' => 0,
                'playoff_total_losses' => 0,
                'playoff_winpct' => '.000',
                'heat_total_wins' => 5,
                'heat_total_losses' => 15,
                'heat_winpct' => '0.250',
                'heat_titles' => 0,
                'div_titles' => 0,
                'conf_titles' => 0,
                'ibl_titles' => 0,
            ],
        ];

        $result = $this->view->render($franchises);

        // Verify zeros are displayed (not empty cells)
        // The HTML should contain '>0<' for each title type
        $zeroCount = substr_count($result, '>0<');
        $this->assertGreaterThanOrEqual(4, $zeroCount, 'All four title types should display zero');
    }
}
