<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;
use Voting\VotingResultsTableRenderer;

final class VotingResultsTableRendererTest extends TestCase
{
    public function testRenderTablesEscapesContentAndPreservesStructure(): void
    {
        $renderer = new VotingResultsTableRenderer();

        $html = $renderer->renderTables([
            [
                'title' => 'Test & Title',
                'rows' => [
                    ['name' => 'Alice <One>', 'votes' => 10],
                    ['name' => 'Bob "Two"', 'votes' => 5],
                ],
            ],
        ]);

        // Check title uses ibl-title class
        $this->assertStringContainsString('<h2 class="ibl-title">Test &amp; Title</h2>', $html);
        // Check table uses proper CSS classes
        $this->assertStringContainsString('ibl-data-table', $html);
        $this->assertStringContainsString('voting-results-table', $html);
        // Check data rows are properly escaped
        $this->assertStringContainsString('Alice &lt;One&gt;', $html);
        $this->assertStringContainsString('Bob &quot;Two&quot;', $html);
        // Check vote counts are rendered
        $this->assertStringContainsString('>10<', $html);
        $this->assertStringContainsString('>5<', $html);
        // Check header columns
        $this->assertStringContainsString('<th>Player</th>', $html);
        $this->assertStringContainsString('<th>Votes</th>', $html);
        // Voting tables should NOT have scroll wrappers (full display on mobile)
        $this->assertStringNotContainsString('table-scroll-wrapper', $html);
        $this->assertStringNotContainsString('table-scroll-container', $html);
    }

    public function testRenderTablesOutputsEmptyTableWhenNoRows(): void
    {
        $renderer = new VotingResultsTableRenderer();

        $html = $renderer->renderTables([
            [
                'title' => 'Empty Category',
                'rows' => [],
            ],
        ]);

        $this->assertStringContainsString('<h2 class="ibl-title">Empty Category</h2>', $html);
        $this->assertStringContainsString('<th>Player</th>', $html);
        $this->assertStringContainsString('<tbody>', $html);
    }
}
