<?php

declare(strict_types=1);

namespace Standings;

use Standings\Contracts\StandingsRepositoryInterface;
use Standings\Contracts\StandingsViewInterface;

/**
 * StandingsView - HTML rendering for team standings
 *
 * Generates sortable HTML tables for conference and division standings.
 * Handles clinched indicators (X/Y/Z) and team streak display.
 *
 * @see StandingsViewInterface For the interface contract
 * @see StandingsRepository For data access
 */
class StandingsView implements StandingsViewInterface
{
    private StandingsRepositoryInterface $repository;

    /**
     * Constructor
     *
     * @param StandingsRepositoryInterface $repository Standings data repository
     */
    public function __construct(StandingsRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function render(): string
    {
        $html = '<script src="sorttable.js"></script>';
        $html .= $this->getStyleBlock();

        // Conference standings
        $html .= $this->renderRegion('Eastern');
        $html .= $this->renderRegion('Western');
        $html .= '<p>';

        // Division standings
        $html .= $this->renderRegion('Atlantic');
        $html .= $this->renderRegion('Central');
        $html .= $this->renderRegion('Midwest');
        $html .= $this->renderRegion('Pacific');

        return $html;
    }

    /**
     * Generate consolidated CSS styles for standings tables
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
            .standings-title {
                color: #fd004d;
                font-weight: bold;
            }
            
            .standings-table {
                border-collapse: collapse;
            }
            
            .standings-header-row {
                background-color: #006cb3;
            }
            
            .standings-header-cell {
                text-align: center;
                color: #ffffff;
                font-weight: bold;
            }
            
            .standings-cell {
                text-align: center;
            }
            
            .standings-team-cell {
                text-align: left;
            }
            
            .standings-divider {
                text-align: center;
            }
        </style>';
    }

    /**
     * {@inheritdoc}
     */
    public function renderRegion(string $region): string
    {
        $groupingType = $this->getGroupingType($region);
        $standings = $this->repository->getStandingsByRegion($region);

        $html = $this->renderHeader($region, $groupingType);
        $html .= $this->renderRows($standings);
        $html .= '<tr><td class="standings-divider" colspan="14"><hr></td></tr></table><p>';

        return $html;
    }

    /**
     * Get the grouping type (Conference or Division) for a region
     *
     * @param string $region Region name
     * @return string 'Conference' or 'Division'
     */
    private function getGroupingType(string $region): string
    {
        if (in_array($region, \League::CONFERENCE_NAMES, true)) {
            return 'Conference';
        }

        return 'Division';
    }

    /**
     * Render the table header for a standings section
     *
     * @param string $region Region name
     * @param string $groupingType 'Conference' or 'Division'
     * @return string HTML for table header
     */
    private function renderHeader(string $region, string $groupingType): string
    {
        $title = htmlspecialchars($region . ' ' . $groupingType, ENT_QUOTES, 'UTF-8');

        ob_start();
        ?>
        <div class="standings-title"><?php echo $title; ?></div>
        <table class="sortable standings-table">
            <tr class="standings-header-row">
            <td class="standings-header-cell">Team</td>
            <td class="standings-header-cell">W-L</td>
            <td class="standings-header-cell">Pct</td>
            <td class="standings-header-cell">GB</td>
            <td class="standings-header-cell">Magic#</td>
            <td class="standings-header-cell">Left</td>
            <td class="standings-header-cell">Conf.</td>
            <td class="standings-header-cell">Div.</td>
            <td class="standings-header-cell">Home</td>
            <td class="standings-header-cell">Away</td>
            <td class="standings-header-cell">Home<br>Played</td>
            <td class="standings-header-cell">Away<br>Played</td>
            <td class="standings-header-cell">Last 10</td>
            <td class="standings-header-cell">Streak</td>
            </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Render all team rows for a standings table
     *
     * @param array $standings Array of team standings data
     * @return string HTML for all team rows
     */
    private function renderRows(array $standings): string
    {
        $html = '';

        foreach ($standings as $team) {
            $html .= $this->renderTeamRow($team);
        }

        return $html;
    }

    /**
     * Render a single team row
     *
     * @param array $team Team standings data
     * @return string HTML for team row
     */
    private function renderTeamRow(array $team): string
    {
        $teamId = (int) $team['tid'];
        $teamName = $this->formatTeamName($team);
        $streakData = $this->repository->getTeamStreakData($teamId);

        $lastWin = $streakData['last_win'] ?? 0;
        $lastLoss = $streakData['last_loss'] ?? 0;
        $streakType = htmlspecialchars($streakData['streak_type'] ?? '', ENT_QUOTES, 'UTF-8');
        $streak = $streakData['streak'] ?? 0;

        return '<tr>'
            . '<td class="standings-team-cell"><a href="modules.php?name=Team&op=team&teamID=' . $teamId . '">' . $teamName . '</a></td>'
            . '<td class="standings-cell">' . htmlspecialchars($team['leagueRecord'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="standings-cell">' . htmlspecialchars((string) $team['pct'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="standings-cell">' . htmlspecialchars((string) $team['gamesBack'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="standings-cell">' . htmlspecialchars((string) $team['magicNumber'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="standings-cell">' . htmlspecialchars((string) $team['gamesUnplayed'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="standings-cell">' . htmlspecialchars($team['confRecord'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="standings-cell">' . htmlspecialchars($team['divRecord'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="standings-cell">' . htmlspecialchars($team['homeRecord'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="standings-cell">' . htmlspecialchars($team['awayRecord'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="standings-cell">' . htmlspecialchars((string) $team['homeGames'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="standings-cell">' . htmlspecialchars((string) $team['awayGames'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="standings-cell">' . $lastWin . '-' . $lastLoss . '</td>'
            . '<td class="standings-cell">' . $streakType . ' ' . $streak . '</td>'
            . '</tr>';
    }

    /**
     * Format team name with clinched indicator
     *
     * @param array $team Team standings data
     * @return string Formatted team name with clinched prefix if applicable
     */
    private function formatTeamName(array $team): string
    {
        $teamName = htmlspecialchars($team['team_name'], ENT_QUOTES, 'UTF-8');

        if ($team['clinchedConference'] == 1) {
            return '<b>Z</b>-' . $teamName;
        }

        if ($team['clinchedDivision'] == 1) {
            return '<b>Y</b>-' . $teamName;
        }

        if ($team['clinchedPlayoffs'] == 1) {
            return '<b>X</b>-' . $teamName;
        }

        return $teamName;
    }
}
