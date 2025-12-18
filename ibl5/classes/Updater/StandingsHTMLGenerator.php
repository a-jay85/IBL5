<?php
namespace Updater;

class StandingsHTMLGenerator extends \BaseMysqliRepository {
    private $standingsHTML;

    public function __construct(object $db) {
        parent::__construct($db);
        $this->standingsHTML = "<script src=\"sorttable.js\"></script>";
    }

    private function assignGroupingsFor($region) {
        if (in_array($region, \League::CONFERENCE_NAMES)) {
            $grouping = 'conference';
            $groupingGB = 'confGB';
            $groupingMagicNumber = 'confMagicNumber';
        }
        if (in_array($region, \League::DIVISION_NAMES)) {
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

        try {
            $this->execute(
                "UPDATE nuke_pages SET text = ? WHERE pid = 4",
                "s",
                $this->standingsHTML
            );
            \UI::displayDebugOutput("Updated nuke_pages with standings HTML", 'Standings HTML SQL Query');
            echo '<p>Full standings page has been updated.<p>';
        } catch (\Exception $e) {
            $errorMessage = 'Failed to update standings page: ' . $e->getMessage();
            error_log("[StandingsHTMLGenerator] Database error: {$errorMessage}");
            throw new \RuntimeException($errorMessage, 1002);
        }
    }

    private function displayStandings($region) {
        list($grouping, $groupingGB, $groupingMagicNumber) = $this->assignGroupingsFor($region);

        $standings = $this->fetchAll(
            "SELECT
            tid,
            team_name,
            leagueRecord,
            pct,
            $groupingGB,
            confRecord,
            divRecord,
            homeRecord,
            awayRecord,
            gamesUnplayed,
            $groupingMagicNumber,
            clinchedConference,
            clinchedDivision,
            clinchedPlayoffs,
            (homeWins + homeLosses) AS homeGames,
            (awayWins + awayLosses) AS awayGames
            FROM ibl_standings
            WHERE $grouping = ? ORDER BY $groupingGB ASC",
            "s",
            $region
        );

        $this->standingsHTML .= $this->generateStandingsHeader($region, $grouping);
        $this->standingsHTML .= $this->generateStandingsRows($standings, $region);
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

    private function generateStandingsRows($standings, $region) {
        $html = '';
        foreach ($standings as $row) {
            $tid = $row['tid'];
            $team_name = $row['team_name'];

            if ($row['clinchedConference'] == 1) {
                $team_name = "<b>Z</b>-" . $team_name;
            } elseif ($row['clinchedDivision'] == 1) {
                $team_name = "<b>Y</b>-" . $team_name;
            } elseif ($row['clinchedPlayoffs'] == 1) {
                $team_name = "<b>X</b>-" . $team_name;
            }

            $last10Games = $this->fetchOne(
                "SELECT last_win, last_loss, streak_type, streak FROM ibl_power WHERE TeamID = ?",
                "i",
                $tid
            );
            
            $html .= $this->generateTeamRow($row, $tid, $team_name, $last10Games, $region);
        }
        return $html;
    }

    private function generateTeamRow($row, $tid, $team_name, $last10Games, $region) {
        list($grouping, $groupingGB, $groupingMagicNumber) = $this->assignGroupingsFor($region);

        $lastWin = $last10Games['last_win'] ?? 0;
        $lastLoss = $last10Games['last_loss'] ?? 0;
        $streakType = $last10Games['streak_type'] ?? '';
        $streak = $last10Games['streak'] ?? 0;

        return '<tr><td><a href="modules.php?name=Team&op=team&teamID=' . $tid . '">' . $team_name . '</td>
            <td>' . $row['leagueRecord'] . '</td>
            <td>' . $row['pct'] . '</td>
            <td><center>' . $row[$groupingGB] . '</center></td>
            <td><center>' . $row[$groupingMagicNumber] . '</center></td>
            <td>' . $row['gamesUnplayed'] . '</td>
            <td>' . $row['confRecord'] . '</td>
            <td>' . $row['divRecord'] . '</td>
            <td>' . $row['homeRecord'] . '</td>
            <td>' . $row['awayRecord'] . '</td>
            <td><center>' . $row['homeGames'] . '</center></td>
            <td><center>' . $row['awayGames'] . '</center></td>
            <td>' . $lastWin . '-' . $lastLoss . '</td>
            <td>' . $streakType . ' ' . $streak . '</td></tr>';
    }
}
