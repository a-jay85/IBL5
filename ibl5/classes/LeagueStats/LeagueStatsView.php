<?php

declare(strict_types=1);

namespace LeagueStats;

use LeagueStats\Contracts\LeagueStatsViewInterface;
use Utilities\HtmlSanitizer;

/**
 * View for rendering league-wide team statistics
 *
 * Generates HTML output with five sortable tables:
 * 1. Team Offense Totals
 * 2. Team Defense Totals
 * 3. Team Offense Averages
 * 4. Team Defense Averages
 * 5. Offense/Defense Differentials
 *
 * Uses HtmlSanitizer::safeHtmlOutput() for XSS protection on team names.
 *
 * @see LeagueStatsViewInterface for method documentation
 */
class LeagueStatsView implements LeagueStatsViewInterface
{
    /**
     * Render the complete league statistics display
     *
     * @see LeagueStatsViewInterface::render()
     * @param array $data Combined data structure
     * @param int $userTeamId The current user's team ID for row highlighting
     * @return string Complete HTML output
     */
    public function render(array $data, int $userTeamId): string
    {
        $teams = $data['teams'] ?? [];
        $league = $data['league'] ?? [];
        $differentials = $data['differentials'] ?? [];

        $html = '<center>';
        $html .= '<h1>League-wide Statistics</h1>';

        // Team Offense Totals
        $html .= '<h2>Team Offense Totals</h2>';
        $html .= $this->renderTotalsTable($teams, 'offense_totals', 'Offense', $userTeamId, $league['totals'] ?? []);

        // Team Defense Totals
        $html .= '<h2>Team Defense Totals</h2>';
        $html .= $this->renderTotalsTable($teams, 'defense_totals', 'Defense', $userTeamId, $league['totals'] ?? []);

        // Team Offense Averages
        $html .= '<h2>Team Offense Averages</h2>';
        $html .= $this->renderAveragesTable($teams, 'offense_averages', 'Offense', $userTeamId, $league['averages'] ?? []);

        // Team Defense Averages
        $html .= '<h2>Team Defense Averages</h2>';
        $html .= $this->renderAveragesTable($teams, 'defense_averages', 'Defense', $userTeamId, $league['averages'] ?? []);

        // Offense/Defense Differentials
        $html .= '<h2>Team Off/Def Average Differentials</h2>';
        $html .= $this->renderDifferentialsTable($differentials, $userTeamId);

        $html .= '</center>';

        return $html;
    }

    /**
     * Render a totals table (offense or defense)
     *
     * @param array $teams Processed team data
     * @param string $statsKey Key for stats array ('offense_totals' or 'defense_totals')
     * @param string $label Label suffix ('Offense' or 'Defense')
     * @param int $userTeamId User's team ID for highlighting
     * @param array $leagueTotals League totals for footer
     * @return string HTML table
     */
    private function renderTotalsTable(
        array $teams,
        string $statsKey,
        string $label,
        int $userTeamId,
        array $leagueTotals
    ): string {
        $html = '<table class="sortable">';
        $html .= '<thead>' . $this->getTotalsHeaderRow() . '</thead>';
        $html .= '<tbody>';

        foreach ($teams as $team) {
            $stats = $team[$statsKey] ?? [];
            $html .= $this->renderTotalsRow($team, $stats, $label, $userTeamId);
        }

        $html .= '</tbody>';
        $html .= '<tfoot>' . $this->renderLeagueTotalsRow($leagueTotals) . '</tfoot>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Render an averages table (offense or defense)
     *
     * @param array $teams Processed team data
     * @param string $statsKey Key for stats array ('offense_averages' or 'defense_averages')
     * @param string $label Label suffix ('Offense' or 'Defense')
     * @param int $userTeamId User's team ID for highlighting
     * @param array $leagueAverages League averages for footer
     * @return string HTML table
     */
    private function renderAveragesTable(
        array $teams,
        string $statsKey,
        string $label,
        int $userTeamId,
        array $leagueAverages
    ): string {
        $html = '<table class="sortable">';
        $html .= '<thead>' . $this->getAveragesHeaderRow() . '</thead>';
        $html .= '<tbody>';

        foreach ($teams as $team) {
            $stats = $team[$statsKey] ?? [];
            $html .= $this->renderAveragesRow($team, $stats, $label, $userTeamId);
        }

        $html .= '</tbody>';
        $html .= '<tfoot>' . $this->renderLeagueAveragesRow($leagueAverages) . '</tfoot>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Render the differentials table
     *
     * @param array $differentials Differential data for each team
     * @param int $userTeamId User's team ID for highlighting
     * @return string HTML table
     */
    private function renderDifferentialsTable(array $differentials, int $userTeamId): string
    {
        $html = '<table class="sortable">';
        $html .= '<thead>' . $this->getAveragesHeaderRow() . '</thead>';
        $html .= '<tbody>';

        foreach ($differentials as $team) {
            $html .= $this->renderDifferentialsRow($team, $userTeamId);
        }

        $html .= '</tbody>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Get the header row for totals tables
     *
     * @return string HTML header row
     */
    private function getTotalsHeaderRow(): string
    {
        return '<tr>
            <th>Team</th>
            <th>Gm</th>
            <th>FGM</th>
            <th>FGA</th>
            <th>FTM</th>
            <th>FTA</th>
            <th>3GM</th>
            <th>3GA</th>
            <th>ORB</th>
            <th>REB</th>
            <th>AST</th>
            <th>STL</th>
            <th>TVR</th>
            <th>BLK</th>
            <th>PF</th>
            <th>PTS</th>
        </tr>';
    }

    /**
     * Get the header row for averages tables
     *
     * @return string HTML header row
     */
    private function getAveragesHeaderRow(): string
    {
        return '<tr>
            <th>Team</th>
            <th>FGM</th>
            <th>FGA</th>
            <th>FGP</th>
            <th>FTM</th>
            <th>FTA</th>
            <th>FTP</th>
            <th>3GM</th>
            <th>3GA</th>
            <th>3GP</th>
            <th>ORB</th>
            <th>REB</th>
            <th>AST</th>
            <th>STL</th>
            <th>TVR</th>
            <th>BLK</th>
            <th>PF</th>
            <th>PTS</th>
        </tr>';
    }

    /**
     * Render a single totals row for a team
     *
     * @param array $team Team data
     * @param array $stats Stats array
     * @param string $label Label suffix
     * @param int $userTeamId User's team ID for highlighting
     * @return string HTML row
     */
    private function renderTotalsRow(array $team, array $stats, string $label, int $userTeamId): string
    {
        $trTag = $this->getRowTag($team['teamid'], $userTeamId);
        $teamCell = $this->renderTeamCell($team, $label);

        return "{$trTag}
            {$teamCell}
            <td>{$stats['games']}</td>
            <td>{$stats['fgm']}</td>
            <td>{$stats['fga']}</td>
            <td>{$stats['ftm']}</td>
            <td>{$stats['fta']}</td>
            <td>{$stats['tgm']}</td>
            <td>{$stats['tga']}</td>
            <td>{$stats['orb']}</td>
            <td>{$stats['reb']}</td>
            <td>{$stats['ast']}</td>
            <td>{$stats['stl']}</td>
            <td>{$stats['tvr']}</td>
            <td>{$stats['blk']}</td>
            <td>{$stats['pf']}</td>
            <td>{$stats['pts']}</td>
        </tr>";
    }

    /**
     * Render a single averages row for a team
     *
     * @param array $team Team data
     * @param array $stats Stats array
     * @param string $label Label suffix
     * @param int $userTeamId User's team ID for highlighting
     * @return string HTML row
     */
    private function renderAveragesRow(array $team, array $stats, string $label, int $userTeamId): string
    {
        $trTag = $this->getRowTag($team['teamid'], $userTeamId);
        $teamCell = $this->renderTeamCell($team, $label);

        return "{$trTag}
            {$teamCell}
            <td>{$stats['fgm']}</td>
            <td>{$stats['fga']}</td>
            <td>{$stats['fgp']}</td>
            <td>{$stats['ftm']}</td>
            <td>{$stats['fta']}</td>
            <td>{$stats['ftp']}</td>
            <td>{$stats['tgm']}</td>
            <td>{$stats['tga']}</td>
            <td>{$stats['tgp']}</td>
            <td>{$stats['orb']}</td>
            <td>{$stats['reb']}</td>
            <td>{$stats['ast']}</td>
            <td>{$stats['stl']}</td>
            <td>{$stats['tvr']}</td>
            <td>{$stats['blk']}</td>
            <td>{$stats['pf']}</td>
            <td>{$stats['pts']}</td>
        </tr>";
    }

    /**
     * Render a single differentials row for a team
     *
     * @param array $team Team differential data
     * @param int $userTeamId User's team ID for highlighting
     * @return string HTML row
     */
    private function renderDifferentialsRow(array $team, int $userTeamId): string
    {
        $trTag = $this->getRowTag($team['teamid'], $userTeamId);
        $teamCell = $this->renderTeamCell($team, 'Diff');
        $diffs = $team['differentials'];

        return "{$trTag}
            {$teamCell}
            <td>{$diffs['fgm']}</td>
            <td>{$diffs['fga']}</td>
            <td>{$diffs['fgp']}</td>
            <td>{$diffs['ftm']}</td>
            <td>{$diffs['fta']}</td>
            <td>{$diffs['ftp']}</td>
            <td>{$diffs['tgm']}</td>
            <td>{$diffs['tga']}</td>
            <td>{$diffs['tgp']}</td>
            <td>{$diffs['orb']}</td>
            <td>{$diffs['reb']}</td>
            <td>{$diffs['ast']}</td>
            <td>{$diffs['stl']}</td>
            <td>{$diffs['tvr']}</td>
            <td>{$diffs['blk']}</td>
            <td>{$diffs['pf']}</td>
            <td>{$diffs['pts']}</td>
        </tr>";
    }

    /**
     * Render the league totals footer row
     *
     * @param array $totals League totals
     * @return string HTML row
     */
    private function renderLeagueTotalsRow(array $totals): string
    {
        return '<tr style="font-weight:bold">
            <td>LEAGUE TOTALS</td>
            <td>' . ($totals['games'] ?? '0') . '</td>
            <td>' . ($totals['fgm'] ?? '0') . '</td>
            <td>' . ($totals['fga'] ?? '0') . '</td>
            <td>' . ($totals['ftm'] ?? '0') . '</td>
            <td>' . ($totals['fta'] ?? '0') . '</td>
            <td>' . ($totals['tgm'] ?? '0') . '</td>
            <td>' . ($totals['tga'] ?? '0') . '</td>
            <td>' . ($totals['orb'] ?? '0') . '</td>
            <td>' . ($totals['reb'] ?? '0') . '</td>
            <td>' . ($totals['ast'] ?? '0') . '</td>
            <td>' . ($totals['stl'] ?? '0') . '</td>
            <td>' . ($totals['tvr'] ?? '0') . '</td>
            <td>' . ($totals['blk'] ?? '0') . '</td>
            <td>' . ($totals['pf'] ?? '0') . '</td>
            <td>' . ($totals['pts'] ?? '0') . '</td>
        </tr>';
    }

    /**
     * Render the league averages footer row
     *
     * @param array $averages League averages
     * @return string HTML row
     */
    private function renderLeagueAveragesRow(array $averages): string
    {
        return '<tr style="font-weight:bold">
            <td>LEAGUE AVERAGES</td>
            <td>' . ($averages['fgm'] ?? '0.0') . '</td>
            <td>' . ($averages['fga'] ?? '0.0') . '</td>
            <td>' . ($averages['fgp'] ?? '0.000') . '</td>
            <td>' . ($averages['ftm'] ?? '0.0') . '</td>
            <td>' . ($averages['fta'] ?? '0.0') . '</td>
            <td>' . ($averages['ftp'] ?? '0.000') . '</td>
            <td>' . ($averages['tgm'] ?? '0.0') . '</td>
            <td>' . ($averages['tga'] ?? '0.0') . '</td>
            <td>' . ($averages['tgp'] ?? '0.000') . '</td>
            <td>' . ($averages['orb'] ?? '0.0') . '</td>
            <td>' . ($averages['reb'] ?? '0.0') . '</td>
            <td>' . ($averages['ast'] ?? '0.0') . '</td>
            <td>' . ($averages['stl'] ?? '0.0') . '</td>
            <td>' . ($averages['tvr'] ?? '0.0') . '</td>
            <td>' . ($averages['blk'] ?? '0.0') . '</td>
            <td>' . ($averages['pf'] ?? '0.0') . '</td>
            <td>' . ($averages['pts'] ?? '0.0') . '</td>
        </tr>';
    }

    /**
     * Get the opening TR tag with optional highlighting
     *
     * @param int $teamId Current team's ID
     * @param int $userTeamId User's team ID
     * @return string HTML TR tag
     */
    private function getRowTag(int $teamId, int $userTeamId): string
    {
        if ($teamId === $userTeamId) {
            return '<tr bgcolor="#FFA" align="right">';
        }
        return '<tr align="right">';
    }

    /**
     * Render the team name cell with link and colors
     *
     * @param array $team Team data
     * @param string $label Label suffix ('Offense', 'Defense', or 'Diff')
     * @return string HTML TD element
     */
    private function renderTeamCell(array $team, string $label): string
    {
        $teamId = (int) $team['teamid'];
        $city = HtmlSanitizer::safeHtmlOutput($team['team_city']);
        $name = HtmlSanitizer::safeHtmlOutput($team['team_name']);
        $color1 = HtmlSanitizer::safeHtmlOutput($team['color1']);
        $color2 = HtmlSanitizer::safeHtmlOutput($team['color2']);

        return '<td bgcolor="' . $color1 . '">
            <a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamId . '">
                <font color="' . $color2 . '">' . $city . ' ' . $name . ' ' . $label . '</font>
            </a>
        </td>';
    }
}
