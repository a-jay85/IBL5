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

        // Check for essential column headers (modern <th> tags)
        $this->assertStringContainsString('>Rank<', $html);
        $this->assertStringContainsString('>Year<', $html);
        $this->assertStringContainsString('>Name<', $html);
        $this->assertStringContainsString('>Team<', $html);
        $this->assertStringContainsString('>ppg<', $html);
        $this->assertStringContainsString('>qa<', $html);
        // Check for sortable table class
        $this->assertStringContainsString('sortable', $html);
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

        // Check rank (with class attribute)
        $this->assertStringContainsString('>1.</td>', $html);

        // Check player link (& properly encoded as &amp; in HTML)
        $this->assertStringContainsString('modules.php?name=Player&amp;pa=showpage&amp;pid=123', $html);
        $this->assertStringContainsString('Test Player', $html);

        // Check team link (& properly encoded as &amp; in HTML)
        $this->assertStringContainsString('modules.php?name=Team&amp;op=team&amp;teamID=1', $html);
        $this->assertStringContainsString('Test Team', $html);

        // Check some stats are displayed
        $this->assertStringContainsString('13.5', $html); // PPG
        $this->assertStringContainsString('23.5', $html); // QA
        $this->assertStringContainsString('0.500', $html); // FG%
    }

    public function testRenderTableHeaderUsesDesignSystemClasses(): void
    {
        // Row alternation is handled by design system CSS via ibl-data-table class
        $html = $this->view->renderTableHeader();
        $this->assertStringContainsString('ibl-data-table', $html);
        $this->assertStringContainsString('table-scroll-container', $html);
    }

    public function testRenderTableFooterReturnsClosingTags(): void
    {
        $html = $this->view->renderTableFooter();
        // Footer closes tbody, table, and container div
        $this->assertStringContainsString('</tbody>', $html);
        $this->assertStringContainsString('</table>', $html);
        $this->assertStringContainsString('</div>', $html);
    }
}
