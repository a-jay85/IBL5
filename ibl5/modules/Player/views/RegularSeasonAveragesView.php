<?php

require_once __DIR__ . '/BaseView.php';

class RegularSeasonAveragesView extends BaseView {
    public function render() {
        echo "<table border=1 cellspacing=0 class=\"sortable\">
            <tr>
                <td colspan=21><center><b><font class=\"content\">Career Averages</font></b></center></td>
            </tr>
            <tr>
                <th>year</th>
                <th>team</th>
                <th>g</th>
                <th>min</th>
                <th>fgm</th>
                <th>fga</th>
                <th>fgp</th>
                <th>ftm</th>
                <th>fta</th>
                <th>ftp</th>
                <th>3gm</th>
                <th>3ga</th>
                <th>3gp</th>
                <th>orb</th>
                <th>reb</th>
                <th>ast</th>
                <th>stl</th>
                <th>to</th>
                <th>blk</th>
                <th>pf</th>
                <th>pts</th>
            </tr>";

        $car_gm = $car_min = $car_fgm = $car_fga = $car_ftm = $car_fta = $car_3gm = $car_3ga = 0;
        $car_orb = $car_reb = $car_ast = $car_stl = $car_blk = $car_tvr = $car_pf = $car_pts = 0;

        $result44 = $this->db->sql_query("SELECT * FROM ibl_hist WHERE pid=" . $this->player->playerID . " ORDER BY year ASC");
        while ($row44 = $this->db->sql_fetchrow($result44)) {
            $hist_year = stripslashes(check_html($row44['year'], "nohtml"));
            $hist_team = stripslashes(check_html($row44['team'], "nohtml"));
            $hist_gm = stripslashes(check_html($row44['gm'], "nohtml"));
            $hist_min = stripslashes(check_html($row44['min'], "nohtml"));
            $hist_fgm = stripslashes(check_html($row44['fgm'], "nohtml"));
            $hist_fga = stripslashes(check_html($row44['fga'], "nohtml"));
            $hist_fgp = ($hist_fga) ? ($hist_fgm / $hist_fga) : "0.000";
            $hist_ftm = stripslashes(check_html($row44['ftm'], "nohtml"));
            $hist_fta = stripslashes(check_html($row44['fta'], "nohtml"));
            $hist_ftp = ($hist_fta) ? ($hist_ftm / $hist_fta) : "0.000";
            $hist_tgm = stripslashes(check_html($row44['3gm'], "nohtml"));
            $hist_tga = stripslashes(check_html($row44['3ga'], "nohtml"));
            $hist_tgp = ($hist_tga) ? ($hist_tgm / $hist_tga) : '0.000';
            $hist_orb = stripslashes(check_html($row44['orb'], "nohtml"));
            $hist_reb = stripslashes(check_html($row44['reb'], "nohtml"));
            $hist_ast = stripslashes(check_html($row44['ast'], "nohtml"));
            $hist_stl = stripslashes(check_html($row44['stl'], "nohtml"));
            $hist_tvr = stripslashes(check_html($row44['tvr'], "nohtml"));
            $hist_blk = stripslashes(check_html($row44['blk'], "nohtml"));
            $hist_pf = stripslashes(check_html($row44['pf'], "nohtml"));
            $hist_pts = $hist_fgm + $hist_fgm + $hist_ftm + $hist_tgm;

            $hist_mpg = ($hist_gm) ? ($hist_min / $hist_gm) : "0.0";
            $hist_fgmpg = ($hist_gm) ? ($hist_fgm / $hist_gm) : "0.0";
            $hist_fgapg = ($hist_gm) ? ($hist_fga / $hist_gm) : "0.0";
            $hist_ftmpg = ($hist_gm) ? ($hist_ftm / $hist_gm) : "0.0";
            $hist_ftapg = ($hist_gm) ? ($hist_fta / $hist_gm) : "0.0";
            $hist_3gmpg = ($hist_gm) ? ($hist_tgm / $hist_gm) : "0.0";
            $hist_3gapg = ($hist_gm) ? ($hist_tga / $hist_gm) : "0.0";
            $hist_opg = ($hist_gm) ? ($hist_orb / $hist_gm) : "0.0";
            $hist_rpg = ($hist_gm) ? ($hist_reb / $hist_gm) : "0.0";
            $hist_apg = ($hist_gm) ? ($hist_ast / $hist_gm) : "0.0";
            $hist_spg = ($hist_gm) ? ($hist_stl / $hist_gm) : "0.0";
            $hist_tpg = ($hist_gm) ? ($hist_tvr / $hist_gm) : "0.0";
            $hist_bpg = ($hist_gm) ? ($hist_blk / $hist_gm) : "0.0";
            $hist_fpg = ($hist_gm) ? ($hist_pf / $hist_gm) : "0.0";
            $hist_ppg = ($hist_gm) ? ($hist_pts / $hist_gm) : "0.0";

            $car_gm += $hist_gm;
            $car_min += $hist_min;
            $car_fgm += $hist_fgm;
            $car_fga += $hist_fga;
            $car_ftm += $hist_ftm;
            $car_fta += $hist_fta;
            $car_3gm += $hist_tgm;
            $car_3ga += $hist_tga;
            $car_orb += $hist_orb;
            $car_reb += $hist_reb;
            $car_ast += $hist_ast;
            $car_stl += $hist_stl;
            $car_blk += $hist_blk;
            $car_tvr += $hist_tvr;
            $car_pf += $hist_pf;
            $car_pts += $hist_pts;

            echo "<td><center>$hist_year</center></td>
                <td><center>$hist_team</center></td>
                <td><center>$hist_gm</center></td>
                <td><center>";
            printf('%01.1f', $hist_mpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_fgmpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_fgapg);
            echo "</center></td><td><center>";
            printf('%01.3f', $hist_fgp);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_ftmpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_ftapg);
            echo "</center></td><td><center>";
            printf('%01.3f', $hist_ftp);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_3gmpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_3gapg);
            echo "</center></td><td><center>";
            printf('%01.3f', $hist_tgp);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_opg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_rpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_apg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_spg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_tpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_bpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_fpg);
            echo "</center></td><td><center>";
            printf('%01.1f', $hist_ppg);
            echo "</center></td></tr>";
        }

        // CURRENT YEAR AVERAGES

        if (!$this->player->isRetired) {
            echo "<tr align=center>
                <td><center>$this->currentYear</center></td>
                <td><center>" . $this->player->teamName . "</center></td>
                <td><center>" . $this->playerStats->seasonGamesPlayed . "</center></td>
                <td><center>";
            printf('%01.1f', $this->playerStats->seasonMinutesPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonFieldGoalsMadePerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonFieldGoalsAttemptedPerGame);
            echo "</center></td><td><center>";
            printf('%01.3f', $this->playerStats->seasonFieldGoalPercentage);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonFreeThrowsMadePerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonFreeThrowsAttemptedPerGame);
            echo "</center></td><td><center>";
            printf('%01.3f', $this->playerStats->seasonFreeThrowPercentage);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonThreePointersMadePerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonThreePointersAttemptedPerGame);
            echo "</center></td><td><center>";
            printf('%01.3f', $this->playerStats->seasonThreePointPercentage);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonOffensiveReboundsPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonTotalReboundsPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonAssistsPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonStealsPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonTurnoversPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonBlocksPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonPersonalFoulsPerGame);
            echo "</center></td><td><center>";
            printf('%01.1f', $this->playerStats->seasonPointsPerGame);
            echo "</center></td></tr>";

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

        $car_avgm = ($car_gm) ? $car_min / $car_gm : "0.0";
        $car_fgmpg = ($car_gm) ? $car_fgm / $car_gm : "0.0";
        $car_fgapg = ($car_gm) ? $car_fga / $car_gm : "0.0";
        $car_fgp = ($car_fga) ?$car_fgm / $car_fga : "0.000";
        $car_ftmpg = ($car_gm) ? $car_ftm / $car_gm : "0.0";
        $car_ftapg = ($car_gm) ? $car_fta / $car_gm : "0.0";
        $car_ftp = ($car_fta) ? $car_ftm / $car_fta : "0.000";
        $car_3gmpg = ($car_gm) ? $car_3gm / $car_gm : "0.0";
        $car_3gapg = ($car_gm) ? $car_3ga / $car_gm : "0.0";
        $car_tgp = ($car_3ga) ? $car_3gm / $car_3ga : "0.000";
        $car_avgo = ($car_gm) ? $car_orb / $car_gm : "0.0";
        $car_avgr = ($car_gm) ? $car_reb / $car_gm : "0.0";
        $car_avga = ($car_gm) ? $car_ast / $car_gm : "0.0";
        $car_avgs = ($car_gm) ? $car_stl / $car_gm : "0.0";
        $car_avgb = ($car_gm) ? $car_blk / $car_gm : "0.0";
        $car_avgt = ($car_gm) ? $car_tvr / $car_gm : "0.0";
        $car_avgf = ($car_gm) ? $car_pf / $car_gm : "0.0";
        $car_avgp = ($car_gm) ? $car_pts / $car_gm : "0.0";

        echo "<tr>
            <td colspan=2>Career Averages</td>
            <td><center>$car_gm</center></td>
            <td><center>";
        printf('%01.1f', $car_avgm);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_fgmpg);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_fgapg);
        echo "</center></td><td><center>";
        printf('%01.3f', $car_fgp);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_ftmpg);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_ftapg);
        echo "</center></td><td><center>";
        printf('%01.3f', $car_ftp);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_3gmpg);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_3gapg);
        echo "</center></td><td><center>";
        printf('%01.3f', $car_tgp);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgo);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgr);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avga);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgs);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgt);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgb);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgf);
        echo "</center></td><td><center>";
        printf('%01.1f', $car_avgp);
        echo "</center></td></tr></table>";
    }
}