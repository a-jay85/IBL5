<?php

declare(strict_types=1);

namespace Voting;

use function htmlspecialchars;
use function implode;
use function sprintf;

/**
 * Renders voting results tables using the legacy module styling.
 */
final class VotingResultsTableRenderer
{
    private const METRIC_LABEL = 'Votes';

    /**
     * @param array<int, array{title: string, rows: array<int, array{name: string, votes: int}>}> $tables
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

    /**
     * @param array<int, array{name: string, votes: int}> $rows
     */
    private function renderTable(string $title, array $rows): string
    {
        $escapedTitle = htmlspecialchars($title, \ENT_QUOTES, 'UTF-8');

        $rowsHtml = [];
        foreach ($rows as $row) {
            $name = htmlspecialchars((string) ($row['name'] ?? ''), \ENT_QUOTES, 'UTF-8');
            $votes = (int) ($row['votes'] ?? 0);
            $rowsHtml[] = sprintf('    <tr><td>%s</td><td>%d</td></tr>', $name, $votes);
        }

        $tableRowsHtml = $rowsHtml ? "\n" . implode("\n", $rowsHtml) . "\n" : "\n";
        $metricLabel = self::METRIC_LABEL;

        return <<<HTML
<h2>{$escapedTitle}</h2>
<table class="sortable" border=1>
    <tr>
        <th>Player</th>
        <th>{$metricLabel}</th>
    </tr>{$tableRowsHtml}</table>
<br><br>

HTML;
    }
}
