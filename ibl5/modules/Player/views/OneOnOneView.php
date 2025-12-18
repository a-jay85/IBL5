<?php

use Services\DatabaseService;
use Utilities\HtmlSanitizer;

require_once __DIR__ . '/BaseView.php';

class OneOnOneView extends BaseView {
    public function render() {
        global $mysqli_db;
        
        echo "<table style='margin: 0 auto;'>
            <tr>
                <td bgcolor=#0000cc align=center><b><font color=#ffffff>ONE-ON-ONE RESULTS</font></b></td>
            </tr>
            <tr>
                <td>";

        $playerName = str_replace("%20", " ", $this->player->name);

        $wins = $losses = 0;

        // Query for wins (where player is the winner)
        $stmt = $mysqli_db->prepare("SELECT gameid, winner, loser, winscore, lossscore FROM ibl_one_on_one WHERE winner = ? ORDER BY gameid ASC");
        $stmt->bind_param("s", $playerName);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $gameid = $row['gameid'];
            $winner = HtmlSanitizer::safeHtmlOutput($row['winner']);
            $loser = HtmlSanitizer::safeHtmlOutput($row['loser']);
            $winscore = $row['winscore'];
            $lossscore = $row['lossscore'];

            echo "* def. $loser, $winscore-$lossscore (# $gameid)<br>";
            $wins++;
        }
        $stmt->close();

        // Query for losses (where player is the loser)
        $stmt = $mysqli_db->prepare("SELECT gameid, winner, loser, winscore, lossscore FROM ibl_one_on_one WHERE loser = ? ORDER BY gameid ASC");
        $stmt->bind_param("s", $playerName);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $gameid = $row['gameid'];
            $winner = HtmlSanitizer::safeHtmlOutput($row['winner']);
            $loser = HtmlSanitizer::safeHtmlOutput($row['loser']);
            $winscore = $row['winscore'];
            $lossscore = $row['lossscore'];

            echo "* lost to $winner, $lossscore-$winscore (# $gameid)<br>";
            $losses++;
        }
        $stmt->close();

        echo "<b><center>Record: $wins - $losses</center></b><br>
            </table>";
    }
}