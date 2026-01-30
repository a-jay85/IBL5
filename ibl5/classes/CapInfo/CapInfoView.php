<?php

declare(strict_types=1);

namespace CapInfo;

use CapInfo\Contracts\CapInfoViewInterface;
use Utilities\HtmlSanitizer;

/**
 * CapInfoView - HTML rendering for salary cap information
 *
 * Generates sortable HTML table displaying team salary cap data.
 *
 * @see CapInfoViewInterface For the interface contract
 */
class CapInfoView implements CapInfoViewInterface
{
    /**
     * @see CapInfoViewInterface::render()
     */
    public function render(array $teamsData, int $beginningYear, int $endingYear, ?int $userTeamId): string
    {
        $html = $this->getStyleBlock();
        $html .= '<div class="sticky-scroll-wrapper">';
        $html .= '<div class="sticky-scroll-container">';
        $html .= $this->renderTableHeader($beginningYear, $endingYear);
        $html .= $this->renderTableRows($teamsData, $userTeamId);
        $html .= '</tbody></table>';
        $html .= '</div></div>';

        return $html;
    }

    /**
     * Generate CSS styles for the cap info table
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return ''; // All styles provided by .ibl-data-table with .divider and .highlight row classes
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
            $html .= '<th>' . HtmlSanitizer::safeHtmlOutput($yearStart) . '-<br>';
            $html .= HtmlSanitizer::safeHtmlOutput($yearEnd) . '<br>Total</th>';
        }

        $html .= '<th class="divider"></th>';

        // Position columns (current year only)
        foreach (\JSB::PLAYER_POSITIONS as $position) {
            $html .= '<th>' . HtmlSanitizer::safeHtmlOutput($beginningYear) . '-<br>';
            $html .= HtmlSanitizer::safeHtmlOutput($endingYear) . '<br>';
            $html .= HtmlSanitizer::safeHtmlOutput($position) . '</th>';
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
     * @param array $teamsData Array of processed team data
     * @param int|null $userTeamId User's team ID for highlighting
     * @return string HTML for all team rows
     */
    private function renderTableRows(array $teamsData, ?int $userTeamId): string
    {
        $html = '';

        foreach ($teamsData as $teamData) {
            $html .= $this->renderTeamRow($teamData, $userTeamId);
        }

        return $html;
    }

    /**
     * Render a single team row
     *
     * @param array $teamData Team's cap data
     * @param int|null $userTeamId User's team ID for highlighting
     * @return string HTML for team row
     */
    private function renderTeamRow(array $teamData, ?int $userTeamId): string
    {
        $isUserTeam = ($userTeamId !== null && $teamData['teamId'] === $userTeamId);
        $highlightClass = $isUserTeam ? ' class="highlight"' : '';
        
        $color1 = HtmlSanitizer::safeHtmlOutput($teamData['color1']);
        $color2 = HtmlSanitizer::safeHtmlOutput($teamData['color2']);
        $teamName = HtmlSanitizer::safeHtmlOutput($teamData['teamName']);
        $teamId = (int)$teamData['teamId'];

        $html = '<tr>';

        // Team name cell with logo - sticky column
        $html .= '<td class="ibl-team-cell--colored sticky-col" style="background-color: #' . $color1 . ';">';
        $html .= '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '&amp;display=contracts" ';
        $html .= 'class="ibl-team-cell__name" style="color: #' . $color2 . ';">';
        $html .= '<img src="images/logo/new' . $teamId . '.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">';
        $html .= '<span class="ibl-team-cell__text">' . $teamName . '</span></a>';
        $html .= '</td>';

        // Available salary columns
        $years = ['year1', 'year2', 'year3', 'year4', 'year5', 'year6'];
        foreach ($years as $year) {
            $html .= '<td' . $highlightClass . '>';
            $html .= HtmlSanitizer::safeHtmlOutput($teamData['availableSalary'][$year]);
            $html .= '</td>';
        }

        $html .= '<td class="divider"></td>';

        // Position salary columns
        foreach (\JSB::PLAYER_POSITIONS as $position) {
            $html .= '<td' . $highlightClass . '>';
            $html .= HtmlSanitizer::safeHtmlOutput($teamData['positionSalaries'][$position] ?? 0);
            $html .= '</td>';
        }

        $html .= '<td class="divider"></td>';

        // FA Slots
        $html .= '<td' . $highlightClass . '>';
        $html .= HtmlSanitizer::safeHtmlOutput($teamData['freeAgencySlots']);
        $html .= '</td>';

        // MLE/LLE icons
        $mleIcon = $teamData['hasMLE'] ? "\u{2705}" : "\u{274C}";
        $lleIcon = $teamData['hasLLE'] ? "\u{2705}" : "\u{274C}";

        $html .= '<td' . $highlightClass . '>' . $mleIcon . '</td>';
        $html .= '<td' . $highlightClass . '>' . $lleIcon . '</td>';

        $html .= '</tr>';

        return $html;
    }
}
