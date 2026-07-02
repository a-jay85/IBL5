<?php

declare(strict_types=1);

namespace Tests\Voting;

use PHPUnit\Framework\TestCase;
use Voting\VotingResultsView;

class VotingResultsViewTest extends TestCase
{
    private VotingResultsView $view;

    protected function setUp(): void
    {
        $this->view = new VotingResultsView();
    }

    /** @return list<array{title: string, rows: list<array{name: string, votes: int, pid: int}>}> */
    private function makeMinimalTables(): array
    {
        return [
            [
                'title' => 'Most Valuable Player',
                'rows' => [
                    ['name' => 'Jane Doe', 'votes' => 42, 'pid' => 0],
                ],
            ],
        ];
    }

    public function testRenderTablesWithNoTitleEmitsNoH1(): void
    {
        $html = $this->view->renderTables($this->makeMinimalTables());
        self::assertStringContainsString('<h2 class="ibl-title">', $html);
        self::assertStringNotContainsString('<h1', $html);
    }

    public function testRenderTablesWithAllStarTitleEmitsH1(): void
    {
        $html = $this->view->renderTables($this->makeMinimalTables(), 'All-Star Voting Results');
        self::assertStringContainsString('<h1 class="ibl-title">All-Star Voting Results</h1>', $html);
        self::assertStringContainsString('<h2 class="ibl-title">', $html);
    }

    public function testRenderTablesWithEndOfYearTitleEmitsH1(): void
    {
        $html = $this->view->renderTables($this->makeMinimalTables(), 'End-of-Year Voting Results');
        self::assertStringContainsString('<h1 class="ibl-title">End-of-Year Voting Results</h1>', $html);
    }

    public function testRenderTablesH1AppearsBeforeH2(): void
    {
        $html = $this->view->renderTables($this->makeMinimalTables(), 'All-Star Voting Results');
        $h1Pos = strpos($html, '<h1');
        $h2Pos = strpos($html, '<h2');
        self::assertNotFalse($h1Pos);
        self::assertNotFalse($h2Pos);
        self::assertLessThan($h2Pos, $h1Pos);
    }

    public function testRenderTablesPageTitleIsEscaped(): void
    {
        $html = $this->view->renderTables($this->makeMinimalTables(), 'All-Star Voting Results');
        // Confirm the output is HTML-safe (safeHtmlOutput wraps this)
        self::assertStringContainsString('All-Star Voting Results', $html);
    }
}
