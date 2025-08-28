<?php

require_once __DIR__ . '/BaseView.php';

class RatingsAndSalaryView extends BaseView {
    public function render() {
        echo "<table border=1 cellspacing=0 class=\"sortable\>
            <tr>
                <td colspan=24><center><b><font class=\"content\">(Past) Career Ratings by Year</font></b></center></td>
            </tr>
            <tr>
                <td>year</td>
                <td>2ga</td>
                <td>2gp</td>
                <td>fta</td>
                <td>ftp</td>
                <td>3ga</td>
                <td>3gp</td>
                <td>orb</td>
                <td>drb</td>
                <td>ast</td>
                <td>stl</td>
                <td>blk</td>
                <td>tvr</td>
                <td>oo</td>
                <td>do</td>
                <td>po</td>
                <td>to</td>
                <td>od</td>
                <td>dd</td>
                <td>pd</td>
                <td>td</td>
                <td>Off</td>
                <td>Def</td>
                <td>Salary</td>
            </tr>";

        $totalsalary = 0;

        $result44 = $this->db->sql_query("SELECT * FROM ibl_hist WHERE pid=" . $this->player->playerID . " ORDER BY year ASC");
        while ($row44 = $this->db->sql_fetchrow($result44)) {
            $r_year = stripslashes(check_html($row44['year'], "nohtml"));
            $r_2ga = stripslashes(check_html($row44['r_2ga'], "nohtml"));
            $r_2gp = stripslashes(check_html($row44['r_2gp'], "nohtml"));
            $r_fta = stripslashes(check_html($row44['r_fta'], "nohtml"));
            $r_ftp = stripslashes(check_html($row44['r_ftp'], "nohtml"));
            $r_3ga = stripslashes(check_html($row44['r_3ga'], "nohtml"));
            $r_3gp = stripslashes(check_html($row44['r_3gp'], "nohtml"));
            $r_orb = stripslashes(check_html($row44['r_orb'], "nohtml"));
            $r_drb = stripslashes(check_html($row44['r_drb'], "nohtml"));
            $r_ast = stripslashes(check_html($row44['r_ast'], "nohtml"));
            $r_stl = stripslashes(check_html($row44['r_stl'], "nohtml"));
            $r_blk = stripslashes(check_html($row44['r_blk'], "nohtml"));
            $r_tvr = stripslashes(check_html($row44['r_tvr'], "nohtml"));
            $r_oo = stripslashes(check_html($row44['r_oo'], "nohtml"));
            $r_do = stripslashes(check_html($row44['r_do'], "nohtml"));
            $r_po = stripslashes(check_html($row44['r_po'], "nohtml"));
            $r_to = stripslashes(check_html($row44['r_to'], "nohtml"));
            $r_od = stripslashes(check_html($row44['r_od'], "nohtml"));
            $r_dd = stripslashes(check_html($row44['r_dd'], "nohtml"));
            $r_pd = stripslashes(check_html($row44['r_pd'], "nohtml"));
            $r_td = stripslashes(check_html($row44['r_td'], "nohtml"));
            $salary = stripslashes(check_html($row44['salary'], "nohtml"));
            $r_Off = $r_oo + $r_do + $r_po + $r_to;
            $r_Def = $r_od + $r_dd + $r_pd + $r_td;

            $totalsalary += $salary;

            echo "<td><center>$r_year</center></td>
                <td><center>$r_2ga</center></td>
                <td><center>$r_2gp</center></td>
                <td><center>$r_fta</center></td>
                <td><center>$r_ftp</center></td>
                <td><center>$r_3ga</center></td>
                <td><center>$r_3gp</center></td>
                <td><center>$r_orb</center></td>
                <td><center>$r_drb</center></td>
                <td><center>$r_ast</center></td>
                <td><center>$r_stl</center></td>
                <td><center>$r_blk</center></td>
                <td><center>$r_tvr</center></td>
                <td><center>$r_oo</center></td>
                <td><center>$r_do</center></td>
                <td><center>$r_po</center></td>
                <td><center>$r_to</center></td>
                <td><center>$r_od</center></td>
                <td><center>$r_dd</center></td>
                <td><center>$r_pd</center></td>
                <td><center>$r_td</center></td>
                <td><center>$r_Off</center></td>
                <td><center>$r_Def</center></td>
                <td><center>$salary</center></td>
            </tr>";
        }

        $totalsalary /= 100;

        echo "<tr>
            <td colspan=24><center><b>Total Career Salary Earned:</b> $totalsalary million dollars</td>
        </tr>
        </table>";
    }
}