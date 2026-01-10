<?php

declare(strict_types=1);

namespace TeamSchedule;

use TeamSchedule\Contracts\TeamScheduleViewInterface;
use Utilities\HtmlSanitizer;

/**
 * TeamScheduleView - HTML rendering for team schedule
 *
 * Generates HTML table displaying a team's game schedule with results.
 *
 * @see TeamScheduleViewInterface For the interface contract
 */
class TeamScheduleView implements TeamScheduleViewInterface
{
    /**
     * @see TeamScheduleViewInterface::render()
     */
    public function render(\Team $team, array $games, int $simLengthInDays): string
    {
        $html = $this->getStyleBlock();
        $html .= $this->renderTeamLogo($team);
        $html .= $this->renderTableStart($team, $simLengthInDays);
        $html .= $this->renderGameRows($games, $team);
        $html .= '</table>';

        return $html;
    }

    /**
     * Generate CSS styles for the schedule table
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
            .schedule-table {
                width: 400px;
                border: 1px solid #000;
                border-collapse: collapse;
            }
            .schedule-table td, .schedule-table th {
                border: 1px solid #000;
                padding: 4px;
            }
            .schedule-header {
                text-align: center;
            }
            .schedule-month-header {
                font-weight: bold;
                text-align: center;
            }
            .schedule-column-header {
                font-weight: bold;
            }
            .next-sim-highlight {
                background-color: #DDDD00;
            }
            a.game-result-win {
                color: green !important;
                font-weight: bold;
                font-family: monospace, monospace;
            }
            a.game-result-loss {
                color: red !important;
                font-weight: bold;
                font-family: monospace, monospace;
            }
            .monospace {
                font-family: monospace, monospace;
            }
        </style>';
    }

    /**
     * Render team logo
     *
     * @param \Team $team Team object
     * @return string HTML for team logo
     */
    private function renderTeamLogo(\Team $team): string
    {
        $teamId = (int)$team->teamID;
        return '<div style="text-align: center;">
            <img src="./images/logo/' . $teamId . '.jpg" alt="Team Logo">
        </div>';
    }

    /**
     * Render table start with header
     *
     * @param \Team $team Team object
     * @param int $simLengthInDays Sim length in days
     * @return string HTML for table start
     */
    private function renderTableStart(\Team $team, int $simLengthInDays): string
    {
        $color1 = HtmlSanitizer::safeHtmlOutput($team->color1);
        $color2 = HtmlSanitizer::safeHtmlOutput($team->color2);

        return '<table class="schedule-table" align="center">
            <tr style="background-color: #' . $color1 . '; color: #' . $color2 . ';">
                <td colspan="5" class="schedule-header">
                    <h1>Team Schedule</h1>
                    <p><em style="font-style: italic;">games highlighted in yellow are projected to be run next sim (' . 
                    HtmlSanitizer::safeHtmlOutput($simLengthInDays) . ' days)</em></p>
                </td>
            </tr>';
    }

    /**
     * Render all game rows
     *
     * @param array $games Game data
     * @param \Team $team Team object
     * @return string HTML for game rows
     */
    private function renderGameRows(array $games, \Team $team): string
    {
        $html = '';
        $lastMonth = '';

        foreach ($games as $row) {
            $currentMonth = $row['currentMonth'];

            // Add month header if new month
            if ($currentMonth !== $lastMonth) {
                $html .= $this->renderMonthHeader($currentMonth, $team);
                $html .= $this->renderColumnHeaders($team);
                $lastMonth = $currentMonth;
            }

            $html .= $this->renderGameRow($row);
        }

        return $html;
    }

    /**
     * Render month header row
     *
     * @param string $month Month name
     * @param \Team $team Team object
     * @return string HTML for month header
     */
    private function renderMonthHeader(string $month, \Team $team): string
    {
        $color1 = HtmlSanitizer::safeHtmlOutput($team->color1);
        $color2 = HtmlSanitizer::safeHtmlOutput($team->color2);
        $safeMonth = HtmlSanitizer::safeHtmlOutput($month);

        return '<tr style="background-color: #' . $color1 . '; color: #' . $color2 . ';" class="schedule-month-header">
            <td colspan="5">' . $safeMonth . '</td>
        </tr>';
    }

    /**
     * Render column header row
     *
     * @param \Team $team Team object
     * @return string HTML for column headers
     */
    private function renderColumnHeaders(\Team $team): string
    {
        $color1 = HtmlSanitizer::safeHtmlOutput($team->color1);
        $color2 = HtmlSanitizer::safeHtmlOutput($team->color2);

        return '<tr style="background-color: #' . $color1 . '; color: #' . $color2 . ';" class="schedule-column-header">
            <td>Date</td>
            <td>Opponent</td>
            <td>Result</td>
            <td>W-L</td>
            <td>Streak</td>
        </tr>';
    }

    /**
     * Render a single game row
     *
     * @param array $row Game data
     * @return string HTML for game row
     */
    private function renderGameRow(array $row): string
    {
        /** @var \Game $game */
        $game = $row['game'];
        /** @var \Team $opposingTeam */
        $opposingTeam = $row['opposingTeam'];

        $highlightClass = ($row['highlight'] === 'next-sim') ? ' class="next-sim-highlight"' : '';
        $opponentTeamId = (int)$opposingTeam->teamID;
        $opponentText = HtmlSanitizer::safeHtmlOutput($row['opponentText']);

        if ($row['isUnplayed']) {
            // Unplayed game
            return '<tr' . $highlightClass . '>
                <td>' . HtmlSanitizer::safeHtmlOutput($game->date) . '</td>
                <td><a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $opponentTeamId . '">' . 
                    $opponentText . '</a></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>';
        }

        // Played game
        $resultClass = ($row['winLossColor'] === 'green') ? 'game-result-win' : 'game-result-loss';
        $score = HtmlSanitizer::safeHtmlOutput($row['gameResult'] . ' ' . $game->visitorScore . ' - ' . $game->homeScore);

        return '<tr style="background-color: #FFFFFF;">
            <td><a href="./ibl/IBL/box' . (int)$game->boxScoreID . '.htm">' . 
                HtmlSanitizer::safeHtmlOutput($game->date) . '</a></td>
            <td><a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $opponentTeamId . '">' . 
                $opponentText . '</a></td>
            <td><a href="./ibl/IBL/box' . (int)$game->boxScoreID . '.htm" class="' . $resultClass . '">' . 
                $score . '</a></td>
            <td class="monospace">' . HtmlSanitizer::safeHtmlOutput($row['wins'] . '-' . $row['losses']) . '</td>
            <td class="monospace">' . HtmlSanitizer::safeHtmlOutput($row['streak']) . '</td>
        </tr>';
    }
}
