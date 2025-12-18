<?php

require_once __DIR__ . '/BaseView.php';

class RegularSeasonTotalsView extends BaseView {
    public function render() {
        global $mysqli_db;
        
        echo "<table border=1 cellspacing=0 class=\"sortable\" style='margin: 0 auto;'>
            <tr>
                <td colspan=15 style='font-weight:bold; text-align:center; background-color:#00c; color:#fff;'>Regular Season Totals</td>
            </tr>
            <tr>
                <th>year</th>
                <th>team</th>
                <th>g</th>
                <th>min</th>
                <th>FGM-FGA</th>
                <th>FTM-FTA</th>
                <th>3GM-3GA</th>
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
        
        $stmt = $mysqli_db->prepare("SELECT * FROM ibl_hist WHERE pid = ? ORDER BY year ASC");
        if (!$stmt) {
            throw new \RuntimeException("Prepare failed: " . $mysqli_db->error);
        }
        $stmt->bind_param("i", $this->player->playerID);
        $stmt->execute();
        $result44 = $stmt->get_result();

        while ($row44 = $result44->fetch_assoc()) {
            $hist_year = intval($row44['year']);
            $hist_team = $row44['team'];
            $hist_tid = intval($row44['teamid']);
            $hist_gm = intval($row44['games']);
            $hist_min = intval($row44['minutes']);
            $hist_fgm = intval($row44['fgm']);
            $hist_fga = intval($row44['fga']);
            $hist_ftm = intval($row44['ftm']);
            $hist_fta = intval($row44['fta']);
            $hist_tgm = intval($row44['tgm']);
            $hist_tga = intval($row44['tga']);
            $hist_orb = intval($row44['orb']);
            $hist_reb = intval($row44['reb']);
            $hist_ast = intval($row44['ast']);
            $hist_stl = intval($row44['stl']);
            $hist_tvr = intval($row44['tvr']);
            $hist_blk = intval($row44['blk']);
            $hist_pf = intval($row44['pf']);
            $hist_pts = $hist_fgm + $hist_fgm + $hist_ftm + $hist_tgm;

            echo "<tr>
                <td><center>$hist_year</center></td>
                <td><center><a href=\"modules.php?name=Team&op=team&teamID=$hist_tid&yr=$hist_year\">$hist_team</a></center></td>
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
        $stmt->close();

        echo "<tr>
            <td colspan=2 style=\"font-weight:bold;\"><b>Career Totals</b></td>
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