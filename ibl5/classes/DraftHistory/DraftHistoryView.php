<?php

declare(strict_types=1);

namespace DraftHistory;

use DraftHistory\Contracts\DraftHistoryViewInterface;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering draft history page.
 *
 * @see DraftHistoryViewInterface
 */
class DraftHistoryView implements DraftHistoryViewInterface
{
    /**
     * @see DraftHistoryViewInterface::render()
     */
    public function render(int $selectedYear, int $startYear, int $endYear, array $draftPicks): string
    {
        $output = $this->getStyleBlock();
        $output .= $this->renderTitle($selectedYear);
        $output .= $this->renderYearNavigation($startYear, $endYear, $selectedYear);

        if (empty($draftPicks)) {
            $output .= $this->renderNoDataMessage();
        } else {
            $output .= $this->renderTableStart();
            $output .= $this->renderTableRows($draftPicks);
            $output .= $this->renderTableEnd();
        }

        return $output;
    }

    /**
     * Get the CSS styles for the draft history table.
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
.draft-title {
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--navy-900, #0f172a);
    text-align: center;
    margin: 0 0 1rem 0;
}
.draft-nav {
    text-align: center;
    margin-bottom: 1.5rem;
    font-family: var(--font-sans, \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif);
    font-size: 1.125rem;
    line-height: 2;
}
.draft-nav a {
    color: var(--gray-600, #4b5563);
    text-decoration: none;
    padding: 0.25rem 0.5rem;
    margin: 0 0.125rem;
    border-radius: var(--radius-sm, 0.25rem);
    transition: all 150ms ease;
}
.draft-nav a:hover {
    color: var(--accent-600, #ea580c);
    background-color: var(--accent-50, #fff7ed);
}
.draft-nav a.active {
    color: white;
    background-color: var(--accent-500, #f97316);
    font-weight: 600;
}
.draft-table {
    font-family: var(--font-sans, \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif);
    border-collapse: separate;
    border-spacing: 0;
    border: none;
    border-radius: var(--radius-lg, 0.5rem);
    overflow: hidden;
    box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1));
    width: 100%;
    max-width: 900px;
    margin: 0 auto;
}
.draft-table thead {
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
}
.draft-table th {
    color: white;
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 600;
    font-size: 1rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.75rem 0.625rem;
    text-align: center;
}
.draft-table td {
    color: var(--gray-800, #1f2937);
    font-size: 1.125rem;
    padding: 0.625rem;
    text-align: center;
}
.draft-table tbody tr {
    transition: background-color 150ms ease;
}
.draft-table tbody tr:nth-child(odd) {
    background-color: white;
}
.draft-table tbody tr:nth-child(even) {
    background-color: var(--gray-50, #f9fafb);
}
.draft-table tbody tr:hover {
    background-color: var(--gray-100, #f3f4f6);
}
.draft-table a {
    color: var(--gray-800, #1f2937);
    text-decoration: none;
    font-weight: 500;
    transition: color 150ms ease;
}
.draft-table a:hover {
    color: var(--accent-500, #f97316);
}
.draft-table .player-image {
    height: 50px;
    border-radius: var(--radius-sm, 0.25rem);
}
.draft-no-data {
    text-align: center;
    padding: 2rem;
    color: var(--gray-500, #6b7280);
    font-family: var(--font-sans, \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif);
}
</style>';
    }

    /**
     * Render the page title.
     *
     * @param int $year Selected year
     * @return string HTML title
     */
    private function renderTitle(int $year): string
    {
        return '<h2 class="draft-title">' . $year . ' Draft</h2>';
    }

    /**
     * Render the year navigation.
     *
     * @param int $startYear First draft year
     * @param int $endYear Last draft year
     * @param int $selectedYear Currently selected year
     * @return string HTML navigation
     */
    private function renderYearNavigation(int $startYear, int $endYear, int $selectedYear): string
    {
        $output = '<div class="draft-nav">';

        for ($year = $startYear; $year <= $endYear; $year++) {
            $activeClass = ($year === $selectedYear) ? ' class="active"' : '';
            $output .= '<a href="./modules.php?name=Draft_History&amp;year=' . $year . '"' . $activeClass . '>' . $year . '</a>';
            if ($year < $endYear) {
                $output .= ' | ';
            }
        }

        $output .= '</div>';
        return $output;
    }

    /**
     * Render the no data message.
     *
     * @return string HTML message
     */
    private function renderNoDataMessage(): string
    {
        return '<p class="draft-no-data">Please select a draft year.</p>';
    }

    /**
     * Render the start of the table.
     *
     * @return string HTML table start
     */
    private function renderTableStart(): string
    {
        return '<table class="sortable draft-table">
            <thead>
                <tr>
                    <th>Round</th>
                    <th>Pick</th>
                    <th>Player</th>
                    <th>Selected By</th>
                    <th>Pic</th>
                    <th>College</th>
                </tr>
            </thead>
            <tbody>';
    }

    /**
     * Render all table rows.
     *
     * @param array $draftPicks Array of draft pick data
     * @return string HTML table rows
     */
    private function renderTableRows(array $draftPicks): string
    {
        $output = '';

        foreach ($draftPicks as $pick) {
            $pid = (int) ($pick['pid'] ?? 0);
            $name = HtmlSanitizer::safeHtmlOutput($pick['name'] ?? '');
            $round = (int) ($pick['draftround'] ?? 0);
            $pickNo = (int) ($pick['draftpickno'] ?? 0);
            $draftedBy = HtmlSanitizer::safeHtmlOutput($pick['draftedby'] ?? '');
            $college = HtmlSanitizer::safeHtmlOutput($pick['college'] ?? '');

            $output .= "<tr>
    <td>{$round}</td>
    <td>{$pickNo}</td>
    <td><a href=\"./modules.php?name=Player&amp;pa=showpage&amp;pid={$pid}\">{$name}</a></td>
    <td>{$draftedBy}</td>
    <td><img class=\"player-image\" src=\"/ibl5/images/player/{$pid}.jpg\" alt=\"{$name}\"></td>
    <td>{$college}</td>
</tr>";
        }

        return $output;
    }

    /**
     * Render the end of the table.
     *
     * @return string HTML table end
     */
    private function renderTableEnd(): string
    {
        return '</tbody></table>';
    }
}
