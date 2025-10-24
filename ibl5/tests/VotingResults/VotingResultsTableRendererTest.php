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

        $this->assertStringContainsString('<h2>Test &amp; Title</h2>', $html);
        $this->assertStringContainsString('<td>Alice &lt;One&gt;</td><td>10</td>', $html);
        $this->assertStringContainsString('<td>Bob &quot;Two&quot;</td><td>5</td>', $html);
        $this->assertStringContainsString('<th>Votes</th>', $html);
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

        $this->assertStringContainsString('<h2>Empty Category</h2>', $html);
        $this->assertStringContainsString('<th>Player</th>', $html);
        $this->assertStringNotContainsString('<td></td><td></td>', $html);
    }
}
