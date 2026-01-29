<?php

declare(strict_types=1);

namespace Voting;

use Voting\Contracts\VotingResultsTableRendererInterface;

/**
 * @see VotingResultsTableRendererInterface
 */
class VotingResultsTableRenderer implements VotingResultsTableRendererInterface
{
    private const METRIC_LABEL = 'Votes';

    /**
     * @see VotingResultsTableRendererInterface::renderTables()
     */
    public function renderTables(array $tables): string
    {
        $output = '';
        foreach ($tables as $table) {
            $title = $table['title'] ?? '';
            $rows = $table['rows'] ?? [];
            $output .= $this->renderTable($title, $rows);
        }

        return $output;
    }

    private function renderTable(string $title, array $rows): string
    {
        $escapedTitle = htmlspecialchars($title, \ENT_QUOTES, 'UTF-8');

        $rowsHtml = [];
        foreach ($rows as $row) {
            $name = htmlspecialchars((string) ($row['name'] ?? ''), \ENT_QUOTES, 'UTF-8');
            $votes = (int) ($row['votes'] ?? 0);
            $rowsHtml[] = sprintf(
                '        <tr><td>%s</td><td>%d</td></tr>',
                $name,
                $votes
            );
        }

        $tableRowsHtml = $rowsHtml ? "\n" . implode("\n", $rowsHtml) . "\n    " : "\n    ";

        $html = '<h2 class="ibl-title">' . $escapedTitle . '</h2>
<table class="sortable ibl-data-table voting-results-table">
    <thead>
        <tr>
            <th>Player</th>
            <th>' . self::METRIC_LABEL . '</th>
        </tr>
    </thead>
    <tbody>' . $tableRowsHtml . '</tbody>
</table>';

        return $html;
    }
}
