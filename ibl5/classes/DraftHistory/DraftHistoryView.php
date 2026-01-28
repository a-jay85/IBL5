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
     * Uses consolidated .ibl-data-table with draft-history-specific overrides.
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return ''; // All styles provided by .ibl-year-nav and .ibl-data-table
    }

    /**
     * Render the page title.
     *
     * @param int $year Selected year
     * @return string HTML title
     */
    private function renderTitle(int $year): string
    {
        return '<h2 class="ibl-table-title">' . $year . ' Draft</h2>';
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
        $output = '<div class="ibl-year-nav">';

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
        return '<table class="sortable ibl-data-table draft-history-table">
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
    <td><img class=\"player-image\" src=\"/ibl5/images/player/{$pid}.jpg\" alt=\"{$name}\" width=\"65\" height=\"90\" loading=\"lazy\"></td>
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
