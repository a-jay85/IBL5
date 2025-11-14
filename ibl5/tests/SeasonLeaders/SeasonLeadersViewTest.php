<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SeasonLeaders\SeasonLeadersView;
use SeasonLeaders\SeasonLeadersService;

final class SeasonLeadersViewTest extends TestCase
{
    private SeasonLeadersView $view;
    private SeasonLeadersService $service;

    protected function setUp(): void
    {
        $this->service = new SeasonLeadersService();
        $this->view = new SeasonLeadersView($this->service);
    }

    public function testRenderTableHeaderContainsAllColumns(): void
    {
        $html = $this->view->renderTableHeader();

        // Check for essential column headers
        $this->assertStringContainsString('<b>Rank</b>', $html);
        $this->assertStringContainsString('<b>Year</b>', $html);
        $this->assertStringContainsString('<b>Name</b>', $html);
        $this->assertStringContainsString('<b>Team</b>', $html);
        $this->assertStringContainsString('<b>ppg</b>', $html);
        $this->assertStringContainsString('<b>qa</b>', $html);
        $this->assertStringContainsString('bgcolor="C2D69A"', $html);
    }

    public function testRenderPlayerRowFormatsCorrectly(): void
    {
        $stats = [
            'pid' => 123,
            'name' => 'Test Player',
            'year' => 2024,
            'teamname' => 'Test Team',
            'teamid' => 1,
            'games' => 10,
            'mpg' => '30.0',
            'fgmpg' => '5.0',
            'fgapg' => '10.0',
            'fgp' => '0.500',
            'ftmpg' => '2.0',
            'ftapg' => '2.5',
            'ftp' => '0.800',
            'tgmpg' => '1.5',
            'tgapg' => '4.0',
            'tgp' => '0.375',
            'orbpg' => '3.0',
            'rpg' => '8.0',
            'apg' => '4.0',
            'spg' => '1.0',
            'tpg' => '1.5',
            'bpg' => '0.5',
            'fpg' => '2.0',
            'ppg' => '13.5',
            'qa' => '23.5'
        ];

        $html = $this->view->renderPlayerRow($stats, 1);

        // Check rank
        $this->assertStringContainsString('<td>1.</td>', $html);

        // Check player link
        $this->assertStringContainsString('modules.php?name=Player&pa=showpage&pid=123', $html);
        $this->assertStringContainsString('Test Player', $html);

        // Check team link
        $this->assertStringContainsString('modules.php?name=Team&op=team&teamID=1', $html);
        $this->assertStringContainsString('Test Team', $html);

        // Check some stats are displayed
        $this->assertStringContainsString('13.5', $html); // PPG
        $this->assertStringContainsString('23.5', $html); // QA
        $this->assertStringContainsString('0.500', $html); // FG%
    }

    public function testRenderPlayerRowAlternatesBackgroundColors(): void
    {
        $stats = [
            'pid' => 123,
            'name' => 'Test Player',
            'year' => 2024,
            'teamname' => 'Test Team',
            'teamid' => 1,
            'games' => 10,
            'mpg' => '30.0',
            'fgmpg' => '5.0',
            'fgapg' => '10.0',
            'fgp' => '0.500',
            'ftmpg' => '2.0',
            'ftapg' => '2.5',
            'ftp' => '0.800',
            'tgmpg' => '1.5',
            'tgapg' => '4.0',
            'tgp' => '0.375',
            'orbpg' => '3.0',
            'rpg' => '8.0',
            'apg' => '4.0',
            'spg' => '1.0',
            'tpg' => '1.5',
            'bpg' => '0.5',
            'fpg' => '2.0',
            'ppg' => '13.5',
            'qa' => '23.5'
        ];

        // Odd rank should have DDDDDD background
        $html1 = $this->view->renderPlayerRow($stats, 1);
        $this->assertStringContainsString('bgcolor="DDDDDD"', $html1);

        // Even rank should have FFFFFF background
        $html2 = $this->view->renderPlayerRow($stats, 2);
        $this->assertStringContainsString('bgcolor="FFFFFF"', $html2);
    }

    public function testRenderTableFooterReturnsClosingTag(): void
    {
        $html = $this->view->renderTableFooter();
        $this->assertEquals('</table>', $html);
    }
}
