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
     * {@inheritdoc}
     */
    public function renderRegion(string $region): string
    {
        $groupingType = $this->getGroupingType($region);
        $standings = $this->repository->getStandingsByRegion($region);

        $html = $this->renderHeader($region, $groupingType);
        $html .= $this->renderRows($standings);
        $html .= '<tr><td colspan=10><hr></td></tr></table><p>';

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

        return '<font color=#fd004d><b>' . $title . '</b></font>'
            . '<table class="sortable">'
            . '<tr bgcolor=#006cb3>'
            . '<td><font color=#ffffff><b>Team</b></font></td>'
            . '<td><font color=#ffffff><b>W-L</b></font></td>'
            . '<td><font color=#ffffff><b>Pct</b></font></td>'
            . '<td><center><font color=#ffffff><b>GB</b></font></center></td>'
            . '<td><center><font color=#ffffff><b>Magic#</b></font></center></td>'
            . '<td><font color=#ffffff><b>Left</b></font></td>'
            . '<td><font color=#ffffff><b>Conf.</b></font></td>'
            . '<td><font color=#ffffff><b>Div.</b></font></td>'
            . '<td><font color=#ffffff><b>Home</b></font></td>'
            . '<td><font color=#ffffff><b>Away</b></font></td>'
            . '<td><center><font color=#ffffff><b>Home<br>Played</b></font></center></td>'
            . '<td><center><font color=#ffffff><b>Away<br>Played</b></font></center></td>'
            . '<td><font color=#ffffff><b>Last 10</b></font></td>'
            . '<td><font color=#ffffff><b>Streak</b></font></td>'
            . '</tr>';
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
            . '<td><a href="modules.php?name=Team&op=team&teamID=' . $teamId . '">' . $teamName . '</td>'
            . '<td>' . htmlspecialchars($team['leagueRecord'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars((string) $team['pct'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td><center>' . htmlspecialchars((string) $team['gamesBack'], ENT_QUOTES, 'UTF-8') . '</center></td>'
            . '<td><center>' . htmlspecialchars((string) $team['magicNumber'], ENT_QUOTES, 'UTF-8') . '</center></td>'
            . '<td>' . htmlspecialchars((string) $team['gamesUnplayed'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($team['confRecord'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($team['divRecord'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($team['homeRecord'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars($team['awayRecord'], ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td><center>' . htmlspecialchars((string) $team['homeGames'], ENT_QUOTES, 'UTF-8') . '</center></td>'
            . '<td><center>' . htmlspecialchars((string) $team['awayGames'], ENT_QUOTES, 'UTF-8') . '</center></td>'
            . '<td>' . $lastWin . '-' . $lastLoss . '</td>'
            . '<td>' . $streakType . ' ' . $streak . '</td>'
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
