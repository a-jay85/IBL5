<?php

declare(strict_types=1);

namespace ContractList;

use ContractList\Contracts\ContractListViewInterface;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering master contract list table.
 *
 * @see ContractListViewInterface
 */
class ContractListView implements ContractListViewInterface
{
    /**
     * @see ContractListViewInterface::render()
     */
    public function render(array $data): string
    {
        $output = $this->getStyleBlock();
        $output .= $this->renderTitle();
        $output .= $this->renderTableStart();
        $output .= $this->renderTableRows($data['contracts']);
        $output .= $this->renderCapTotals($data['capTotals']);
        $output .= $this->renderAvgCaps($data['avgCaps']);
        $output .= $this->renderTableEnd();

        return $output;
    }

    /**
     * Get the CSS styles for the contract list table.
     *
     * Uses consolidated .ibl-data-table from design system - no overrides needed.
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return ''; // All styles provided by .ibl-data-table
    }

    /**
     * Render the page title.
     *
     * @return string HTML title
     */
    private function renderTitle(): string
    {
        return '<h2 class="ibl-table-title">Master Contract List</h2>';
    }

    /**
     * Render the start of the table.
     *
     * @return string HTML table start
     */
    private function renderTableStart(): string
    {
        return '<table class="sortable ibl-data-table responsive-table">
            <thead>
                <tr>
                    <th>Pos</th>
                    <th class="sticky-col">Player</th>
                    <th>Team</th>
                    <th>Bird</th>
                    <th>Year1</th>
                    <th>Year2</th>
                    <th>Year3</th>
                    <th>Year4</th>
                    <th>Year5</th>
                    <th>Year6</th>
                </tr>
            </thead>
            <tbody>';
    }

    /**
     * Render all contract rows.
     *
     * @param array $contracts Array of contract data
     * @return string HTML table rows
     */
    private function renderTableRows(array $contracts): string
    {
        $output = '';

        foreach ($contracts as $contract) {
            $pid = (int) ($contract['pid'] ?? 0);
            $name = HtmlSanitizer::safeHtmlOutput($contract['name'] ?? '');
            $pos = HtmlSanitizer::safeHtmlOutput($contract['pos'] ?? '');
            $tid = (int) ($contract['tid'] ?? 0);
            $bird = HtmlSanitizer::safeHtmlOutput($contract['bird'] ?? '');
            $con1 = (int) ($contract['con1'] ?? 0);
            $con2 = (int) ($contract['con2'] ?? 0);
            $con3 = (int) ($contract['con3'] ?? 0);
            $con4 = (int) ($contract['con4'] ?? 0);
            $con5 = (int) ($contract['con5'] ?? 0);
            $con6 = (int) ($contract['con6'] ?? 0);

            // Team cell styling
            $teamName = HtmlSanitizer::safeHtmlOutput($contract['teamname'] ?? '');
            $color1 = HtmlSanitizer::safeHtmlOutput($contract['color1'] ?? 'FFFFFF');
            $color2 = HtmlSanitizer::safeHtmlOutput($contract['color2'] ?? '000000');

            // Handle free agents (tid=0) gracefully
            if ($tid === 0) {
                $teamCell = '<td>Free Agent</td>';
            } else {
                $teamCell = "<td class=\"ibl-team-cell--colored\" style=\"background-color: #{$color1};\">
        <a href=\"./modules.php?name=Team&amp;op=team&amp;teamID={$tid}\" class=\"ibl-team-cell__name\" style=\"color: #{$color2};\">
            <img src=\"images/logo/new{$tid}.png\" alt=\"\" class=\"ibl-team-cell__logo\" width=\"24\" height=\"24\" loading=\"lazy\">
            <span class=\"ibl-team-cell__text\">{$teamName}</span>
        </a>
    </td>";
            }

            $output .= "<tr>
    <td>{$pos}</td>
    <td class=\"sticky-col\" style=\"white-space: nowrap;\"><a href=\"./modules.php?name=Player&amp;pa=showpage&amp;pid={$pid}\">{$name}</a></td>
    {$teamCell}
    <td>{$bird}</td>
    <td>{$con1}</td>
    <td>{$con2}</td>
    <td>{$con3}</td>
    <td>{$con4}</td>
    <td>{$con5}</td>
    <td>{$con6}</td>
</tr>";
        }

        return $output;
    }

    /**
     * Render cap totals row.
     *
     * @param array{cap1: float, cap2: float, cap3: float, cap4: float, cap5: float, cap6: float} $capTotals Cap totals
     * @return string HTML table row
     */
    private function renderCapTotals(array $capTotals): string
    {
        return sprintf(
            '<tr class="totals-row">
    <td></td>
    <td class="sticky-col">Cap Totals</td>
    <td></td>
    <td></td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
</tr>',
            $capTotals['cap1'],
            $capTotals['cap2'],
            $capTotals['cap3'],
            $capTotals['cap4'],
            $capTotals['cap5'],
            $capTotals['cap6']
        );
    }

    /**
     * Render average team cap row.
     *
     * @param array{acap1: float, acap2: float, acap3: float, acap4: float, acap5: float, acap6: float} $avgCaps Average caps
     * @return string HTML table row
     */
    private function renderAvgCaps(array $avgCaps): string
    {
        return sprintf(
            '<tr class="totals-row">
    <td></td>
    <td class="sticky-col">Average Team Cap</td>
    <td></td>
    <td></td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
    <td>%.2f</td>
</tr>',
            $avgCaps['acap1'],
            $avgCaps['acap2'],
            $avgCaps['acap3'],
            $avgCaps['acap4'],
            $avgCaps['acap5'],
            $avgCaps['acap6']
        );
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
