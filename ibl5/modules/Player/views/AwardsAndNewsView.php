<?php

require_once __DIR__ . '/BaseView.php';

class AwardsAndNewsView extends BaseView {
    public function render() {
        $awardsquery = $this->db->sql_query("SELECT * FROM ibl_awards WHERE name='" . $this->player->name . "' ORDER BY year ASC");

        echo "<table border=1 cellspacing=0 cellpadding=0 valign=top>
            <tr>
                <td bgcolor=#0000cc align=center><b><font color=#ffffff>AWARDS</font></b></td>
            </tr>";

        while ($awardsrow = $this->db->sql_fetchrow($awardsquery)) {
            $award_year = stripslashes(check_html($awardsrow['year'], "nohtml"));
            $award_type = stripslashes(check_html($awardsrow['Award'], "nohtml"));

            echo "<tr>
                <td>$award_year $award_type</td>
            </tr>";
        }

        echo "<tr>
            <td bgcolor=#0000cc align=center><b><font color=#ffffff>ARTICLES MENTIONING THIS PLAYER</font></b></td>
        </tr>
        <tr>
            <td>";

        $player = $this->player->name;
        $query = "SELECT *
            FROM nuke_stories
            WHERE (hometext LIKE '%$player%' OR bodytext LIKE '%$player%')
                AND (hometext NOT LIKE '%$player II%' OR bodytext NOT LIKE '%$player II%')
                ORDER BY time DESC;";
        $result = $this->db->sql_query($query);
        $num = $this->db->sql_numrows($result);

        echo "<small>";        
        $i = 0;
        while ($i < $num) {
            $sid = $this->db->sql_result($result, $i, "sid");
            $title = $this->db->sql_result($result, $i, "title");
            $time = $this->db->sql_result($result, $i, "time");

            echo "* <a href=\"modules.php?name=News&file=article&sid=$sid&mode=&order=0&thold=0\">$title</a> ($time)<br>";

            $i++;
        }
        echo "</small>
            </td></tr></table>";
    }
}