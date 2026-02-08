<?php

declare(strict_types=1);

namespace Voting;

use Player\PlayerImageHelper;
use Voting\Contracts\VotingResultsTableRendererInterface;
use Voting\Contracts\VotingResultsServiceInterface;

/**
 * @phpstan-import-type VoteRow from VotingResultsServiceInterface
 * @phpstan-import-type VoteTable from VotingResultsServiceInterface
 *
 * @see VotingResultsTableRendererInterface
 */
class VotingResultsTableRenderer implements VotingResultsTableRendererInterface
{
    private const METRIC_LABEL = 'Votes';

    /**
     * @see VotingResultsTableRendererInterface::renderTables()
     *
     * @param list<VoteTable> $tables
     */
    public function renderTables(array $tables): string
    {
        $output = '';
        foreach ($tables as $table) {
            $title = $table['title'];
            $rows = $table['rows'];
            $output .= $this->renderTable($title, $rows);
        }

        return $output;
    }

    /**
     * @param list<VoteRow> $rows
     */
    private function renderTable(string $title, array $rows): string
    {
        $escapedTitle = htmlspecialchars($title, \ENT_QUOTES, 'UTF-8');

        /** @var list<string> $rowsHtml */
        $rowsHtml = [];
        foreach ($rows as $row) {
            $name = htmlspecialchars($row['name'], \ENT_QUOTES, 'UTF-8');
            $votes = $row['votes'];
            $pid = $row['pid'] ?? 0;

            if ($pid > 0) {
                $nameCell = PlayerImageHelper::renderFlexiblePlayerCell($pid, $row['name']);
            } else {
                $nameCell = '<td>' . $name . '</td>';
            }

            $rowsHtml[] = sprintf(
                '        <tr>%s<td>%d</td></tr>',
                $nameCell,
                $votes
            );
        }

        $tableRowsHtml = $rowsHtml !== [] ? "\n" . implode("\n", $rowsHtml) . "\n    " : "\n    ";

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
