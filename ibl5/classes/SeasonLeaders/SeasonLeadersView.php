<?php

namespace SeasonLeaders;

/**
 * SeasonLeadersView - Handles HTML rendering for season leaders
 * 
 * Separates presentation logic from business logic.
 */
class SeasonLeadersView
{
    private $service;

    public function __construct(SeasonLeadersService $service)
    {
        $this->service = $service;
    }

    /**
     * Render the filter form
     * 
     * @param array $teams Teams query result
     * @param array $years Array of available years
     * @param array $currentFilters Current filter values
     * @return string HTML for the filter form
     */
    public function renderFilterForm($teams, array $years, array $currentFilters): string
    {
        $html = '<form name="Leaderboards" method="post" action="modules.php?name=Season_Leaders">';
        $html .= '<table border="1">';
        $html .= '<tr>';
        
        // Team selector
        $html .= '<td><b>Team</b></td>';
        $html .= '<td><select name="team">';
        $html .= $this->renderTeamOptions($teams, $currentFilters['team'] ?? 0);
        $html .= '</select></td>';
        
        // Year selector
        $html .= '<td><b>Year</b></td>';
        $html .= '<td><select name="year">';
        $html .= $this->renderYearOptions($years, $currentFilters['year'] ?? '');
        $html .= '</select></td>';
        
        // Sort by selector
        $html .= '<td><b>Sort By</b></td>';
        $html .= '<td><select name="sortby">';
        $html .= $this->renderSortOptions($currentFilters['sortby'] ?? '1');
        $html .= '</select></td>';
        
        // Submit button
        $html .= '<td><input type="submit" value="Search Season Data"></td>';
        $html .= '</tr></table>';
        $html .= '</form>';
        
        return $html;
    }

    /**
     * Render team dropdown options
     * 
     * @param resource $teams Database result with teams
     * @param int $selectedTeam Currently selected team ID
     * @return string HTML options
     */
    private function renderTeamOptions($teams, int $selectedTeam): string
    {
        global $db;
        
        $html = '<option value="0">All</option>';
        
        $numTeams = $db->sql_numrows($teams);
        for ($i = 0; $i < $numTeams; $i++) {
            $tid = $db->sql_result($teams, $i, "TeamID");
            $teamName = $db->sql_result($teams, $i, "Team");
            
            $selected = ($selectedTeam == $tid) ? ' SELECTED' : '';
            $html .= "<option value=\"$tid\"$selected>$teamName</option>";
        }
        
        return $html;
    }

    /**
     * Render year dropdown options
     * 
     * @param array $years Array of available years
     * @param string $selectedYear Currently selected year
     * @return string HTML options
     */
    private function renderYearOptions(array $years, string $selectedYear): string
    {
        $html = '<option value="">All</option>';
        
        foreach ($years as $year) {
            $selected = ($selectedYear == $year) ? ' SELECTED' : '';
            $html .= "<option value=\"$year\"$selected>$year</option>";
        }
        
        return $html;
    }

    /**
     * Render sort by dropdown options
     * 
     * @param string $selectedSort Currently selected sort option
     * @return string HTML options
     */
    private function renderSortOptions(string $selectedSort): string
    {
        $html = '';
        $sortOptions = $this->service->getSortOptions();
        
        $i = 1;
        foreach ($sortOptions as $label) {
            $selected = ($i == $selectedSort) ? ' SELECTED' : '';
            $html .= "<option value=\"$i\"$selected>$label</option>";
            $i++;
        }
        
        return $html;
    }

    /**
     * Render the statistics table header
     * 
     * @return string HTML table header
     */
    public function renderTableHeader(): string
    {
        $html = '<table cellpadding="3" cellspacing="0" border="0">';
        $html .= '<tr bgcolor="C2D69A">';
        $html .= '<td><b>Rank</b></td>';
        $html .= '<td><b>Year</b></td>';
        $html .= '<td><b>Name</b></td>';
        $html .= '<td><b>Team</b></td>';
        $html .= '<td><b>G</b></td>';
        $html .= '<td align="right"><b>Min</b></td>';
        $html .= '<td align="right"><b>fgm</b></td>';
        $html .= '<td align="right"><b>fga</b></td>';
        $html .= '<td align="right"><b>fg%</b></td>';
        $html .= '<td align="right"><b>ftm</b></td>';
        $html .= '<td align="right"><b>fta</b></td>';
        $html .= '<td align="right"><b>ft%</b></td>';
        $html .= '<td align="right"><b>tgm</b></td>';
        $html .= '<td align="right"><b>tga</b></td>';
        $html .= '<td align="right"><b>tg%</b></td>';
        $html .= '<td align="right"><b>orb</b></td>';
        $html .= '<td align="right"><b>reb</b></td>';
        $html .= '<td align="right"><b>ast</b></td>';
        $html .= '<td align="right"><b>stl</b></td>';
        $html .= '<td align="right"><b>to</b></td>';
        $html .= '<td align="right"><b>blk</b></td>';
        $html .= '<td align="right"><b>pf</b></td>';
        $html .= '<td align="right"><b>ppg</b></td>';
        $html .= '<td align="right"><b>qa</b></td>';
        $html .= '</tr>';
        
        return $html;
    }

    /**
     * Render a single player statistics row
     * 
     * @param array $stats Formatted player statistics
     * @param int $rank Player's rank in the leaderboard
     * @return string HTML table row
     */
    public function renderPlayerRow(array $stats, int $rank): string
    {
        $bgcolor = ($rank % 2 == 0) ? "FFFFFF" : "DDDDDD";
        
        $html = "<tr bgcolor=\"$bgcolor\">";
        $html .= "<td>$rank.</td>";
        $html .= "<td>{$stats['year']}</td>";
        $html .= "<td><a href=\"modules.php?name=Player&pa=showpage&pid={$stats['pid']}\">{$stats['name']}</a></td>";
        $html .= "<td><a href=\"modules.php?name=Team&op=team&teamID={$stats['teamid']}\">{$stats['teamname']}</a></td>";
        $html .= "<td>{$stats['games']}</td>";
        $html .= "<td align=\"right\">{$stats['mpg']}</td>";
        $html .= "<td align=\"right\">{$stats['fgmpg']}</td>";
        $html .= "<td align=\"right\">{$stats['fgapg']}</td>";
        $html .= "<td align=\"right\">{$stats['fgp']}</td>";
        $html .= "<td align=\"right\">{$stats['ftmpg']}</td>";
        $html .= "<td align=\"right\">{$stats['ftapg']}</td>";
        $html .= "<td align=\"right\">{$stats['ftp']}</td>";
        $html .= "<td align=\"right\">{$stats['tgmpg']}</td>";
        $html .= "<td align=\"right\">{$stats['tgapg']}</td>";
        $html .= "<td align=\"right\">{$stats['tgp']}</td>";
        $html .= "<td align=\"right\">{$stats['orbpg']}</td>";
        $html .= "<td align=\"right\">{$stats['rpg']}</td>";
        $html .= "<td align=\"right\">{$stats['apg']}</td>";
        $html .= "<td align=\"right\">{$stats['spg']}</td>";
        $html .= "<td align=\"right\">{$stats['tpg']}</td>";
        $html .= "<td align=\"right\">{$stats['bpg']}</td>";
        $html .= "<td align=\"right\">{$stats['fpg']}</td>";
        $html .= "<td align=\"right\">{$stats['ppg']}</td>";
        $html .= "<td align=\"right\">{$stats['qa']}</td>";
        $html .= "</tr>";
        
        return $html;
    }

    /**
     * Render the table footer
     * 
     * @return string HTML table closing tag
     */
    public function renderTableFooter(): string
    {
        return '</table>';
    }
}
