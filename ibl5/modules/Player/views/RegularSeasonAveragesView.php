<?php

require_once __DIR__ . '/BaseView.php';

class RegularSeasonAveragesView extends BaseView {
    public function render() {
        echo "<table border=1 cellspacing=0 class=\"sortable\" style='margin: 0 auto;'>
            <tr>
                <td colspan=21 style='font-weight:bold; text-align:center;background-color:#00c;color:#fff;'>Regular Season Averages</td>
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
            $hist_year = intval($row44['year']);
            $hist_team = $row44['team'];
            $hist_gm = intval($row44['games']);
            $hist_min = intval($row44['minutes']);
            $hist_fgm = intval($row44['fgm']);
            $hist_fga = intval($row44['fga']);
            $hist_fgp = ($hist_fga) ? ($hist_fgm / $hist_fga) : "0.000";
            $hist_ftm = intval($row44['ftm']);
            $hist_fta = intval($row44['fta']);
            $hist_ftp = ($hist_fta) ? ($hist_ftm / $hist_fta) : "0.000";
            $hist_tgm = intval($row44['tgm']);
            $hist_tga = intval($row44['tga']);
            $hist_tgp = ($hist_tga) ? ($hist_tgm / $hist_tga) : '0.000';
            $hist_orb = intval($row44['orb']);
            $hist_reb = intval($row44['reb']);
            $hist_ast = intval($row44['ast']);
            $hist_stl = intval($row44['stl']);
            $hist_tvr = intval($row44['tvr']);
            $hist_blk = intval($row44['blk']);
            $hist_pf = intval($row44['pf']);
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
            <td colspan=2 style='font-weight:bold;'>Career Averages</td>
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