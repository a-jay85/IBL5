<?php

declare(strict_types=1);

namespace ContractList;

use ContractList\Contracts\ContractListViewInterface;
use Player\PlayerImageHelper;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;

/**
 * View class for rendering master contract list table.
 *
 * @phpstan-type ViewContract array{pid: int, name: string, pos: string, teamname: string, tid: int, team_city: string, color1: string, color2: string, bird: string, con1: int, con2: int, con3: int, con4: int, con5: int, con6: int}
 * @phpstan-type CapTotals array{cap1: float, cap2: float, cap3: float, cap4: float, cap5: float, cap6: float}
 * @phpstan-type AvgCaps array{acap1: float, acap2: float, acap3: float, acap4: float, acap5: float, acap6: float}
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
        $output = '';
        $output .= $this->renderTitle();
        $output .= $this->renderTableStart();
        $output .= $this->renderTableRows($data['contracts']);
        $output .= $this->renderCapTotals($data['capTotals']);
        $output .= $this->renderAvgCaps($data['avgCaps']);
        $output .= $this->renderTableEnd();

        return $output;
    }

    /**
     * Render the page title.
     *
     * @return string HTML title
     */
    private function renderTitle(): string
    {
        return '<h2 class="ibl-title">Master Contract List</h2>';
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
                    <th class="sticky-col">Player</th>
                    <th>Pos</th>
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
     * @param array<int, ViewContract> $contracts Array of contract data
     * @return string HTML table rows
     */
    private function renderTableRows(array $contracts): string
    {
        $output = '';

        foreach ($contracts as $contract) {
            $pid = $contract['pid'];
            $tid = $contract['tid'];
            $pos = HtmlSanitizer::safeHtmlOutput($contract['pos']);
            $bird = HtmlSanitizer::safeHtmlOutput($contract['bird']);
            $con1 = $contract['con1'];
            $con2 = $contract['con2'];
            $con3 = $contract['con3'];
            $con4 = $contract['con4'];
            $con5 = $contract['con5'];
            $con6 = $contract['con6'];

            $playerCell = PlayerImageHelper::renderFlexiblePlayerCell($pid, $contract['name'], 'sticky-col');
            $teamCell = TeamCellHelper::renderTeamCellOrFreeAgent($tid, $contract['teamname'], $contract['color1'], $contract['color2']);

            $output .= "<tr data-team-id=\"{$tid}\">"
                . $playerCell
                . "<td>{$pos}</td>"
                . $teamCell
                . "<td>{$bird}</td>"
                . "<td>{$con1}</td>"
                . "<td>{$con2}</td>"
                . "<td>{$con3}</td>"
                . "<td>{$con4}</td>"
                . "<td>{$con5}</td>"
                . "<td>{$con6}</td>"
                . '</tr>';
        }

        return $output;
    }

    /**
     * Render cap totals row.
     *
     * @param CapTotals $capTotals Cap totals
     * @return string HTML table row
     */
    private function renderCapTotals(array $capTotals): string
    {
        return sprintf(
            '<tr class="totals-row">
    <td class="sticky-col">Cap Totals</td>
    <td></td>
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
     * @param AvgCaps $avgCaps Average caps
     * @return string HTML table row
     */
    private function renderAvgCaps(array $avgCaps): string
    {
        return sprintf(
            '<tr class="totals-row">
    <td class="sticky-col">Average Team Cap</td>
    <td></td>
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
