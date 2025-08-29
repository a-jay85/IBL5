<?php

require_once __DIR__ . '/BaseView.php';

class RegularSeasonTotalsView extends BaseView {
    public function render() {
        $car_gm = $car_min = $car_fgm = $car_fga = $car_ftm = $car_fta = $car_3gm = $car_3ga = 0;
        $car_orb = $car_reb = $car_ast = $car_stl = $car_blk = $car_tvr = $car_pf = $car_pts = 0;

        echo "<table border=1 cellspacing=0 class=\"sortable\>
            <tr>
                <td colspan=15><center><font class=\"content\" color=\"#000000\"><b>Career Totals</b></font></center></td>
            </tr>
            <tr>
                <td>year</td>
                <td>team</td>
                <td>g</td>
                <td>min</td>
                <td>FGM-FGA</td>
                <td>FTM-FTA</td>
                <td>3GM-3GA</td>
                <td>orb</td>
                <td>reb</td>
                <td>ast</td>
                <td>stl</td>
                <td>to</td>
                <td>blk</td>
                <td>pf</td>
                <td>pts</td>
            </tr>";

        $result44 = $this->db->sql_query("SELECT * FROM ibl_hist WHERE pid=" . $this->player->playerID . " ORDER BY year ASC");

        while ($row44 = $this->db->sql_fetchrow($result44)) {
            $hist_year = stripslashes(check_html($row44['year'], "nohtml"));
            $hist_team = stripslashes(check_html($row44['team'], "nohtml"));
            $hist_tid = stripslashes(check_html($row44['teamid'], "nohtml"));
            $hist_gm = stripslashes(check_html($row44['gm'], "nohtml"));
            $hist_min = stripslashes(check_html($row44['min'], "nohtml"));
            $hist_fgm = stripslashes(check_html($row44['fgm'], "nohtml"));
            $hist_fga = stripslashes(check_html($row44['fga'], "nohtml"));
            $hist_ftm = stripslashes(check_html($row44['ftm'], "nohtml"));
            $hist_fta = stripslashes(check_html($row44['fta'], "nohtml"));
            $hist_tgm = stripslashes(check_html($row44['3gm'], "nohtml"));
            $hist_tga = stripslashes(check_html($row44['3ga'], "nohtml"));
            $hist_orb = stripslashes(check_html($row44['orb'], "nohtml"));
            $hist_reb = stripslashes(check_html($row44['reb'], "nohtml"));
            $hist_ast = stripslashes(check_html($row44['ast'], "nohtml"));
            $hist_stl = stripslashes(check_html($row44['stl'], "nohtml"));
            $hist_tvr = stripslashes(check_html($row44['tvr'], "nohtml"));
            $hist_blk = stripslashes(check_html($row44['blk'], "nohtml"));
            $hist_pf = stripslashes(check_html($row44['pf'], "nohtml"));
            $hist_pts = $hist_fgm + $hist_fgm + $hist_ftm + $hist_tgm;

            echo "<tr>
                <td><center>$hist_year</center></td>
                <td><center><a href=\"modules.php?name=Team&op=team&tid=$hist_tid&yr=$hist_year\">$hist_team</a></center></td>
                <td><center>$hist_gm</center></td>
                <td><center>$hist_min</center></td>
                <td><center>$hist_fgm-$hist_fga</center></td>
                <td><center>$hist_ftm-$hist_fta</center></td>
                <td><center>$hist_tgm-$hist_tga</center></td>
                <td><center>$hist_orb</center></td>
                <td><center>$hist_reb</center></td>
                <td><center>$hist_ast</center></td>
                <td><center>$hist_stl</center></td>
                <td><center>$hist_tvr</center></td>
                <td><center>$hist_blk</center></td>
                <td><center>$hist_pf</center></td>
                <td><center>$hist_pts</td>
            </tr>";

            $car_gm = $car_gm + $hist_gm;
            $car_min = $car_min + $hist_min;
            $car_fgm = $car_fgm + $hist_fgm;
            $car_fga = $car_fga + $hist_fga;
            $car_ftm = $car_ftm + $hist_ftm;
            $car_fta = $car_fta + $hist_fta;
            $car_3gm = $car_3gm + $hist_tgm;
            $car_3ga = $car_3ga + $hist_tga;
            $car_orb = $car_orb + $hist_orb;
            $car_reb = $car_reb + $hist_reb;
            $car_ast = $car_ast + $hist_ast;
            $car_stl = $car_stl + $hist_stl;
            $car_blk = $car_blk + $hist_blk;
            $car_tvr = $car_tvr + $hist_tvr;
            $car_pf = $car_pf + $hist_pf;
            $car_pts = $car_pts + $hist_pts;
        }

        // CURRENT YEAR TOTALS

        if ($this->player->isRetired == 0) {
            echo "<tr>
                <td><center>$this->currentYear</center></td>
                <td><center>" . $this->player->teamName . "</center></td>
                <td><center>" . $this->playerStats->seasonGamesPlayed . "</center></td>
                <td><center>" . $this->playerStats->seasonMinutes . "</center></td>
                <td><center>" . $this->playerStats->seasonFieldGoalsMade . "-" . $this->playerStats->seasonFieldGoalsAttempted . "</center></td>
                <td><center>" . $this->playerStats->seasonFreeThrowsMade . "-" . $this->playerStats->seasonFreeThrowsAttempted . "</center></td>
                <td><center>" . $this->playerStats->seasonThreePointersMade . "-" . $this->playerStats->seasonThreePointersAttempted . "</center></td>
                <td><center>" . $this->playerStats->seasonOffensiveRebounds . "</center></td>
                <td><center>" . $this->playerStats->seasonTotalRebounds . "</center></td>
                <td><center>" . $this->playerStats->seasonAssists . "</center></td>
                <td><center>" . $this->playerStats->seasonSteals . "</center></td>
                <td><center>" . $this->playerStats->seasonTurnovers . "</center></td>
                <td><center>" . $this->playerStats->seasonBlocks . "</center></td>
                <td><center>" . $this->playerStats->seasonPersonalFouls . "</center></td>
                <td><center>" . $this->playerStats->seasonPoints . "</td>
            </tr>";

            $car_gm += $this->playerStats->seasonGamesPlayed;
            $car_min += $this->playerStats->seasonMinutes;
            $car_fgm += $this->playerStats->seasonFieldGoalsMade;
            $car_fga += $this->playerStats->seasonFieldGoalsAttempted;
            $car_ftm += $this->playerStats->seasonFreeThrowsMade;
            $car_fta += $this->playerStats->seasonFreeThrowsAttempted;
            $car_3gm += $this->playerStats->seasonThreePointersMade;
            $car_3ga += $this->playerStats->seasonThreePointersAttempted;
            $car_orb += $this->playerStats->seasonOffensiveRebounds;
            $car_reb += $this->playerStats->seasonTotalRebounds;
            $car_ast += $this->playerStats->seasonAssists;
            $car_stl += $this->playerStats->seasonSteals;
            $car_blk += $this->playerStats->seasonBlocks;
            $car_tvr += $this->playerStats->seasonTurnovers;
            $car_pf += $this->playerStats->seasonPersonalFouls;
            $car_pts += $this->playerStats->seasonPoints;
        }

        echo "<tr>
            <td colspan=2 >Career Totals</td>
            <td><center>$car_gm</center></td>
            <td><center>$car_min</center></td>
            <td><center>$car_fgm-$car_fga</center></td>
            <td><center>$car_ftm-$car_fta</center></td>
            <td><center>$car_3gm-$car_3ga</center></td>
            <td><center>$car_orb</center></td>
            <td><center>$car_reb</center></td>
            <td><center>$car_ast</center></td>
            <td><center>$car_stl</center></td>
            <td><center>$car_tvr</center></td>
            <td><center>$car_blk</center></td>
            <td><center>$car_pf</center></td>
            <td><center>$car_pts</td>
        </tr>
        </table>";
    }
}