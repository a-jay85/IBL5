<?php

declare(strict_types=1);

namespace CapSpace;

use CapSpace\Contracts\CapSpaceViewInterface;
use UI\TeamCellHelper;
use Utilities\HtmlSanitizer;

/**
 * CapSpaceView - HTML rendering for salary cap information
 *
 * Generates sortable HTML table displaying team salary cap data.
 *
 * @phpstan-import-type CapSpaceTeamData from CapSpaceService
 *
 * @see CapSpaceViewInterface For the interface contract
 */
class CapSpaceView implements CapSpaceViewInterface
{
    /**
     * @see CapSpaceViewInterface::render()
     *
     * @param list<CapSpaceTeamData> $teamsData
     */
    public function render(array $teamsData, int $beginningYear, int $endingYear): string
    {
        $html = '';
        $html .= '<h2 class="ibl-title">Cap Info</h2>';
        $html .= '<div class="sticky-scroll-wrapper">';
        $html .= '<div class="sticky-scroll-container">';
        $html .= $this->renderTableHeader($beginningYear, $endingYear);
        $html .= $this->renderTableRows($teamsData);
        $html .= '</tbody></table>';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Render table header with year columns
     *
     * @param int $beginningYear Starting year
     * @param int $endingYear Ending year
     * @return string HTML for table header
     */
    private function renderTableHeader(int $beginningYear, int $endingYear): string
    {
        $html = '<table class="sortable ibl-data-table sticky-table">';
        $html .= '<thead><tr>';
        $html .= '<th class="sticky-col sticky-corner">Team</th>';

        // Year columns (6 years)
        for ($i = 0; $i < 6; $i++) {
            $yearStart = $beginningYear + $i;
            $yearEnd = $endingYear + $i;
            $html .= '<th>' . $yearStart . '-<br>';
            $html .= $yearEnd . '<br>Total</th>';
        }

        $html .= '<th class="divider"></th>';

        // Position columns (current year only)
        foreach (\JSB::PLAYER_POSITIONS as $position) {
            $safeBeginningYear = HtmlSanitizer::safeHtmlOutput($beginningYear);
            $safeEndingYear = HtmlSanitizer::safeHtmlOutput($endingYear);
            $safePosition = HtmlSanitizer::safeHtmlOutput($position);
            $html .= '<th>' . $safeBeginningYear . '-<br>';
            $html .= $safeEndingYear . '<br>';
            $html .= $safePosition . '</th>';
        }

        $html .= '<th class="divider"></th>';
        $html .= '<th>FA Slots</th>';
        $html .= '<th>Has MLE</th>';
        $html .= '<th>Has LLE</th>';
        $html .= '</tr></thead><tbody>';

        return $html;
    }

    /**
     * Render all team rows
     *
     * @param list<CapSpaceTeamData> $teamsData Array of processed team data
     * @return string HTML for all team rows
     */
    private function renderTableRows(array $teamsData): string
    {
        $html = '';

        foreach ($teamsData as $teamData) {
            $html .= $this->renderTeamRow($teamData);
        }

        return $html;
    }

    /**
     * Render a single team row
     *
     * @param CapSpaceTeamData $teamData Team's cap data
     * @return string HTML for team row
     */
    private function renderTeamRow(array $teamData): string
    {
        $teamId = $teamData['teamId'];
        $contractsUrl = 'modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '&amp;display=contracts';

        $html = '<tr data-team-id="' . $teamId . '">';
        $html .= TeamCellHelper::renderTeamCell($teamId, $teamData['teamName'], $teamData['color1'], $teamData['color2'], 'sticky-col', $contractsUrl);

        // Available salary columns
        $years = ['year1', 'year2', 'year3', 'year4', 'year5', 'year6'];
        foreach ($years as $year) {
            $html .= '<td>';
            $safeSalary = HtmlSanitizer::safeHtmlOutput($teamData['availableSalary'][$year]);
            $html .= $safeSalary;
            $html .= '</td>';
        }

        $html .= '<td class="divider"></td>';

        // Position salary columns
        foreach (\JSB::PLAYER_POSITIONS as $position) {
            $html .= '<td>';
            $safePositionSalary = HtmlSanitizer::safeHtmlOutput($teamData['positionSalaries'][$position] ?? 0);
            $html .= $safePositionSalary;
            $html .= '</td>';
        }

        $html .= '<td class="divider"></td>';

        // FA Slots
        $html .= '<td>';
        $safeFaSlots = HtmlSanitizer::safeHtmlOutput($teamData['freeAgencySlots']);
        $html .= $safeFaSlots;
        $html .= '</td>';

        // MLE/LLE icons
        $mleIcon = $teamData['hasMLE'] ? "\u{2705}" : "\u{274C}";
        $lleIcon = $teamData['hasLLE'] ? "\u{2705}" : "\u{274C}";

        $html .= '<td>' . $mleIcon . '</td>';
        $html .= '<td>' . $lleIcon . '</td>';

        $html .= '</tr>';

        return $html;
    }
}
