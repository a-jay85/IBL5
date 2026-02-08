<?php

declare(strict_types=1);

namespace TeamOffDefStats;

use TeamOffDefStats\Contracts\TeamOffDefStatsViewInterface;
use UI\TeamCellHelper;
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
 * @see TeamOffDefStatsViewInterface for method documentation
 *
 * @phpstan-import-type RenderData from Contracts\TeamOffDefStatsViewInterface
 * @phpstan-import-type ProcessedTeamStats from Contracts\TeamOffDefStatsServiceInterface
 * @phpstan-import-type LeagueTotals from Contracts\TeamOffDefStatsServiceInterface
 * @phpstan-import-type DifferentialTeam from Contracts\TeamOffDefStatsServiceInterface
 * @phpstan-import-type FormattedStatTotals from Contracts\TeamOffDefStatsServiceInterface
 * @phpstan-import-type FormattedStatAverages from Contracts\TeamOffDefStatsServiceInterface
 * @phpstan-import-type DifferentialStats from Contracts\TeamOffDefStatsServiceInterface
 */
class TeamOffDefStatsView implements TeamOffDefStatsViewInterface
{
    /**
     * Render the complete league statistics display
     *
     * @see TeamOffDefStatsViewInterface::render()
     * @param RenderData $data Combined data structure
     * @return string Complete HTML output
     */
    public function render(array $data): string
    {
        $teams = $data['teams'] ?? [];
        $league = $data['league'] ?? [];
        $differentials = $data['differentials'] ?? [];

        $html = '<div class="league-stats-container">';
        $html .= '<h2 class="ibl-title">League-wide Statistics</h1>';

        // Team Offense Totals
        $html .= '<h2 class="ibl-table-title">Team Offense Totals</h2>';
        $html .= $this->renderTotalsTable($teams, 'offense_totals', 'Offense', $league['totals'] ?? []);

        // Team Defense Totals
        $html .= '<h2 class="ibl-table-title">Team Defense Totals</h2>';
        $html .= $this->renderTotalsTable($teams, 'defense_totals', 'Defense', $league['totals'] ?? []);

        // Team Offense Averages
        $html .= '<h2 class="ibl-table-title">Team Offense Averages</h2>';
        $html .= $this->renderAveragesTable($teams, 'offense_averages', 'Offense', $league['averages'] ?? []);

        // Team Defense Averages
        $html .= '<h2 class="ibl-table-title">Team Defense Averages</h2>';
        $html .= $this->renderAveragesTable($teams, 'defense_averages', 'Defense', $league['averages'] ?? []);

        // Offense/Defense Differentials
        $html .= '<h2 class="ibl-table-title">Team Off/Def Average Differentials</h2>';
        $html .= $this->renderDifferentialsTable($differentials);

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a totals table (offense or defense)
     *
     * @param list<ProcessedTeamStats> $teams Processed team data
     * @param string $statsKey Key for stats array ('offense_totals' or 'defense_totals')
     * @param string $label Label suffix ('Offense' or 'Defense')
     * @param FormattedStatTotals $leagueTotals League totals for footer
     * @return string HTML table
     */
    private function renderTotalsTable(
        array $teams,
        string $statsKey,
        string $label,
        array $leagueTotals
    ): string {
        $html = '<table class="sortable league-stats-table ibl-data-table">';
        $html .= '<thead>' . $this->getTotalsHeaderRow() . '</thead>';
        $html .= '<tbody>';

        foreach ($teams as $team) {
            /** @var FormattedStatTotals $stats */
            $stats = $team[$statsKey] ?? [];
            $html .= $this->renderTotalsRow($team, $stats, $label);
        }

        $html .= '</tbody>';
        $html .= '<tfoot>' . $this->renderLeagueTotalsRow($leagueTotals) . '</tfoot>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Render an averages table (offense or defense)
     *
     * @param list<ProcessedTeamStats> $teams Processed team data
     * @param string $statsKey Key for stats array ('offense_averages' or 'defense_averages')
     * @param string $label Label suffix ('Offense' or 'Defense')
     * @param FormattedStatAverages $leagueAverages League averages for footer
     * @return string HTML table
     */
    private function renderAveragesTable(
        array $teams,
        string $statsKey,
        string $label,
        array $leagueAverages
    ): string {
        $html = '<table class="sortable league-stats-table ibl-data-table">';
        $html .= '<thead>' . $this->getAveragesHeaderRow() . '</thead>';
        $html .= '<tbody>';

        foreach ($teams as $team) {
            /** @var FormattedStatAverages $stats */
            $stats = $team[$statsKey] ?? [];
            $html .= $this->renderAveragesRow($team, $stats, $label);
        }

        $html .= '</tbody>';
        $html .= '<tfoot>' . $this->renderLeagueAveragesRow($leagueAverages) . '</tfoot>';
        $html .= '</table>';

        return $html;
    }

    /**
     * Render the differentials table
     *
     * @param list<DifferentialTeam> $differentials Differential data for each team
     * @return string HTML table
     */
    private function renderDifferentialsTable(array $differentials): string
    {
        $html = '<table class="sortable league-stats-table ibl-data-table">';
        $html .= '<thead>' . $this->getAveragesHeaderRow() . '</thead>';
        $html .= '<tbody>';

        foreach ($differentials as $team) {
            $html .= $this->renderDifferentialsRow($team);
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
            <th class="ibl-team-cell--colored">Team</th>
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
            <th class="ibl-team-cell--colored">Team</th>
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
     * @param ProcessedTeamStats $team Team data
     * @param FormattedStatTotals $stats Stats array
     * @param string $label Label suffix
     * @return string HTML row
     */
    private function renderTotalsRow(array $team, array $stats, string $label): string
    {
        $teamId = (int) $team['teamid'];
        $teamCell = $this->renderTeamCell($team, $label);

        return "<tr data-team-id=\"{$teamId}\">
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
     * @param ProcessedTeamStats $team Team data
     * @param FormattedStatAverages $stats Stats array
     * @param string $label Label suffix
     * @return string HTML row
     */
    private function renderAveragesRow(array $team, array $stats, string $label): string
    {
        $teamId = (int) $team['teamid'];
        $teamCell = $this->renderTeamCell($team, $label);

        return "<tr data-team-id=\"{$teamId}\">
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
     * @param DifferentialTeam $team Team differential data
     * @return string HTML row
     */
    private function renderDifferentialsRow(array $team): string
    {
        $teamId = (int) $team['teamid'];
        $teamCell = $this->renderTeamCell($team, 'Diff');
        $diffs = $team['differentials'];

        return "<tr data-team-id=\"{$teamId}\">
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
     * @param FormattedStatTotals $totals League totals
     * @return string HTML row
     */
    private function renderLeagueTotalsRow(array $totals): string
    {
        return '<tr style="font-weight:bold">
            <td></td>
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
     * @param FormattedStatAverages $averages League averages
     * @return string HTML row
     */
    private function renderLeagueAveragesRow(array $averages): string
    {
        return '<tr style="font-weight:bold">
            <td></td>
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
     * Render the team name cell with link and colors
     *
     * @param array{teamid: int, team_city: string, team_name: string, color1: string, color2: string, ...} $team Team data
     * @param string $label Label suffix ('Offense', 'Defense', or 'Diff')
     * @return string HTML TD element
     */
    private function renderTeamCell(array $team, string $label): string
    {
        $teamId = (int) $team['teamid'];
        /** @var string $name */
        $name = HtmlSanitizer::safeHtmlOutput($team['team_name']);
        /** @var string $safeLabel */
        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
        $nameHtml = $name . ' ' . $safeLabel;

        return TeamCellHelper::renderTeamCell($teamId, $team['team_name'], $team['color1'], $team['color2'], '', '', $nameHtml);
    }
}
