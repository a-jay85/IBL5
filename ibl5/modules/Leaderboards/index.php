<?php

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
    'Reg' => 'Regular Season Totals',
    'Rav' => 'Regular Season Averages',
    'Ply' => 'Playoff Totals',
    'PLA' => 'Playoff Averages',
    'HET' => 'H.E.A.T. Totals',
    'HEA' => 'H.E.A.T. Averages',
    'OLY' => 'Olympic Totals',
    'OLM' => 'Olympic Averages',
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
            <td>Type: <select name=\"boards_type\">";
            
foreach ($typeArray as $key => $value) {
    echo "<option value=\"$key\"" . ($boards_type == $key ? ' SELECTED' : '') . ">$value</option>";
}

echo "</select></td><td>
        Category: <select name=\"sort_cat\">";

foreach ($sort_cat_array as $key => $value) {
    echo "<option value=\"$value\"" . ($sort_cat == $value ? ' SELECTED' : '') . ">$value</option>";
}

echo "</select></td>
    <td>
        Search: <select name=\"active\">
        <option value=\"0\"" . ($active == '0' ? ' SELECTED' : '') . ">All Players</option>
        <option value=\"1\"" . ($active == '1' ? ' SELECTED' : '') . ">Active Players Only</option>
        </select>
    </td>
    <td>
        Limit: <input type=\"text\" name=\"display\" size=\"4\" value=\"$display\"> Records
    </td>
    <td>
        <input type=\"hidden\" name=\"submitted\" value=\"1\">
        <input type=\"submit\" value=\"Display Leaderboards\"></form>
    </td>
</tr></table>";

// ===== RUN QUERY IF FORM HAS BEEN SUBMITTED

if ($submitted != null) {
    $tableforquery = "ibl_plr";

    if ($boards_type == 'Reg') {
        $tableforquery = "ibl_plr";
        $restriction2 = "car_gm > 0 ";
    } elseif ($boards_type == 'Rav') {
        $tableforquery = "ibl_season_career_avgs";
        $restriction2 = "games > 0";
    } elseif ($boards_type == 'Ply') {
        $tableforquery = "ibl_playoff_career_totals";
        $restriction2 = "games > 0";
    } elseif ($boards_type == 'PLA') {
        $tableforquery = "ibl_playoff_career_avgs";
        $restriction2 = "games > 0";
    } elseif ($boards_type == 'HET') {
        $tableforquery = "ibl_heat_career_totals";
        $restriction2 = "games > 0";
    } elseif ($boards_type == 'HEA') {
        $tableforquery = "ibl_heat_career_avgs";
        $restriction2 = "games > 0";
    } elseif ($boards_type == 'OLY') {
        $tableforquery = "ibl_olympics_career_totals";
        $restriction2 = "games > 0";
    } elseif ($boards_type == 'OLM') {
        $tableforquery = "ibl_olympics_career_avgs";
        $restriction2 = "games > 0";
    }

    if ($active == 1) {
        $restriction1 = " retired = '0' AND ";
    }

    $sortby = "pts";
    foreach ($sort_cat_array as $key => $value) {
        if ($sort_cat == $value) {
            $sortby = $key;
        }
    }

    if ($tableforquery == "ibl_plr") {
        $sortby = "car_" . $sortby;
        if ($sort_cat == 'Games') {
            $sortby = "car_gm";
        } elseif ($sort_cat == 'Minutes') {
            $sortby = "car_min";
        } elseif ($sort_cat == 'Turnovers') {
            $sortby = "car_to";
        }
    }

    $query = "SELECT * FROM $tableforquery WHERE $restriction1 $restriction2 ORDER BY $sortby DESC" . (is_numeric($display) ? " LIMIT $display" : "");
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);

    echo "<h2 style=\"text-align: center;\">Leaderboards Display</h2><p>
        <table class=\"sortable\">
            <tr>
                <th style=\"text-align: center;\">Rank</th>
                <th style=\"text-align: center;\">Name</th>
                <th style=\"text-align: center;\">Gm</th>
                <th style=\"text-align: center;\">Min</th>
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
        if ($tableforquery == "ibl_plr") {
            $playerStats = PlayerStats::withPlrRow($db, $row);
            $retired = $playerStats->isRetired;
            if ($retired == 0) {
                $name = $playerStats->name;
                $pid = $playerStats->playerID;
                $gm = number_format($playerStats->careerGamesPlayed);
                $min = number_format($playerStats->careerMinutesPlayed);
                $fgm = number_format($playerStats->careerFieldGoalsMade);
                $fga = number_format($playerStats->careerFieldGoalsAttempted);
                $fgp = number_format($playerStats->careerFieldGoalsAttempted ? round($playerStats->careerFieldGoalsMade / $playerStats->careerFieldGoalsAttempted, 3) : "0.000", 3);
                $ftm = number_format($playerStats->careerFreeThrowsMade);
                $fta = number_format($playerStats->careerFreeThrowsAttempted);
                $ftp = number_format($playerStats->careerFreeThrowsAttempted ? round($playerStats->careerFreeThrowsMade / $playerStats->careerFreeThrowsAttempted, 3) : "0.000", 3);
                $tgm = number_format($playerStats->careerThreePointersMade);
                $tga = number_format($playerStats->careerThreePointersAttempted);
                $tgp = number_format($playerStats->careerThreePointersAttempted ? round($playerStats->careerThreePointersMade / $playerStats->careerThreePointersAttempted, 3) : "0.000", 3);
                $orb = number_format($playerStats->careerOffensiveRebounds);
                $reb = number_format($playerStats->careerTotalRebounds);
                $ast = number_format($playerStats->careerAssists);
                $stl = number_format($playerStats->careerSteals);
                $to = number_format($playerStats->careerTurnovers);
                $blk = number_format($playerStats->careerBlocks);
                $pf = number_format($playerStats->careerPersonalFouls);
                $pts = number_format($playerStats->careerPoints);
            } else {
                $name = $row["name"] . "*";
                $pid = $row["pid"];
                $result_iblhist = $db->sql_query("SELECT
                    sum(gm) as gm,
                    sum(min) as min,
                    sum(fgm) as fgm,
                    sum(fga) as fga,
                    sum(ftm) as ftm,
                    sum(fta) as fta,
                    sum(3gm) as 3gm,
                    sum(3ga) as 3ga,
                    sum(orb) as orb,
                    sum(reb) as reb,
                    sum(ast) as ast,
                    sum(stl) as stl,
                    sum(blk) as blk,
                    sum(tvr) as tvr,
                    sum(pf) as pf,
                    sum(ftm) + sum(3gm) + (2 * sum(fgm)) as pts
                    FROM ibl_hist
                    WHERE pid = $pid;");
                $hist = $db->sql_fetchrow($result_iblhist);
                $gm = number_format($hist["gm"]);
                $min = number_format($hist["min"]);
                $fgm = number_format($hist["fgm"]);
                $fga = number_format($hist["fga"]);
                $fgp = number_format($hist["fga"] ? round($hist["fgm"] / $hist["fga"], 3) : 0.000, 3);
                $ftm = number_format($hist["ftm"]);
                $fta = number_format($hist["fta"]);
                $ftp = number_format($hist["fta"] ? round($hist["ftm"] / $hist["fta"], 3) : 0.000, 3);
                $tgm = number_format($hist["3gm"]);
                $tga = number_format($hist["3ga"]);
                $tgp = number_format($hist["3ga"] ? round($hist["3gm"] / $hist["3ga"], 3) : 0.000, 3);
                $orb = number_format($hist["orb"]);
                $reb = number_format($hist["reb"]);
                $ast = number_format($hist["ast"]);
                $stl = number_format($hist["stl"]);
                $to = number_format($hist["tvr"]);
                $blk = number_format($hist["blk"]);
                $pf = number_format($hist["pf"]);
                $pts = number_format($hist["pts"]);
            }
        } elseif (
            $tableforquery == "ibl_season_career_avgs" ||
            $tableforquery == "ibl_heat_career_avgs" ||
            $tableforquery == "ibl_olympics_career_avgs" ||
            $tableforquery == "ibl_playoff_career_avgs"
        ) {
            $name = $row["name"];
            $pid = $row["pid"];
            $gm = round($row["games"]);
            $min = number_format(round($row["minutes"], 2), 2);
            $fgm = number_format(round($row["fgm"], 2), 2);
            $fga = number_format(round($row["fga"], 2), 2);
            $fgp = number_format(round($row["fgpct"], 3), 3);
            $ftm = number_format(round($row["ftm"], 2), 2);
            $fta = number_format(round($row["fta"], 2), 2);
            $ftp = number_format(round($row["ftpct"], 3), 3);
            $tgm = number_format(round($row["tgm"], 2), 2);
            $tga = number_format(round($row["tga"], 2), 2);
            $tgp = number_format(round($row["tpct"], 3), 3);
            $orb = number_format(round($row["orb"], 2), 2);
            $reb = number_format(round($row["reb"], 2), 2);
            $ast = number_format(round($row["ast"], 2), 2);
            $stl = number_format(round($row["stl"], 2), 2);
            $to = number_format(round($row["tvr"], 2), 2);
            $blk = number_format(round($row["blk"], 2), 2);
            $pf = number_format(round($row["pf"], 2), 2);
            $pts = number_format(round($row["pts"], 2), 2);
        } elseif (
            $tableforquery == "ibl_heat_career_totals" ||
            $tableforquery == "ibl_olympics_career_totals" ||
            $tableforquery == "ibl_playoff_career_totals"
        ) {
            $name = $row["name"];
            $pid = $row["pid"];
            $gm = number_format($row["games"]);
            $min = number_format($row["minutes"]);
            $fgm = number_format($row["fgm"]);
            $fga = number_format($row["fga"]);
            $fgp = number_format($row["fga"] ? round($row["fgm"] / $row["fga"], 3) : 0.000, 3);
            $ftm = number_format($row["ftm"]);
            $fta = number_format($row["fta"]);
            $ftp = number_format($row["fta"] ? round($row["ftm"] / $row["fta"], 3) : 0.000, 3);
            $tgm = number_format($row["tgm"]);
            $tga = number_format($row["tga"]);
            $tgp = number_format($row["tga"] ? round($row["tgm"] / $row["tga"], 3) : 0.000, 3);
            $orb = number_format($row["orb"]);
            $reb = number_format($row["reb"]);
            $ast = number_format($row["ast"]);
            $stl = number_format($row["stl"]);
            $to = number_format($row["tvr"]);
            $blk = number_format($row["blk"]);
            $pf = number_format($row["pf"]);
            $pts = number_format($row["pts"]);
        }

        echo "<tr>
            <td style=\"text-align: center;\">" . $rank . "</td>
            <td style=\"text-align: center;\"><a href=\"modules.php?name=Player&pa=showpage&pid=$pid\">$name</a></td>
            <td style=\"text-align: center;\">" . $gm . "</td>
            <td style=\"text-align: center;\">" . $min . "</td>
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