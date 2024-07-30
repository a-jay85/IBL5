<?php

global $db;

$queryplayoffs = "SELECT * FROM ibl_playoff_results ORDER BY year DESC";
$resultplayoffs = $db->sql_query($queryplayoffs);
$numplayoffs = $db->sql_numrows($resultplayoffs);

$pp = 0;
$totalplayoffwins = 0;
$totalplayofflosses = 0;
$first_round_victories = 0;
$second_round_victories = 0;
$third_round_victories = 0;
$fourth_round_victories = 0;
$first_round_losses = 0;
$second_round_losses = 0;
$third_round_losses = 0;
$fourth_round_losses = 0;

$round_one_output = "";
$round_two_output = "";
$round_three_output = "";
$round_four_output = "";

$first_wins = 0;
$second_wins = 0;
$third_wins = 0;
$fourth_wins = 0;
$first_losses = 0;
$second_losses = 0;
$third_losses = 0;
$fourth_losses = 0;

while ($pp < $numplayoffs) {
    $playoffround = $db->sql_result($resultplayoffs, $pp, "round");
    $playoffyear = $db->sql_result($resultplayoffs, $pp, "year");
    $playoffwinner = $db->sql_result($resultplayoffs, $pp, "winner");
    $playoffloser = $db->sql_result($resultplayoffs, $pp, "loser");
    $playoffloser_games = $db->sql_result($resultplayoffs, $pp, "loser_games");

    if ($playoffround == 1) {
        if ($playoffwinner == $team->name) {
            $totalplayoffwins += 4;
            $totalplayofflosses += $playoffloser_games;
            $first_wins += 4;
            $first_losses += $playoffloser_games;
            $first_round_victories++;
            $round_one_output .= "$playoffyear - $team->name 4, $playoffloser $playoffloser_games<br>";
        } else if ($playoffloser == $team->name) {
            $totalplayofflosses += 4;
            $totalplayoffwins += $playoffloser_games;
            $first_losses += 4;
            $first_wins += $playoffloser_games;
            $first_round_losses++;
            $round_one_output .= "$playoffyear - $playoffwinner 4, $team->name $playoffloser_games<br>";
        }
    } else if ($playoffround == 2) {
        if ($playoffwinner == $team->name) {
            $totalplayoffwins += 4;
            $totalplayofflosses += $playoffloser_games;
            $second_wins += 4;
            $second_losses += $playoffloser_games;
            $second_round_victories++;
            $round_two_output .= "$playoffyear - $team->name 4, $playoffloser $playoffloser_games<br>";
        } else if ($playoffloser == $team->name) {
            $totalplayofflosses += 4;
            $totalplayoffwins += $playoffloser_games;
            $second_losses += 4;
            $second_wins += $playoffloser_games;
            $second_round_losses++;
            $round_two_output .= "$playoffyear - $playoffwinner 4, $team->name $playoffloser_games<br>";
        }
    } else if ($playoffround == 3) {
        if ($playoffwinner == $team->name) {
            $totalplayoffwins += 4;
            $totalplayofflosses += $playoffloser_games;
            $third_wins += 4;
            $third_losses += $playoffloser_games;
            $third_round_victories++;
            $round_three_output .= "$playoffyear - $team->name 4, $playoffloser $playoffloser_games<br>";
        } else if ($playoffloser == $team->name) {
            $totalplayofflosses += 4;
            $totalplayoffwins += $playoffloser_games;
            $third_losses += 4;
            $third_wins += $playoffloser_games;
            $third_round_losses++;
            $round_three_output .= "$playoffyear - $playoffwinner 4, $team->name $playoffloser_games<br>";
        }
    } else if ($playoffround == 4) {
        if ($playoffwinner == $team->name) {
            $totalplayoffwins += 4;
            $totalplayofflosses += $playoffloser_games;
            $fourth_wins += 4;
            $fourth_losses += $playoffloser_games;
            $fourth_round_victories++;
            $round_four_output .= "$playoffyear - $team->name 4, $playoffloser $playoffloser_games<br>";
        } else if ($playoffloser == $team->name) {
            $totalplayofflosses += 4;
            $totalplayoffwins += $playoffloser_games;
            $fourth_losses += 4;
            $fourth_wins += $playoffloser_games;
            $fourth_round_losses++;
            $round_four_output .= "$playoffyear - $playoffwinner 4, $team->name $playoffloser_games<br>";
        }
    }
    $pp++;
}

$pwlpct = ($totalplayoffwins + $totalplayofflosses != 0) ? number_format($totalplayoffwins / ($totalplayoffwins + $totalplayofflosses), 3) : "0.000";
$r1wlpct = ($first_round_victories + $first_round_losses != 0) ? number_format($first_round_victories / ($first_round_victories + $first_round_losses), 3) : "0.000";
$r2wlpct = ($second_round_victories + $second_round_losses != 0) ? number_format($second_round_victories / ($second_round_victories + $second_round_losses), 3) : "0.000";
$r3wlpct = ($third_round_victories + $third_round_losses) ? number_format($third_round_victories / ($third_round_victories + $third_round_losses), 3) : "0.000";
$r4wlpct = ($fourth_round_victories + $fourth_round_losses) ? number_format($fourth_round_victories / ($fourth_round_victories + $fourth_round_losses), 3) : "0.000";
$round_victories = $first_round_victories + $second_round_victories + $third_round_victories + $fourth_round_victories;
$round_losses = $first_round_losses + $second_round_losses + $third_round_losses + $fourth_round_losses;
$swlpct = ($round_victories + $round_losses) ? number_format($round_victories / ($round_victories + $round_losses), 3) : "0.000";
$firstpct = ($first_wins + $first_losses) ? number_format($first_wins / ($first_wins + $first_losses), 3) : "0.000";
$secondpct = ($second_wins + $second_losses) ? number_format($second_wins / ($second_wins + $second_losses), 3) : "0.000";
$thirdpct = ($third_wins + $third_losses) ? number_format($third_wins / ($third_wins + $third_losses), 3) : "0.000";
$fourthpct = ($fourth_wins + $fourth_losses) ? number_format($fourth_wins / ($fourth_wins + $fourth_losses), 3) : "0.000";

if ($round_one_output != "") {
    $output .= "<tr bgcolor=\"#$team->color1\"><td align=center><font color=\"#$team->color2\"><b>First-Round Playoff Results</b></font></td></tr>
        <tr><td>
        <div id=\"History-P1\" style=\"overflow:auto\">" . $round_one_output . "</div></td></tr>
        <tr><td><b>Totals:</b> $first_wins - $first_losses ($firstpct)<br>
        <b>Series:</b> $first_round_victories - $first_round_losses ($r1wlpct)</td></tr>";
}
if ($round_two_output != "") {
    $output .= "<tr bgcolor=\"#$team->color1\"><td align=center><font color=\"#$team->color2\"><b>Conference Semis Playoff Results</b></font></td></tr>
        <tr><td>
        <div id=\"History-P2\" style=\"overflow:auto\">" . $round_two_output . "</div></td></tr>
        <tr><td><b>Totals:</b> $second_wins - $second_losses ($secondpct)<br>
        <b>Series:</b> $second_round_victories - $second_round_losses ($r2wlpct)</td></tr>";
}
if ($round_three_output != "") {
    $output .= "<tr bgcolor=\"#$team->color1\"><td align=center><font color=\"#$team->color2\"><b>Conference Finals Playoff Results</b></font></td></tr>
        <tr><td>
        <div id=\"History-P3\" style=\"overflow:auto\">" . $round_three_output . "</div></td></tr>
        <tr><td><b>Totals:</b> $third_wins - $third_losses ($thirdpct)<br>
        <b>Series:</b> $third_round_victories - $third_round_losses ($r3wlpct)</td></tr>";
}
if ($round_four_output != "") {
    $output .= "<tr bgcolor=\"#$team->color1\"><td align=center><font color=\"#$team->color2\"><b>IBL Finals Playoff Results</b></font></td></tr>
        <tr><td>
        <div id=\"History-P4\" style=\"overflow:auto\">" . $round_four_output . "</div></td></tr>
        <tr><td><b>Totals:</b> $fourth_wins - $fourth_losses ($fourthpct)<br>
        <b>Series:</b> $fourth_round_victories - $fourth_round_losses ($r4wlpct)</td></tr>";
}

$output .= "<tr bgcolor=\"#$team->color1\"><td align=center><font color=\"#$team->color2\"><b>Post-Season Totals</b></font></td></tr>
    <tr><td><b>Games:</b> $totalplayoffwins - $totalplayofflosses ($pwlpct)</td></tr>
    <tr><td><b>Series:</b> $round_victories - $round_losses ($swlpct)</td></tr>
    </table>";