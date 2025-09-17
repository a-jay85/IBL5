<?php
namespace Updater;

class StandingsHTMLGenerator {
    private $db;
    private $standingsHTML;

    public function __construct($db) {
        $this->db = $db;
        $this->standingsHTML = "<script src=\"sorttable.js\"></script>";
    }

    private function assignGroupingsFor($region) {
        if (in_array($region, array("Eastern", "Western"))) {
            $grouping = 'conference';
            $groupingGB = 'confGB';
            $groupingMagicNumber = 'confMagicNumber';
        }
        if (in_array($region, array("Atlantic", "Central", "Midwest", "Pacific"))) {
            $grouping = 'division';
            $groupingGB = 'divGB';
            $groupingMagicNumber = 'divMagicNumber';
        }
        return array($grouping, $groupingGB, $groupingMagicNumber);
    }

    public function generateStandingsPage() {
        echo '<p>Updating the Standings page...<p>';
        
        $this->displayStandings('Eastern');
        $this->displayStandings('Western');
        $this->standingsHTML .= '<p>';

        $this->displayStandings('Atlantic');
        $this->displayStandings('Central');
        $this->displayStandings('Midwest');
        $this->displayStandings('Pacific');

        $sqlQueryString = "UPDATE nuke_pages SET text = '$this->standingsHTML' WHERE pid = 4";
        if ($this->db->sql_query($sqlQueryString)) {
            \UI::displayDebugOutput($sqlQueryString, 'Standings HTML SQL Query');
            echo '<p>Full standings page has been updated.<p>';
        } else {
            die('Invalid query: ' . $this->db->sql_error());
        }
    }

    private function displayStandings($region) {
        list($grouping, $groupingGB, $groupingMagicNumber) = $this->assignGroupingsFor($region);

        $query = "SELECT tid, team_name, leagueRecord, pct, $groupingGB, confRecord, divRecord, homeRecord, awayRecord, gamesUnplayed, $groupingMagicNumber, clinchedConference, clinchedDivision, clinchedPlayoffs, (homeWins + homeLosses) AS homeGames, (awayWins + awayLosses) AS awayGames
            FROM ibl_standings
            WHERE $grouping = '$region' ORDER BY $groupingGB ASC";
        
        $result = $this->db->sql_query($query);
        $limit = $this->db->sql_numrows($result);

        $this->standingsHTML .= $this->generateStandingsHeader($region, $grouping);
        $this->standingsHTML .= $this->generateStandingsRows($result, $limit);
        $this->standingsHTML .= '<tr><td colspan=10><hr></td></tr></table><p>';
    }

    private function generateStandingsHeader($region, $grouping) {
        $html = '<font color=#fd004d><b>' . $region . ' ' . ucfirst($grouping) . '</b></font>';
        $html .= '<table class="sortable">';
        $html .= '<tr>
            <td><font color=#ffffff><b>Team</b></font></td>
            <td><font color=#ffffff><b>W-L</b></font></td>
            <td><font color=#ffffff><b>Pct</b></font></td>
            <td><center><font color=#ffffff><b>GB</b></font></center></td>
            <td><center><font color=#ffffff><b>Magic#</b></font></center></td>
            <td><font color=#ffffff><b>Left</b></font></td>
            <td><font color=#ffffff><b>Conf.</b></font></td>
            <td><font color=#ffffff><b>Div.</b></font></td>
            <td><font color=#ffffff><b>Home</b></font></td>
            <td><font color=#ffffff><b>Away</b></font></td>
            <td><center><font color=#ffffff><b>Home<br>Played</b></font></center></td>
            <td><center><font color=#ffffff><b>Away<br>Played</b></font></center></td>
            <td><font color=#ffffff><b>Last 10</b></font></td>
            <td><font color=#ffffff><b>Streak</b></font></td>
        </tr>';
        return $html;
    }

    private function generateStandingsRows($result, $limit) {
        $html = '';
        for ($i = 0; $i < $limit; $i++) {
            $tid = $this->db->sql_result($result, $i, 'tid');
            $team_name = $this->db->sql_result($result, $i, 'team_name');
            
            if ($this->db->sql_result($result, $i, 'clinchedConference') == 1) {
                $team_name = "<b>Z</b>-" . $team_name;
            } elseif ($this->db->sql_result($result, $i, 'clinchedDivision') == 1) {
                $team_name = "<b>Y</b>-" . $team_name;
            } elseif ($this->db->sql_result($result, $i, 'clinchedPlayoffs') == 1) {
                $team_name = "<b>X</b>-" . $team_name;
            }

            $queryLast10Games = "SELECT last_win, last_loss, streak_type, streak FROM ibl_power WHERE TeamID = $tid";
            $resultLast10Games = $this->db->sql_query($queryLast10Games);
            
            $html .= $this->generateTeamRow($result, $i, $tid, $team_name, $resultLast10Games);
        }
        return $html;
    }

    private function generateTeamRow($result, $i, $tid, $team_name, $resultLast10Games) {
        return '<tr><td><a href="modules.php?name=Team&op=team&teamID=' . $tid . '">' . $team_name . '</td>
            <td>' . $this->db->sql_result($result, $i, 'leagueRecord') . '</td>
            <td>' . $this->db->sql_result($result, $i, 'pct') . '</td>
            <td><center>' . $this->db->sql_result($result, $i, 'confGB') . '</center></td>
            <td><center>' . $this->db->sql_result($result, $i, 'confMagicNumber') . '</center></td>
            <td>' . $this->db->sql_result($result, $i, 'gamesUnplayed') . '</td>
            <td>' . $this->db->sql_result($result, $i, 'confRecord') . '</td>
            <td>' . $this->db->sql_result($result, $i, 'divRecord') . '</td>
            <td>' . $this->db->sql_result($result, $i, 'homeRecord') . '</td>
            <td>' . $this->db->sql_result($result, $i, 'awayRecord') . '</td>
            <td><center>' . $this->db->sql_result($result, $i, 'homeGames') . '</center></td>
            <td><center>' . $this->db->sql_result($result, $i, 'awayGames') . '</center></td>
            <td>' . $this->db->sql_result($resultLast10Games, 0, 'last_win') . '-' . $this->db->sql_result($resultLast10Games, 0, 'last_loss') . '</td>
            <td>' . $this->db->sql_result($resultLast10Games, 0, 'streak_type') . ' ' . $this->db->sql_result($resultLast10Games, 0, 'streak') . '</td></tr>';
    }
}
