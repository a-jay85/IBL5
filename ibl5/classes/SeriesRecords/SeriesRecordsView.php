<?php

declare(strict_types=1);

namespace SeriesRecords;

use SeriesRecords\Contracts\SeriesRecordsViewInterface;
use Utilities\HtmlSanitizer;

/**
 * SeriesRecordsView - View rendering for series records
 * 
 * Handles all HTML generation for the series records grid table display.
 * Uses HtmlSanitizer for XSS protection on all output.
 * 
 * @see SeriesRecordsViewInterface
 */
class SeriesRecordsView implements SeriesRecordsViewInterface
{
    private SeriesRecordsService $service;

    public function __construct(SeriesRecordsService $service)
    {
        $this->service = $service;
    }

    /**
     * @see SeriesRecordsViewInterface::renderSeriesRecordsTable()
     */
    public function renderSeriesRecordsTable(
        array $teams,
        array $seriesMatrix,
        int $userTeamId,
        int $numTeams
    ): string {
        $output = '<table class="sortable ibl-data-table">';

        // Header row with team logos
        $output .= '<tr>';
        $output .= '<th>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&rarr;&rarr;<br>vs.<br>&uarr;</th>';

        for ($i = 1; $i <= $numTeams; $i++) {
            $output .= $this->renderHeaderCell($i);
        }
        $output .= '</tr>';

        // Data rows - one per team
        $teamIndex = 0;
        for ($rowTeamId = 1; $rowTeamId <= $numTeams; $rowTeamId++) {
            // Get team data for this row
            $team = $teams[$teamIndex] ?? null;

            // Skip if team data doesn't match expected team ID
            if ($team && (int) $team['teamid'] !== $rowTeamId) {
                // Team ID mismatch - might be skipped team, output empty row
                $output .= $this->renderEmptyRow($rowTeamId, $numTeams, $userTeamId);
                continue;
            }

            if (!$team) {
                $output .= $this->renderEmptyRow($rowTeamId, $numTeams, $userTeamId);
                continue;
            }

            $isUserTeamRow = ($userTeamId === $rowTeamId);

            $output .= '<tr>';
            $output .= $this->renderTeamNameCell($team, $isUserTeamRow);

            // Render record cells for each opponent
            for ($colTeamId = 1; $colTeamId <= $numTeams; $colTeamId++) {
                if ($rowTeamId === $colTeamId) {
                    // Diagonal cell - team vs itself
                    $output .= $this->renderDiagonalCell($isUserTeamRow);
                } else {
                    $record = $this->service->getRecordFromMatrix($seriesMatrix, $rowTeamId, $colTeamId);
                    $wins = $record['wins'];
                    $losses = $record['losses'];
                    $bgColor = $this->service->getRecordBackgroundColor($wins, $losses);
                    $isBold = ($isUserTeamRow || $userTeamId === $colTeamId);

                    $output .= $this->renderRecordCell($wins, $losses, $bgColor, $isBold);
                }
            }

            $output .= '</tr>';
            $teamIndex++;
        }

        $output .= '</table>';

        return $output;
    }

    /**
     * @see SeriesRecordsViewInterface::renderHeaderCell()
     */
    public function renderHeaderCell(int $teamId): string
    {
        $safeTeamId = HtmlSanitizer::safeHtmlOutput((string)$teamId);
        return '<th class="text-center"><img src="images/logo/new' . $safeTeamId . '.png" width="50" height="50" alt="Team ' . $safeTeamId . ' logo"></th>';
    }

    /**
     * @see SeriesRecordsViewInterface::renderTeamNameCell()
     */
    public function renderTeamNameCell(array $team, bool $isUserTeam): string
    {
        $teamId = HtmlSanitizer::safeHtmlOutput((string)$team['teamid']);
        $color1 = HtmlSanitizer::safeHtmlOutput($team['color1']);
        $color2 = HtmlSanitizer::safeHtmlOutput($team['color2']);
        $city = HtmlSanitizer::safeHtmlOutput($team['team_city']);
        $name = HtmlSanitizer::safeHtmlOutput($team['team_name']);

        $boldOpen = $isUserTeam ? '<strong>' : '';
        $boldClose = $isUserTeam ? '</strong>' : '';

        return '<td class="ibl-team-cell--colored" style="background-color: #' . $color1 . ';">'
            . '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '" class="ibl-team-cell__name" style="color: #' . $color2 . ';">'
            . '<img src="images/logo/new' . $teamId . '.png" alt="" class="ibl-team-cell__logo" width="24" height="24" loading="lazy">'
            . '<span class="ibl-team-cell__text">' . $boldOpen . $city . ' ' . $name . $boldClose . '</span>'
            . '</a></td>';
    }

    /**
     * @see SeriesRecordsViewInterface::renderRecordCell()
     */
    public function renderRecordCell(int $wins, int $losses, string $backgroundColor, bool $isBold): string
    {
        $safeWins = HtmlSanitizer::safeHtmlOutput((string)$wins);
        $safeLosses = HtmlSanitizer::safeHtmlOutput((string)$losses);
        $safeBgColor = HtmlSanitizer::safeHtmlOutput($backgroundColor);

        $boldOpen = $isBold ? '<strong>' : '';
        $boldClose = $isBold ? '</strong>' : '';

        return '<td class="text-center" style="background-color: ' . $safeBgColor . ';">'
            . $boldOpen . $safeWins . ' - ' . $safeLosses . $boldClose
            . '</td>';
    }

    /**
     * @see SeriesRecordsViewInterface::renderDiagonalCell()
     */
    public function renderDiagonalCell(bool $isUserTeam): string
    {
        $boldOpen = $isUserTeam ? '<strong>' : '';
        $boldClose = $isUserTeam ? '</strong>' : '';

        return '<td class="text-center">' . $boldOpen . 'x' . $boldClose . '</td>';
    }

    /**
     * Render an empty row for missing team data
     * 
     * @param int $teamId The team ID for this row
     * @param int $numTeams Total number of teams
     * @param int $userTeamId The user's team ID for highlighting
     * @return string HTML for the empty row
     */
    private function renderEmptyRow(int $teamId, int $numTeams, int $userTeamId): string
    {
        $isUserTeamRow = ($userTeamId === $teamId);
        $boldOpen = $isUserTeamRow ? '<strong>' : '';
        $boldClose = $isUserTeamRow ? '</strong>' : '';

        $output = '<tr>';
        $output .= '<td>' . $boldOpen . 'Team ' . $teamId . $boldClose . '</td>';

        for ($i = 1; $i <= $numTeams; $i++) {
            if ($teamId === $i) {
                $output .= '<td class="text-center">' . $boldOpen . 'x' . $boldClose . '</td>';
            } else {
                $output .= '<td class="text-center">0 - 0</td>';
            }
        }

        $output .= '</tr>';
        return $output;
    }
}
