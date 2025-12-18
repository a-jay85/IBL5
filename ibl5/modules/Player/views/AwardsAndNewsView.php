<?php

use Services\DatabaseService;
use Utilities\HtmlSanitizer;

require_once __DIR__ . '/BaseView.php';

class AwardsAndNewsView extends BaseView {
    public function render() {
        global $mysqli_db;
        
        $stmt = $mysqli_db->prepare("SELECT * FROM ibl_awards WHERE name=? ORDER BY year ASC");
        $stmt->bind_param("s", $this->player->name);
        $stmt->execute();
        $awardsquery = $stmt->get_result();

        echo "<table border=1 cellspacing=0 cellpadding=0 valign=top style='margin: 0 auto;'>
            <tr>
                <td bgcolor=#0000cc align=center><b><font color=#ffffff>AWARDS</font></b></td>
            </tr>";

        while ($awardsrow = $awardsquery->fetch_assoc()) {
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

        $searchPattern = '%' . $mysqli_db->real_escape_string($this->player->name) . '%';
        $searchPatternII = '%' . $mysqli_db->real_escape_string($this->player->name) . ' II%';
        
        $stmt = $mysqli_db->prepare("SELECT sid, title, time FROM nuke_stories WHERE (hometext LIKE ? OR bodytext LIKE ?) AND (hometext NOT LIKE ? OR bodytext NOT LIKE ?) ORDER BY time DESC");
        $stmt->bind_param("ssss", $searchPattern, $searchPattern, $searchPatternII, $searchPatternII);
        $stmt->execute();
        $result = $stmt->get_result();
        $num = $result->num_rows;

        echo "<small>";        
        while ($row = $result->fetch_assoc()) {
            $sid = $row['sid'];
            $title = HtmlSanitizer::safeHtmlOutput($row['title']);
            $time = HtmlSanitizer::safeHtmlOutput($row['time']);

            echo "* <a href=\"modules.php?name=News&file=article&sid=$sid&mode=&order=0&thold=0\">$title</a> ($time)<br>";
        }
        echo "</small>
            </td></tr></table>";
    }
}