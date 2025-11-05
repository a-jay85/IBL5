<?php

use Statistics\StatsFormatter;

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Player Archives";

Nuke\Header::header();
OpenTable();
UI::playerMenu();

$boards_type = $_POST['boards_type'];
$display = $_POST['display'];
$active = $_POST['active'];
$sort_cat = $_POST['sort_cat'];
$submitted = $_POST['submitted'];

$typeArray = array(
    'ibl_hist' => 'Regular Season Totals',
    'ibl_season_career_avgs' => 'Regular Season Averages',
    'ibl_playoff_career_totals' => 'Playoff Totals',
    'ibl_playoff_career_avgs' => 'Playoff Averages',
    'ibl_heat_career_totals' => 'H.E.A.T. Totals',
    'ibl_heat_career_avgs' => 'H.E.A.T. Averages',
    'ibl_olympics_career_totals' => 'Olympic Totals',
    'ibl_olympics_career_avgs' => 'Olympic Averages',
);

$sort_cat_array = array(
    'pts' => 'Points',
    'games' => 'Games',
    'minutes' => 'Minutes',
    'fgm' => 'Field Goals Made',
    'fga' => 'Field Goals Attempted',
    'fgpct' => 'FG Percentage (avgs only)',
    'ftm' => 'Free Throws Made',
    'fta' => 'Free Throws Attempted',
    'ftpct' => 'FT Percentage (avgs only)',
    'tgm' => 'Three-Pointers Made',
    'tga' => 'Three-Pointers Attempted',
    'tpct' => '3P Percentage (avgs only)',
    'orb' => 'Offensive Rebounds',
    'reb' => 'Total Rebounds',
    'ast' => 'Assists',
    'stl' => 'Steals',
    'tvr' => 'Turnovers',
    'blk' => 'Blocked Shots',
    'pf' => 'Personal Fouls',
);

echo "<form name=\"Leaderboards\" method=\"post\" action=\"modules.php?name=Leaderboards\">
    <table style=\"margin: auto;\">
        <tr>
            <td>
                Type: <select name=\"boards_type\">\n";
            
foreach ($typeArray as $key => $value) {
    echo "<option value=\"$value\"" . ($boards_type == $value ? ' SELECTED' : '') . ">$value</option>\n";
}

echo "</select></td>
    <td>
        Category: <select name=\"sort_cat\">";

foreach ($sort_cat_array as $key => $value) {
    echo "<option value=\"$value\"" . ($sort_cat == $value ? ' SELECTED' : '') . ">$value</option>\n";
}

echo "</select></td>
    <td>
        Include Retirees: <select name=\"active\">
        <option value=\"0\"" . ($active == '0' ? ' SELECTED' : '') . ">Yes</option>
        <option value=\"1\"" . ($active == '1' ? ' SELECTED' : '') . ">No</option>
        </select>
    </td>
    <td>
        Limit: <input type=\"number\" name=\"display\" style=\"width: 4em\" value=\"$display\"> Records
    </td>
    <td>
        <input type=\"hidden\" name=\"submitted\" value=\"1\">
        <input type=\"submit\" value=\"Display Leaderboards\"></form>
    </td>
</tr></table>";

// ===== RUN QUERY IF FORM HAS BEEN SUBMITTED

if ($submitted != null) {
    foreach ($typeArray as $key => $value) {
        if ($boards_type == $value) {
            $tableforquery = $key;
            break;
        }
    }
    foreach ($sort_cat_array as $key => $value) {
        if ($sort_cat == $value) {
            $sortby = $key;
            break;
        }
    }

    if ($tableforquery == "ibl_hist") {
        $query = "SELECT
            h.pid,
            h.name,
            sum(h.games) as games,
            sum(h.minutes) as minutes,
            sum(h.fgm) as fgm,
            sum(h.fga) as fga,
            sum(h.ftm) as ftm,
            sum(h.fta) as fta,
            sum(h.tgm) as tgm,
            sum(h.tga) as tga,
            sum(h.orb) as orb,
            sum(h.reb) as reb,
            sum(h.ast) as ast,
            sum(h.stl) as stl,
            sum(h.blk) as blk,
            sum(h.tvr) as tvr,
            sum(h.pf) as pf,
            sum(h.pts) as pts,
            p.retired
            FROM ibl_hist h
            LEFT JOIN ibl_plr p ON h.pid = p.pid
            WHERE " . ($active == 1 ? "p.retired = '0' AND" : "") . " games > 0
            GROUP BY pid
            ORDER BY $sortby DESC" . (is_numeric($display) && $display > 0 ? " LIMIT $display" : "") . ";";
    } else {
        $query = "SELECT h.*, p.retired
            FROM $tableforquery h
            LEFT JOIN ibl_plr p ON h.pid = p.pid
            WHERE " . ($active == 1 ? "p.retired = '0' AND" : "") . " games > 0
            ORDER BY $sortby DESC" . (is_numeric($display) && $display > 0 ? " LIMIT $display" : "") . ";";
    }
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);

    echo "<h2 style=\"text-align: center;\">Leaderboards Display</h2><p>
        <table class=\"sortable\">
            <tr>
                <th style=\"text-align: center;\">Rank</th>
                <th style=\"text-align: center;\">Name</th>
                <th style=\"text-align: center;\">Games</th>
                <th style=\"text-align: center;\">Minutes</th>
                <th style=\"text-align: center;\">FGM</th>
                <th style=\"text-align: center;\">FGA</th>
                <th style=\"text-align: center;\">FG%</th>
                <th style=\"text-align: center;\">FTM</th>
                <th style=\"text-align: center;\">FTA</th>
                <th style=\"text-align: center;\">FT%</th>
                <th style=\"text-align: center;\">3GM</th>
                <th style=\"text-align: center;\">3GA</th>
                <th style=\"text-align: center;\">3P%</th>
                <th style=\"text-align: center;\">ORB</th>
                <th style=\"text-align: center;\">REB</th>
                <th style=\"text-align: center;\">AST</th>
                <th style=\"text-align: center;\">STL</th>
                <th style=\"text-align: center;\">TVR</th>
                <th style=\"text-align: center;\">BLK</th>
                <th style=\"text-align: center;\">FOULS</th>
                <th style=\"text-align: center;\">PTS</th>
            </tr>";

    // ========== FILL ROWS

    $numstop = (empty($display) || $display == 0) ? $num : $display;

    $rank = 1;
    while ($row = $db->sql_fetchrow($result)) {
        if (
            $tableforquery == "ibl_season_career_avgs" ||
            $tableforquery == "ibl_heat_career_avgs" ||
            $tableforquery == "ibl_olympics_career_avgs" ||
            $tableforquery == "ibl_playoff_career_avgs"
        ) {
            $name = $row["name"] . ($row["retired"] ? "*" : "");
            $pid = $row["pid"];
            $games = round($row["games"]);
            $minutes = StatsFormatter::formatAverage($row["minutes"]);
            $fgm = StatsFormatter::formatAverage($row["fgm"]);
            $fga = StatsFormatter::formatAverage($row["fga"]);
            $fgp = StatsFormatter::formatPercentageWithDecimals($row["fgpct"], 1, 3);
            $ftm = StatsFormatter::formatAverage($row["ftm"]);
            $fta = StatsFormatter::formatAverage($row["fta"]);
            $ftp = StatsFormatter::formatPercentageWithDecimals($row["ftpct"], 1, 3);
            $tgm = StatsFormatter::formatAverage($row["tgm"]);
            $tga = StatsFormatter::formatAverage($row["tga"]);
            $tgp = StatsFormatter::formatPercentageWithDecimals($row["tpct"], 1, 3);
            $orb = StatsFormatter::formatAverage($row["orb"]);
            $reb = StatsFormatter::formatAverage($row["reb"]);
            $ast = StatsFormatter::formatAverage($row["ast"]);
            $stl = StatsFormatter::formatAverage($row["stl"]);
            $to = StatsFormatter::formatAverage($row["tvr"]);
            $blk = StatsFormatter::formatAverage($row["blk"]);
            $pf = StatsFormatter::formatAverage($row["pf"]);
            $pts = StatsFormatter::formatAverage($row["pts"]);
        } elseif (
            $tableforquery == "ibl_hist" ||
            $tableforquery == "ibl_heat_career_totals" ||
            $tableforquery == "ibl_olympics_career_totals" ||
            $tableforquery == "ibl_playoff_career_totals"
        ) {
            $name = $row["name"] . ($row["retired"] ? "*" : "");
            $pid = $row["pid"];
            $games = StatsFormatter::formatTotal($row["games"]);
            $minutes = StatsFormatter::formatTotal($row["minutes"]);
            $fgm = StatsFormatter::formatTotal($row["fgm"]);
            $fga = StatsFormatter::formatTotal($row["fga"]);
            $fgp = StatsFormatter::formatPercentage($row["fgm"], $row["fga"]);
            $ftm = StatsFormatter::formatTotal($row["ftm"]);
            $fta = StatsFormatter::formatTotal($row["fta"]);
            $ftp = StatsFormatter::formatPercentage($row["ftm"], $row["fta"]);
            $tgm = StatsFormatter::formatTotal($row["tgm"]);
            $tga = StatsFormatter::formatTotal($row["tga"]);
            $tgp = StatsFormatter::formatPercentage($row["tgm"], $row["tga"]);
            $orb = StatsFormatter::formatTotal($row["orb"]);
            $reb = StatsFormatter::formatTotal($row["reb"]);
            $ast = StatsFormatter::formatTotal($row["ast"]);
            $stl = StatsFormatter::formatTotal($row["stl"]);
            $to = StatsFormatter::formatTotal($row["tvr"]);
            $blk = StatsFormatter::formatTotal($row["blk"]);
            $pf = StatsFormatter::formatTotal($row["pf"]);
            $pts = StatsFormatter::formatTotal($row["pts"]);
        }
        
        echo "<tr>
            <td style=\"text-align: center;\">" . $rank . "</td>
            <td style=\"text-align: center;\"><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>
            <td style=\"text-align: center;\">" . $games . "</td>
            <td style=\"text-align: center;\">" . $minutes . "</td>
            <td style=\"text-align: center;\">" . $fgm . "</td>
            <td style=\"text-align: center;\">" . $fga . "</td>
            <td style=\"text-align: center;\">" . $fgp . "</td>
            <td style=\"text-align: center;\">" . $ftm . "</td>
            <td style=\"text-align: center;\">" . $fta . "</td>
            <td style=\"text-align: center;\">" . $ftp . "</td>
            <td style=\"text-align: center;\">" . $tgm . "</td>
            <td style=\"text-align: center;\">" . $tga . "</td>
            <td style=\"text-align: center;\">" . $tgp . "</td>
            <td style=\"text-align: center;\">" . $orb . "</td>
            <td style=\"text-align: center;\">" . $reb . "</td>
            <td style=\"text-align: center;\">" . $ast . "</td>
            <td style=\"text-align: center;\">" . $stl . "</td>
            <td style=\"text-align: center;\">" . $to . "</td>
            <td style=\"text-align: center;\">" . $blk . "</td>
            <td style=\"text-align: center;\">" . $pf . "</td>
            <td style=\"text-align: center;\">" . $pts . "</td>
            </tr>";

        $rank++;
        if (isset($display) && is_numeric($display) && $rank > $display) {
            break;
        }
    }

    echo "</table></center></td></tr>";
}

CloseTable();
Nuke\Footer::footer();