<?php

require_once __DIR__ . '/BaseView.php';

class OlympicTotalsView extends BaseView {
    public function render() {
        $car_gm = $car_min = $car_fgm = $car_fga = $car_ftm = $car_fta = $car_3gm = $car_3ga = 0;
        $car_orb = $car_reb = $car_ast = $car_stl = $car_blk = $car_tvr = $car_pf = $car_pts = 0;

        echo "<table border=1 cellspacing=0 class=\"sortable\>
            <tr>
                <td colspan=15><center><font class=\"content\" color=\"#000000\"><b>Olympics Career Totals</b></font></center></td>
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

        $resultplayoff4 = $this->db->sql_query("SELECT * FROM ibl_olympics_stats WHERE name='" . $this->player->name . "' ORDER BY year ASC");
        while ($rowplayoff4 = $this->db->sql_fetchrow($resultplayoff4)) {
            $hist_year = stripslashes(check_html($rowplayoff4['year'], "nohtml"));
            $hist_team = stripslashes(check_html($rowplayoff4['team'], "nohtml"));
            $hist_gm = stripslashes(check_html($rowplayoff4['games'], "nohtml"));
            $hist_min = stripslashes(check_html($rowplayoff4['minutes'], "nohtml"));
            $hist_fgm = stripslashes(check_html($rowplayoff4['fgm'], "nohtml"));
            $hist_fga = stripslashes(check_html($rowplayoff4['fga'], "nohtml"));
            $hist_fgp = ($hist_fga) ? ($hist_fgm / $hist_fga) : "0.000";
            $hist_ftm = stripslashes(check_html($rowplayoff4['ftm'], "nohtml"));
            $hist_fta = stripslashes(check_html($rowplayoff4['fta'], "nohtml"));
            $hist_ftp = ($hist_fta) ? ($hist_ftm / $hist_fta) : "0.000";
            $hist_tgm = stripslashes(check_html($rowplayoff4['tgm'], "nohtml"));
            $hist_tga = stripslashes(check_html($rowplayoff4['tga'], "nohtml"));
            $hist_tgp = ($hist_tga) ? ($hist_tgm / $hist_tga) : "0.000";
            $hist_orb = stripslashes(check_html($rowplayoff4['orb'], "nohtml"));
            $hist_reb = stripslashes(check_html($rowplayoff4['reb'], "nohtml"));
            $hist_ast = stripslashes(check_html($rowplayoff4['ast'], "nohtml"));
            $hist_stl = stripslashes(check_html($rowplayoff4['stl'], "nohtml"));
            $hist_tvr = stripslashes(check_html($rowplayoff4['tvr'], "nohtml"));
            $hist_blk = stripslashes(check_html($rowplayoff4['blk'], "nohtml"));
            $hist_pf = stripslashes(check_html($rowplayoff4['pf'], "nohtml"));
            $hist_pts = $hist_fgm + $hist_fgm + $hist_ftm + $hist_tgm;

            echo "<td><center>$hist_year</center></td>
                <td><center>$hist_team</center></td>
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

        $car_fgp = ($car_fga) ? $car_fgm / $car_fga : "0.000";
        $car_ftp = ($car_fta) ? $car_ftm / $car_fta : "0.000";
        $car_tgp = ($car_3ga) ? $car_3gm / $car_3ga : "0.000";
        $car_avgm = ($car_gm) ? $car_min / $car_gm : "0.0";
        $car_avgo = ($car_gm) ? $car_orb / $car_gm : "0.0";
        $car_avgr = ($car_gm) ? $car_reb / $car_gm : "0.0";
        $car_avga = ($car_gm) ? $car_ast / $car_gm : "0.0";
        $car_avgs = ($car_gm) ? $car_stl / $car_gm : "0.0";
        $car_avgb = ($car_gm) ? $car_blk / $car_gm : "0.0";
        $car_avgt = ($car_gm) ? $car_tvr / $car_gm : "0.0";
        $car_avgf = ($car_gm) ? $car_pf / $car_gm : "0.0";
        $car_avgp = ($car_gm) ? $car_pts / $car_gm : "0.0";

        echo "<tr>
            <td colspan=2>Olympics Totals</td>
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