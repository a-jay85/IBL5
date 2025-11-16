<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Leaderboards\LeaderboardsService;
use Leaderboards\LeaderboardsView;

final class LeaderboardsViewTest extends TestCase
{
    private LeaderboardsView $view;
    private LeaderboardsService $service;

    protected function setUp(): void
    {
        $this->service = new LeaderboardsService();
        $this->view = new LeaderboardsView($this->service);
    }

    public function testRenderFilterFormCreatesValidHtml(): void
    {
        $filters = [
            'boards_type' => 'Regular Season Totals',
            'sort_cat' => 'Points',
            'active' => '1',
            'display' => '50'
        ];

        $html = $this->view->renderFilterForm($filters);

        // Check that form is rendered
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('name="Leaderboards"', $html);
        $this->assertStringContainsString('action="modules.php?name=Leaderboards"', $html);
        
        // Check that all form fields are present
        $this->assertStringContainsString('name="boards_type"', $html);
        $this->assertStringContainsString('name="sort_cat"', $html);
        $this->assertStringContainsString('name="active"', $html);
        $this->assertStringContainsString('name="display"', $html);
        $this->assertStringContainsString('name="submitted"', $html);
        
        // Check that selected values are marked
        $this->assertStringContainsString('value="50"', $html);
        $this->assertStringContainsString('SELECTED', $html);
        
        // Check that HTML is properly escaped
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testRenderFilterFormHandlesEmptyFilters(): void
    {
        $filters = [];

        $html = $this->view->renderFilterForm($filters);

        // Should still render the form
        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('name="Leaderboards"', $html);
    }

    public function testRenderTableHeaderCreatesValidHtml(): void
    {
        $html = $this->view->renderTableHeader();

        // Check for header elements
        $this->assertStringContainsString('<h2', $html);
        $this->assertStringContainsString('Leaderboards Display', $html);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('class="sortable"', $html);
        
        // Check that all stat columns are present
        $this->assertStringContainsString('>Rank<', $html);
        $this->assertStringContainsString('>Name<', $html);
        $this->assertStringContainsString('>Games<', $html);
        $this->assertStringContainsString('>Minutes<', $html);
        $this->assertStringContainsString('>FGM<', $html);
        $this->assertStringContainsString('>FGA<', $html);
        $this->assertStringContainsString('>FG%<', $html);
        $this->assertStringContainsString('>PTS<', $html);
    }

    public function testRenderPlayerRowCreatesValidHtml(): void
    {
        $stats = [
            'pid' => 123,
            'name' => 'Test Player',
            'games' => '82',
            'minutes' => '3,000',
            'fgm' => '500',
            'fga' => '1,000',
            'fgp' => '0.500',
            'ftm' => '200',
            'fta' => '250',
            'ftp' => '0.800',
            'tgm' => '150',
            'tga' => '400',
            'tgp' => '0.375',
            'orb' => '100',
            'reb' => '500',
            'ast' => '400',
            'stl' => '80',
            'tvr' => '150',
            'blk' => '50',
            'pf' => '200',
            'pts' => '1,350'
        ];

        $html = $this->view->renderPlayerRow($stats, 1);

        // Check that row is created
        $this->assertStringContainsString('<tr>', $html);
        $this->assertStringContainsString('</tr>', $html);
        
        // Check that rank is displayed
        $this->assertStringContainsString('>1<', $html);
        
        // Check that player link is created
        $this->assertStringContainsString('href="modules.php?name=Player&pa=showpage&pid=123"', $html);
        $this->assertStringContainsString('>Test Player<', $html);
        
        // Check that stats are displayed
        $this->assertStringContainsString('>82<', $html);
        $this->assertStringContainsString('>3,000<', $html);
        $this->assertStringContainsString('>0.500<', $html);
        $this->assertStringContainsString('>1,350<', $html);
    }

    public function testRenderPlayerRowEscapesHtml(): void
    {
        $stats = [
            'pid' => 456,
            'name' => 'Player <script>alert("XSS")</script>',
            'games' => '82',
            'minutes' => '3,000',
            'fgm' => '500',
            'fga' => '1,000',
            'fgp' => '0.500',
            'ftm' => '200',
            'fta' => '250',
            'ftp' => '0.800',
            'tgm' => '150',
            'tga' => '400',
            'tgp' => '0.375',
            'orb' => '100',
            'reb' => '500',
            'ast' => '400',
            'stl' => '80',
            'tvr' => '150',
            'blk' => '50',
            'pf' => '200',
            'pts' => '1,350'
        ];

        $html = $this->view->renderPlayerRow($stats, 1);

        // Check that HTML is properly escaped
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderTableFooterCreatesValidHtml(): void
    {
        $html = $this->view->renderTableFooter();

        // Check that table is closed
        $this->assertStringContainsString('</table>', $html);
        $this->assertStringContainsString('</td>', $html);
        $this->assertStringContainsString('</tr>', $html);
    }

    public function testRenderPlayerRowHandlesRetiredPlayer(): void
    {
        $stats = [
            'pid' => 789,
            'name' => 'Retired Legend*',
            'games' => '1,000',
            'minutes' => '40,000',
            'fgm' => '10,000',
            'fga' => '20,000',
            'fgp' => '0.500',
            'ftm' => '5,000',
            'fta' => '6,000',
            'ftp' => '0.833',
            'tgm' => '2,000',
            'tga' => '6,000',
            'tgp' => '0.333',
            'orb' => '2,000',
            'reb' => '10,000',
            'ast' => '8,000',
            'stl' => '1,500',
            'tvr' => '2,000',
            'blk' => '1,000',
            'pf' => '3,000',
            'pts' => '27,000'
        ];

        $html = $this->view->renderPlayerRow($stats, 1);

        // Check that asterisk is displayed for retired player
        $this->assertStringContainsString('Retired Legend*', $html);
    }
}
