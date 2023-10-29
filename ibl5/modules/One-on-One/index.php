<?php

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

require_once "mainfile.php";

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- $module_name";

oneonone();

function menu()
{
    echo "<center><b>
	<a href=\"modules.php?name=Player&pa=search\">Player Search</a>  |
	<a href=\"modules.php?name=Player&pa=awards\">Awards Search</a> |
	<a href=\"modules.php?name=One-on-One\">One-On-One Game</a> |
	<a href=\"modules.php?name=Leaderboards\">Career Leaderboards</a> (All Types)
	</b><hr>";
}

// ================================================================================
//
// One-On-One Code
//
// ================================================================================

function oneonone()
{
    global $prefix, $db, $user, $cookie;
    include "header.php";

    cookiedecode($user);

    $sql2 = "SELECT * FROM " . $prefix . "_users WHERE username='$cookie[1]'";
    $result2 = $db->sql_query($sql2);
    $num2 = $db->sql_numrows($result2);
    $userinfo = $db->sql_fetchrow($result2);

    $ownerplaying = stripslashes(check_html($userinfo['username'], "nohtml"));

    OpenTable();
    menu();

    $player1 = $_POST['pid1'];
    $player2 = $_POST['pid2'];
    $gameid = $_POST['gameid'];

    echo "<center><table><tr><th>One-on-One Match</th></tr></table></center>";

    // ===== SET FORM =====

    echo "<form name=\"OneOnOne\" method=\"post\" action=\"modules.php?name=One-on-One\">
    Player One: <select name=\"pid1\">";

    $query = "SELECT * FROM ibl_plr WHERE retired = '0' ORDER BY name ASC";
    $result = $db->sql_query($query);
    $num = $db->sql_numrows($result);

    $i = 0;
    while ($i < $num) {
        $playername = $db->sql_result($result, $i, "name");
        $pida = $db->sql_result($result, $i, "pid");
        if ($pida == $player1) {
            echo "<option value=\"$pida\" SELECTED>$playername</option>";
        } else {
            echo "<option value=\"$pida\">$playername</option>";
        }
        $i++;
    }

    echo "</select> | Player Two: <select name=\"pid2\">";
    $i = 0;
    while ($i < $num) {
        $playername = $db->sql_result($result, $i, "name");
        $pida = $db->sql_result($result, $i, "pid");
        if ($pida == $player2) {
            echo "<option value=\"$pida\" SELECTED>$playername</option>";
        } else {
            echo "<option value=\"$pida\">$playername</option>";
        }
        $i++;
    }

    echo "</select><input type=\"submit\" value=\"Begin One-on-One Match\"></form>

	<form name=\"LookUpOldGame\" method=\"post\" action=\"modules.php?name=One-on-One\">
	Review Old Game (Input Game ID): <input type=\"text\" name=\"gameid\" size=\"11\"><input type=\"submit\" value=\"Review Old Game\">
	</form>

	<hr>";

    // ===== END FORM

    // ===== PRINT THE RESULTS OF AN OLD GAME

    if ($gameid == null and $_GET['gameid'] == null) {
        // ===== IF VALUES ARE FOUND FOR P1 AND P2, AND THEY ARE DIFFERENT, RUN THE GAME

        $runstop = 0;
        if ($player1 == null) {
            echo "Please select a Player from the Player 1 Category.<br>";
            $runstop = 1;
        }
        if ($player2 == null) {
            echo "Please select a Player from the Player 2 Category.<br>";
            $runstop = 1;
        }
        if ($player1 == $player2) {
            echo "Please do not select the same player for both Player 1 and Player 2.<br>";
            $runstop = 1;
        }

        if ($runstop == 0) {
            rungame($player1, $player2, $ownerplaying);
        }

        // ===== ABOVE DISPLAYS A NEW GAME; ELSE PRINT THE OLD GAME RESULTS

    } else {
        if ($_GET['gameid'] != null) {
            $gameid = intval($_GET['gameid']);
        }
        printgame($gameid);
    }

    CloseTable();
    include "footer.php";
}

// ==================================
//
// BELOW ARE THE FUNCTIONS USED TO RUN ONE-ON-ONE GAME
//
// ==================================

// ===== A RETURN VALUE OF 1 INDICATES THE ATTEMPT IS BLOCKED

function shotblock($blockrating, $attemptrating)
{
    $block = 0;
    $makeblock1 = $blockrating + rand(1, 100) + rand(1, 100);
    $makeblock2 = $blockrating + rand(1, 100) + rand(1, 100);
    $makeblock3 = $blockrating + rand(1, 100) + rand(1, 100);
    $avoidblock1 = $attemptrating + rand(1, 100) + rand(1, 100);
    $avoidblock2 = $attemptrating + rand(1, 100) + rand(1, 100);
    $avoidblock3 = $attemptrating + rand(1, 100) + rand(1, 100);

    if ($makeblock1 > $avoidblock1) {
        $block = $block + 1;
    }
    if ($makeblock2 > $avoidblock2) {
        $block = $block + 1;
    }
    if ($makeblock3 > $avoidblock3) {
        $block = $block + 1;
    }

    if ($block == 3) {
        return 1;
    } else {
        return 0;
    }
}

// ===== A RETURN VALUE OF 1 INDICATES THE BALL IS STOLEN

function stealcheck($stealrating, $turnoverrating)
{
    $steal = 0;
    $makesteal1 = $stealrating + rand(1, 100) + rand(1, 100);
    $makesteal2 = $stealrating + rand(1, 100) + rand(1, 100);
    $makesteal3 = $stealrating + rand(1, 100) + rand(1, 100);
    $avoidsteal1 = $turnoverrating + rand(1, 100) + rand(1, 100);
    $avoidsteal2 = $turnoverrating + rand(1, 100) + rand(1, 100);
    $avoidsteal3 = $turnoverrating + rand(1, 100) + rand(1, 100);

    if ($makesteal1 > $avoidsteal1) {
        $steal = $steal + 1;
    }
    if ($makesteal2 > $avoidsteal2) {
        $steal = $steal + 1;
    }
    if ($makesteal3 > $avoidsteal3) {
        $steal = $steal + 1;
    }

    if ($steal == 3) {
        return 1;
    } else {
        return 0;
    }
}

// ===== A RETURN VALUE OF 1 INDICATES A FOUL

function foulcheck($foulrating, $drawfoulrating)
{
    $foul = 0;
    $drawfoul1 = $drawfoulrating + rand(1, 100) + rand(1, 100);
    $drawfoul2 = $drawfoulrating + rand(1, 100) + rand(1, 100);
    $drawfoul3 = $drawfoulrating + rand(1, 100) + rand(1, 100);
    $drawfoul4 = $drawfoulrating + rand(1, 100) + rand(1, 100);
    $drawfoul5 = $drawfoulrating + rand(1, 100) + rand(1, 100);
    $avoidfoul1 = $foulrating + rand(1, 100) + rand(1, 100);
    $avoidfoul2 = $foulrating + rand(1, 100) + rand(1, 100);
    $avoidfoul3 = $foulrating + rand(1, 100) + rand(1, 100);
    $avoidfoul4 = $foulrating + rand(1, 100) + rand(1, 100);
    $avoidfoul5 = $foulrating + rand(1, 100) + rand(1, 100);

    if ($drawfoul1 > $avoidfoul1) {
        $foul = $foul + 1;
    }
    if ($drawfoul2 > $avoidfoul2) {
        $foul = $foul + 1;
    }
    if ($drawfoul3 > $avoidfoul3) {
        $foul = $foul + 1;
    }
    if ($drawfoul4 > $avoidfoul4) {
        $foul = $foul + 1;
    }
    if ($drawfoul5 > $avoidfoul5) {
        $foul = $foul + 1;
    }

    if ($foul > 3) {
        return 1;
    } else {
        return 0;
    }
}

// ===== A RETURN OF 1 INDICATES A MADE BASKET

function shootball($basepercent, $offenserating, $defenserating)
{
    $shotchance = $basepercent + $offenserating - $defenserating * 2;
    $shotresult = rand(1, 100);

    if ($shotresult > $shotchance) {
        return 0;
    } else {
        return 1;
    }
}

// ===== A RETURN OF 0 INDICATES A DEFENSIVE BOARD, A RETURN OF 1 AN OFFENSIVE BOARD

function rebound($offrebound, $defrebound)
{
    $reboundmatrix = $offrebound + $defrebound + 50;
    $reboundresult = rand(1, $reboundmatrix);

    if ($reboundresult > $offrebound) {
        return 0;
    } else {
        return 1;
    }
}

// ===== THREE-POINTER IS 0, OUTSIDE TWO IS 1, DRIVE IS 2, POST IS 3

function selectshottype($outside, $drive, $post, $twochance, $threechance)
{
    $shotselection = $outside + $drive + $post;
    $shottype = rand(0, $shotselection - 1);
    if ($shottype < $outside) {
        $twoorthree = $twochance + $threechance;
        $picktwoorthree = rand(0, $twoorthree - 1);
        if ($picktwoorthree > $twochance) {
            return 0;
        } else {
            return 1;
        }
    } elseif ($shottype < ($outside + $drive)) {
        return 2;
    } else {
        return 3;
    }
}

// ===== RESULTS KEY =====
// 1 - FOUL
// 2 - STEAL
// 3 - BLOCKED THREE
// 4 - MISSED THREE
// 5 - MADE THREE
// 6 - BLOCKED OUTSIDE TWO
// 7 - MISSED OUTSIDE TWO
// 8 - MADE OUTSIDE TWO
// 9 - BLOCKED DRIVE TWO
// 10 - MISSED DRIVE TWO
// 11 - MADE DRIVE TWO
// 12 - BLOCKED POST TWO
// 13 - MISSED POST TWO
// 14 - MADE POST TWO
// ===== END RESULTS KEY =====

function runpossession($off_player_2ga, $off_player_2gp, $off_player_fta, $off_player_3ga, $off_player_3gp, $off_player_tvr, $off_player_oo, $off_player_do, $off_player_po, $def_player_stl, $def_player_blk, $def_player_foul, $def_player_od, $def_player_dd, $def_player_pd)
{
    $fouldifficulty = 5;

    if (foulcheck($def_player_foul, $off_player_fta)) {
        return 1;
    }

    if (stealcheck($def_player_stl, $off_player_tvr)) {
        return 2;
    }

    $shotroutine = selectshottype($off_player_oo, $off_player_do, $off_player_po, $off_player_2ga, $off_player_3ga);

    if ($shotroutine == 0) {
        if (shotblock($def_player_blk, $off_player_3ga)) {
            return 3;
        }

        if (foulcheck($def_player_foul, $off_player_fta)) {
            if (shootball($off_player_3gp - $fouldifficulty, $off_player_oo, $def_player_od)) {
                return 5;
            } else {
                return 1;
            }
        } else {
            if (shootball($off_player_3gp, $off_player_oo, $def_player_od)) {
                return 5;
            } else {
                return 4;
            }
        }

    } elseif ($shotroutine == 1) {

        if (shotblock($def_player_blk, $off_player_2ga)) {
            return 6;
        }

        if (foulcheck($def_player_foul, $off_player_fta)) {
            if (shootball($off_player_2gp - $fouldifficulty, $off_player_oo, $def_player_od)) {
                return 8;
            } else {
                return 1;
            }
        } else {
            if (shootball($off_player_2gp, $off_player_oo, $def_player_od)) {
                return 8;
            } else {
                return 7;
            }
        }

    } elseif ($shotroutine == 2) {

        if (shotblock($def_player_blk, $off_player_2ga)) {
            return 9;
        }

        if (foulcheck($def_player_foul, $off_player_fta)) {
            if (shootball($off_player_2gp - $fouldifficulty, $off_player_do, $def_player_dd)) {
                return 11;
            } else {
                return 1;
            }
        } else {
            if (shootball($off_player_2gp, $off_player_do, $def_player_dd)) {
                return 11;
            } else {
                return 10;
            }
        }

    } elseif ($shotroutine == 3) {

        if (shotblock($def_player_blk, $off_player_2ga)) {
            return 12;
        }

        if (foulcheck($def_player_foul, $off_player_fta)) {
            if (shootball($off_player_2gp - $fouldifficulty, $off_player_po, $def_player_pd)) {
                return 14;
            } else {
                return 1;
            }
        } else {
            if (shootball($off_player_2gp, $off_player_po, $def_player_pd)) {
                return 14;
            } else {
                return 13;
            }
        }
    }
}

// ===== FUN TEXTUAL DESCRIPTIONS =====

function threetext()
{
    $ThreeArray[] = "launches a three";
    $ThreeArray[] = "fires from downtown";
    $ThreeArray[] = "shoots from beyond the arc";
    $ThreeArray[] = "tosses up a trey";
    $ThreeArray[] = "attempts a trifecta";
    $ThreeArray[] = "guns up a three-pointer";
    $ThreeArray[] = "chucks a long-range bomb";
    $ThreeArray[] = "takes a shot from outside the arc";
    $descriptionnumber = rand(0, 7);
    return $ThreeArray[$descriptionnumber];
}

function outsidetwotext()
{
    $OutsideArray[] = "takes a perimeter shot";
    $OutsideArray[] = "from just inside the arc";
    $OutsideArray[] = "gets off a long two";
    $OutsideArray[] = "sets, fires, ";
    $OutsideArray[] = "throws up a long jump shot";
    $OutsideArray[] = "fires a shot from near the top of the key";
    $OutsideArray[] = "pulls up along the baseline";
    $OutsideArray[] = "elevates for a J";
    $descriptionnumber = rand(0, 7);
    return $OutsideArray[$descriptionnumber];
}

function drivetext()
{
    $DriveArray[] = "slashes into the lane";
    $DriveArray[] = "drives to the basket";
    $DriveArray[] = "lifts up a teardrop on the drive";
    $DriveArray[] = "gets free for a drive with a nifty crossover";
    $DriveArray[] = "fakes left and drives right";
    $DriveArray[] = "floats a hanging jumper on the move";
    $DriveArray[] = "squeezes off a leaping leaner";
    $DriveArray[] = "spins into the paint on a drive";
    $descriptionnumber = rand(0, 7);
    return $DriveArray[$descriptionnumber];
}

function posttext()
{
    $PostArray[] = "with a jump hook from the low block";
    $PostArray[] = "takes a sweeping skyhook as he powers into the lane";
    $PostArray[] = "backs down and takes a little turnaround jumper";
    $PostArray[] = "uses a drop-step to try a layup";
    $PostArray[] = "elevates inside for a dunk";
    $PostArray[] = "goes to the up-and-under move";
    $PostArray[] = "lofts up a soft fadeaway from the paint";
    $PostArray[] = "flips up a finger roll";
    $descriptionnumber = rand(0, 7);
    return $PostArray[$descriptionnumber];
}

function madeshottext()
{
    $MadeShotArray[] = "and connects!";
    $MadeShotArray[] = "and hits!";
    $MadeShotArray[] = "and scores!";
    $MadeShotArray[] = "and knocks it down!";
    $MadeShotArray[] = "and knocks down the shot!";
    $MadeShotArray[] = "that rattles around an in!";
    $MadeShotArray[] = "that hits nothing but net!";
    $MadeShotArray[] = "that tickles the twine as it goes in!";
    $MadeShotArray[] = "that swishes cleanly through the net!";
    $MadeShotArray[] = "and it drops through the bucket!";
    $MadeShotArray[] = "and gets it to drop!";
    $MadeShotArray[] = "and practically wills it home!";
    $MadeShotArray[] = "and hits the shot!";
    $MadeShotArray[] = "that bounces off the front of the rim, off the back of the rim, then drops!";
    $MadeShotArray[] = "that hangs on the lip of the rim, but drops!";
    $MadeShotArray[] = "that drops through the hoop!";
    $MadeShotArray[] = "and makes the basket!";
    $MadeShotArray[] = "and gets the bucket!";
    $descriptionnumber = rand(0, 17);
    return $MadeShotArray[$descriptionnumber];
}

function missedshottext()
{
    $MissedShotArray[] = "and misses.";
    $MissedShotArray[] = "and clanks it off the front of the iron.";
    $MissedShotArray[] = "but the shot is off-line.";
    $MissedShotArray[] = "and it's an airball!";
    $MissedShotArray[] = "but comes up empty.";
    $MissedShotArray[] = "and the shot is a bit long.";
    $MissedShotArray[] = "and it rattles around and out.";
    $MissedShotArray[] = "that hangs on the lip of the rim before falling out.";
    $MissedShotArray[] = "that caroms off the rim.";
    $MissedShotArray[] = "but can't connect.";
    $MissedShotArray[] = "and comes up dry.";
    $MissedShotArray[] = "but can't get it to fall.";
    $MissedShotArray[] = "and the shot comes up short.";
    $MissedShotArray[] = "but it's no good.";
    $MissedShotArray[] = "and bounces it off the glass and out.";
    $MissedShotArray[] = "that spins out.";
    $MissedShotArray[] = "and the ball just won't stay down.";
    $MissedShotArray[] = "and somehow the ball stays out.";
    $descriptionnumber = rand(0, 17);
    return $MissedShotArray[$descriptionnumber];
}

function blocktext()
{
    $BlockArray[] = "knocks it away.";
    $BlockArray[] = "deflects the shot.";
    $BlockArray[] = "swats it away.";
    $BlockArray[] = "gets a hand on the shot.";
    $BlockArray[] = "slaps the ball away.";
    $BlockArray[] = "tips the shot attempt away.";
    $BlockArray[] = "comes up with the block.";
    $BlockArray[] = "recovers in time to get a piece of the shot.";
    $descriptionnumber = rand(0, 7);
    return $BlockArray[$descriptionnumber];
}

function stealtext()
{
    $StealArray[] = "swipes the ball from";
    $StealArray[] = "gets a clean pick of";
    $StealArray[] = "grabs the ball right out of the hands of";
    $StealArray[] = "steals the ball from";
    $StealArray[] = "comes up with a steal from";
    $StealArray[] = "strips the ball away from";
    $StealArray[] = "pokes the ball away and gets the steal from";
    $StealArray[] = "pilfers the ball from";
    $descriptionnumber = rand(0, 7);
    return $StealArray[$descriptionnumber];
}

// ===== THIS IS THE MAIN FUNCTION THAT RUNS THE GAME =====

function rungame($p1, $p2, $owner)
{
    global $db;

    $query1 = "SELECT * FROM ibl_plr WHERE pid = $p1";
    $result1 = $db->sql_query($query1);

    $p1_name = $db->sql_result($result1, 0, "name");
    $p1_oo = $db->sql_result($result1, 0, "oo");
    $p1_do = $db->sql_result($result1, 0, "do");
    $p1_po = $db->sql_result($result1, 0, "po");
    $p1_od = $db->sql_result($result1, 0, "od");
    $p1_dd = $db->sql_result($result1, 0, "dd");
    $p1_pd = $db->sql_result($result1, 0, "pd");

    $p1_2ga = $db->sql_result($result1, 0, "r_fga");
    $p1_2gp = $db->sql_result($result1, 0, "r_fgp");
    $p1_fta = $db->sql_result($result1, 0, "r_fta");
    $p1_3ga = $db->sql_result($result1, 0, "r_tga");
    $p1_3gp = $db->sql_result($result1, 0, "r_tgp");
    $p1_orb = $db->sql_result($result1, 0, "r_orb");
    $p1_drb = $db->sql_result($result1, 0, "r_drb");
    $p1_stl = $db->sql_result($result1, 0, "r_stl");
    $p1_tvr = $db->sql_result($result1, 0, "r_to");
    $p1_blk = $db->sql_result($result1, 0, "r_blk");
    $p1_foul = $db->sql_result($result1, 0, "r_foul");

    $query2 = "SELECT * FROM ibl_plr WHERE pid = $p2";
    $result2 = $db->sql_query($query2);

    $p2_name = $db->sql_result($result2, 0, "name");
    $p2_oo = $db->sql_result($result2, 0, "oo");
    $p2_do = $db->sql_result($result2, 0, "do");
    $p2_po = $db->sql_result($result2, 0, "po");
    $p2_od = $db->sql_result($result2, 0, "od");
    $p2_dd = $db->sql_result($result2, 0, "dd");
    $p2_pd = $db->sql_result($result2, 0, "pd");
    $p2_2ga = $db->sql_result($result2, 0, "r_fga");
    $p2_2gp = $db->sql_result($result2, 0, "r_fgp");
    $p2_3ga = $db->sql_result($result2, 0, "r_tga");
    $p2_3gp = $db->sql_result($result2, 0, "r_tgp");
    $p2_fta = $db->sql_result($result2, 0, "r_fta");
    $p2_orb = $db->sql_result($result2, 0, "r_orb");
    $p2_drb = $db->sql_result($result2, 0, "r_drb");
    $p2_stl = $db->sql_result($result2, 0, "r_stl");
    $p2_tvr = $db->sql_result($result2, 0, "r_to");
    $p2_blk = $db->sql_result($result2, 0, "r_blk");
    $p2_foul = $db->sql_result($result2, 0, "r_foul");

    $p1_stats_fgm = 0;
    $p1_stats_fga = 0;
    $p1_stats_3gm = 0;
    $p1_stats_3ga = 0;
    $p1_stats_orb = 0;
    $p1_stats_reb = 0;
    $p1_stats_stl = 0;
    $p1_stats_blk = 0;
    $p1_stats_to = 0;
    $p1_stats_foul = 0;

    $p2_stats_fgm = 0;
    $p2_stats_fga = 0;
    $p2_stats_3gm = 0;
    $p2_stats_3ga = 0;
    $p2_stats_orb = 0;
    $p2_stats_reb = 0;
    $p2_stats_stl = 0;
    $p2_stats_blk = 0;
    $p2_stats_to = 0;
    $p2_stats_foul = 0;

    // === COIN TOSS

    $possession = 1;
    $coinflip = rand(1, 2);
    echo "The opening coin flip is ";
    if ($coinflip == 1) {
        echo " heads, so $p1_name gets the ball to start.<br>";
        $possession = 1;
    } else {
        echo " tails, so $p2_name gets the ball to start.<br>";
        $possession = 2;
    }

    // ===== SET SCORES TO ZERO

    $hiscore = 0;
    $score1 = 0;
    $score2 = 0;

    // ===== ONE-ON-ONE GAME START
    $playbyplay = null;
    while ($hiscore < 21) {
        if ($possession == 1) {
            $possessionresult = runpossession($p1_2ga, $p1_2gp, $p1_fta, $p1_3ga, $p1_3gp, $p1_tvr, $p1_oo, $p1_do, $p1_po, $p2_stl, $p2_blk, $p2_foul, $p2_od, $p2_dd, $p2_pd);
            if ($possessionresult == 1) {
                $playbyplay = $playbyplay . "$p2_name fouls $p1_name.<br>";
                $p2_stats_foul = $p2_stats_foul + 1;
                $looseball = 0;
                $possession = 1;
            } elseif ($possessionresult == 2) {
                $playbyplay = $playbyplay . "$p2_name " . stealtext() . " $p1_name.<br>";
                $looseball = 0;
                $possession = 2;
                $p1_stats_to = $p1_stats_to + 1;
                $p2_stats_stl = $p2_stats_stl + 1;
            } elseif ($possessionresult == 3) {
                $playbyplay = $playbyplay . "$p1_name " . threetext() . " but $p2_name " . blocktext() . "<br>";
                $p1_stats_fga = $p1_stats_fga + 1;
                $p1_stats_3ga = $p1_stats_3ga + 1;
                $p2_stats_blk = $p2_stats_blk + 1;
                $looseball = 1;
            } elseif ($possessionresult == 4) {
                $playbyplay = $playbyplay . "$p1_name " . threetext() . " " . missedshottext() . "<br>";
                $p1_stats_fga = $p1_stats_fga + 1;
                $p1_stats_3ga = $p1_stats_3ga + 1;
                $looseball = 1;
            } elseif ($possessionresult == 5) {
                $playbyplay = $playbyplay . "$p1_name " . threetext() . " " . madeshottext() . "<br>";
                $p1_stats_fga = $p1_stats_fga + 1;
                $p1_stats_3ga = $p1_stats_3ga + 1;
                $p1_stats_fgm = $p1_stats_fgm + 1;
                $p1_stats_3gm = $p1_stats_3gm + 1;
                $looseball = 0;
                $possession = 2;
                $score1 = $score1 + 3;
            } elseif ($possessionresult == 6) {
                $playbyplay = $playbyplay . "$p1_name " . outsidetwotext() . " but $p2_name " . blocktext() . "<br>";
                $p1_stats_fga = $p1_stats_fga + 1;
                $p2_stats_blk = $p2_stats_blk + 1;
                $looseball = 1;
            } elseif ($possessionresult == 7) {
                $playbyplay = $playbyplay . "$p1_name " . outsidetwotext() . " " . missedshottext() . "<br>";
                $p1_stats_fga = $p1_stats_fga + 1;
                $looseball = 1;
            } elseif ($possessionresult == 8) {
                $playbyplay = $playbyplay . "$p1_name " . outsidetwotext() . " " . madeshottext() . "<br>";
                $p1_stats_fga = $p1_stats_fga + 1;
                $p1_stats_fgm = $p1_stats_fgm + 1;
                $looseball = 0;
                $possession = 2;
                $score1 = $score1 + 2;
            } elseif ($possessionresult == 9) {
                $playbyplay = $playbyplay . "$p1_name " . drivetext() . " but $p2_name " . blocktext() . "<br>";
                $p1_stats_fga = $p1_stats_fga + 1;
                $p2_stats_blk = $p2_stats_blk + 1;
                $looseball = 1;
            } elseif ($possessionresult == 10) {
                $playbyplay = $playbyplay . "$p1_name " . drivetext() . " " . missedshottext() . "<br>";
                $p1_stats_fga = $p1_stats_fga + 1;
                $looseball = 1;
            } elseif ($possessionresult == 11) {
                $playbyplay = $playbyplay . "$p1_name " . drivetext() . " " . madeshottext() . "<br>";
                $p1_stats_fga = $p1_stats_fga + 1;
                $p1_stats_fgm = $p1_stats_fgm + 1;
                $looseball = 0;
                $possession = 2;
                $score1 = $score1 + 2;
            } elseif ($possessionresult == 12) {
                $playbyplay = $playbyplay . "$p1_name " . posttext() . " but $p2_name " . blocktext() . "<br>";
                $p1_stats_fga = $p1_stats_fga + 1;
                $p2_stats_blk = $p2_stats_blk + 1;
                $looseball = 1;
            } elseif ($possessionresult == 13) {
                $playbyplay = $playbyplay . "$p1_name " . posttext() . " " . missedshottext() . "<br>";
                $p1_stats_fga = $p1_stats_fga + 1;
                $looseball = 1;
            } elseif ($possessionresult == 14) {
                $playbyplay = $playbyplay . "$p1_name " . posttext() . " " . madeshottext() . "<br>";
                $p1_stats_fga = $p1_stats_fga + 1;
                $p1_stats_fgm = $p1_stats_fgm + 1;
                $looseball = 0;
                $possession = 2;
                $score1 = $score1 + 2;
            }
        } else {
            $possessionresult = runpossession($p2_2ga, $p2_2gp, $p2_fta, $p2_3ga, $p2_3gp, $p2_tvr, $p2_oo, $p2_do, $p2_po, $p1_stl, $p1_blk, $p1_foul, $p1_od, $p1_dd, $p1_pd);
            if ($possessionresult == 1) {
                $playbyplay = $playbyplay . "$p1_name fouls $p2_name.<br>";
                $p1_stats_foul = $p1_stats_foul + 1;
                $looseball = 0;
                $possession = 2;
            } elseif ($possessionresult == 2) {
                $playbyplay = $playbyplay . "$p1_name " . stealtext() . " $p2_name.<br>";
                $looseball = 0;
                $possession = 1;
                $p2_stats_to = $p2_stats_to + 1;
                $p1_stats_stl = $p1_stats_stl + 1;
            } elseif ($possessionresult == 3) {
                $playbyplay = $playbyplay . "$p2_name " . threetext() . " but $p1_name " . blocktext() . "<br>";
                $p2_stats_fga = $p2_stats_fga + 1;
                $p2_stats_3ga = $p2_stats_3ga + 1;
                $p1_stats_blk = $p1_stats_blk + 1;
                $looseball = 1;
            } elseif ($possessionresult == 4) {
                $playbyplay = $playbyplay . "$p2_name " . threetext() . " " . missedshottext() . ".<br>";
                $p2_stats_fga = $p2_stats_fga + 1;
                $p2_stats_3ga = $p2_stats_3ga + 1;
                $looseball = 1;
            } elseif ($possessionresult == 5) {
                $playbyplay = $playbyplay . "$p2_name " . threetext() . " " . madeshottext() . "<br>";
                $p2_stats_fga = $p2_stats_fga + 1;
                $p2_stats_3ga = $p2_stats_3ga + 1;
                $p2_stats_fgm = $p2_stats_fgm + 1;
                $p2_stats_3gm = $p2_stats_3gm + 1;
                $looseball = 0;
                $possession = 1;
                $score2 = $score2 + 3;
            } elseif ($possessionresult == 6) {
                $playbyplay = $playbyplay . "$p2_name " . outsidetwotext() . " but $p1_name " . blocktext() . "<br>";
                $p2_stats_fga = $p2_stats_fga + 1;
                $p1_stats_blk = $p1_stats_blk + 1;
                $looseball = 1;
            } elseif ($possessionresult == 7) {
                $playbyplay = $playbyplay . "$p2_name " . outsidetwotext() . " " . missedshottext() . "<br>";
                $p2_stats_fga = $p2_stats_fga + 1;
                $looseball = 1;
            } elseif ($possessionresult == 8) {
                $playbyplay = $playbyplay . "$p2_name " . outsidetwotext() . " " . madeshottext() . "<br>";
                $p2_stats_fga = $p2_stats_fga + 1;
                $p2_stats_fgm = $p2_stats_fgm + 1;
                $looseball = 0;
                $possession = 1;
                $score2 = $score2 + 2;
            } elseif ($possessionresult == 9) {
                $playbyplay = $playbyplay . "$p2_name " . drivetext() . " but $p1_name " . blocktext() . "<br>";
                $p2_stats_fga = $p2_stats_fga + 1;
                $p1_stats_blk = $p1_stats_blk + 1;
                $looseball = 1;
            } elseif ($possessionresult == 10) {
                $playbyplay = $playbyplay . "$p2_name " . drivetext() . " " . missedshottext() . "<br>";
                $p2_stats_fga = $p2_stats_fga + 1;
                $looseball = 1;
            } elseif ($possessionresult == 11) {
                $playbyplay = $playbyplay . "$p2_name " . drivetext() . " " . madeshottext() . "<br>";
                $p2_stats_fga = $p2_stats_fga + 1;
                $p2_stats_fgm = $p2_stats_fgm + 1;
                $looseball = 0;
                $possession = 1;
                $score2 = $score2 + 2;
            } elseif ($possessionresult == 12) {
                $playbyplay = $playbyplay . "$p2_name " . posttext() . " but $p1_name " . blocktext() . "<br>";
                $p2_stats_fga = $p2_stats_fga + 1;
                $p1_stats_blk = $p1_stats_blk + 1;
                $looseball = 1;
            } elseif ($possessionresult == 13) {
                $playbyplay = $playbyplay . "$p2_name " . posttext() . " " . missedshottext() . "<br>";
                $p2_stats_fga = $p2_stats_fga + 1;
                $looseball = 1;
            } elseif ($possessionresult == 14) {
                $playbyplay = $playbyplay . "$p2_name " . posttext() . " " . madeshottext() . "<br>";
                $p2_stats_fga = $p2_stats_fga + 1;
                $p2_stats_fgm = $p2_stats_fgm + 1;
                $looseball = 0;
                $possession = 1;
                $score2 = $score2 + 2;
            }
        }

        // === END SHOOTING ROUTINE, CHECK FOR LOOSE BALL
        if ($looseball == 1) {
            if ($possession == 1) {
                if (rebound($p1_orb, $p2_drb)) {
                    $playbyplay = $playbyplay . "$p1_name gets the (offensive) rebound.<br>";
                    $possession = 1;
                    $p1_stats_orb = $p1_stats_orb + 1;
                    $p1_stats_reb = $p1_stats_reb + 1;
                } else {
                    $playbyplay = $playbyplay . "$p2_name gets the rebound.<br>";
                    $possession = 2;
                    $p2_stats_reb = $p2_stats_reb + 1;
                }
            } else {
                if (rebound($p2_orb, $p1_drb)) {
                    $playbyplay = $playbyplay . "$p2_name gets the (offensive) rebound.<br>";
                    $possession = 2;
                    $p2_stats_orb = $p2_stats_orb + 1;
                    $p2_stats_reb = $p2_stats_reb + 1;
                } else {
                    $playbyplay = $playbyplay . "$p1_name gets the rebound.<br>";
                    $possession = 1;
                    $p1_stats_reb = $p1_stats_reb + 1;
                }
            }
        }

        // === UPDATE HISCORE VARIABLE

        if ($score1 > $score2) {
            $hiscore = $score1;
        } else {
            $hiscore = $score2;
        }
        $playbyplay = $playbyplay . "<B>SCORE: $p1_name $score1, $p2_name $score2</B><p>";
        // ===== END OF POSSESSION
    }

    $playbyplay = $playbyplay . "<table border=1><tr><td colspan=11><font color=#ff0000><b>FINAL SCORE: $p1_name $score1, $p2_name $score2</b></font></td></tr>
	<tr><td>Name</td><td>FGM</td><td>FGA</td><td>3GM</td><td>3GA</td><td>ORB</td><td>REB</td><td>STL</td><td>BLK</td><td>TVR</td><td>FOUL</td></tr>
	<tr><td>$p1_name</td><td>$p1_stats_fgm</td><td>$p1_stats_fga</td><td>$p1_stats_3gm</td><td>$p1_stats_3ga</td><td>$p1_stats_orb</td><td>$p1_stats_reb</td><td>$p1_stats_stl</td><td>$p1_stats_blk</td><td>$p1_stats_to</td><td>$p1_stats_foul</td></tr>
	<tr><td>$p2_name</td><td>$p2_stats_fgm</td><td>$p2_stats_fga</td><td>$p2_stats_3gm</td><td>$p2_stats_3ga</td><td>$p2_stats_orb</td><td>$p2_stats_reb</td><td>$p2_stats_stl</td><td>$p2_stats_blk</td><td>$p2_stats_to</td><td>$p2_stats_foul</td></tr>
	</table>
	";

    echo "$playbyplay";

    $querygetgameid = "SELECT * FROM ibl_one_on_one";
    $resultgetgameid = $db->sql_query($querygetgameid);
    $gameid = $db->sql_numrows($resultgetgameid) + 1;

    if ($score1 > $score2) {
        $winner = addslashes($p1_name);
        $loser = addslashes($p2_name);
        $winscore = $score1;
        $lossscore = $score2;
    } else {
        $winner = addslashes($p2_name);
        $loser = addslashes($p1_name);
        $winscore = $score2;
        $lossscore = $score1;
    }

    $playbyplay2 = addslashes($playbyplay);

    $queryinsertgame = "INSERT INTO ibl_one_on_one (gameid, playbyplay, winner, loser, winscore, lossscore, owner) VALUES ('$gameid', '$playbyplay2', '$winner', '$loser', '$winscore', '$lossscore', '$owner')";
    $resultinsert = $db->sql_query($queryinsertgame);

    echo "GAME ID: $gameid";

    $discordText = "";
    $bang = "";

    if ($score1 > $score2) {
        $gamewinner = strtoupper($p1_name);
        if (abs($score1 - $score2) <= 3) {
            $bang = "__**BANG! BANG! OH WHAT A SHOT FROM $gamewinner!!!**__\n";
        }
        $discordText .= $bang;
        $discordText .= "**$p1_name $score1**, $p2_name $score2\n\t*(Game played by $owner)*\nhttp://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]?name=One-on-One&gameid=$gameid";
    } else {
        $gamewinner = strtoupper($p2_name);
        if (abs($score1 - $score2) <= 3) {
            $bang = "__**BANG! BANG! OH WHAT A SHOT FROM $gamewinner!!!**__\n";
        }
        $discordText .= $bang;
        $discordText .= "$p1_name $score1, **$p2_name $score2**\n\t*(Game played by $owner)*\nhttp://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]?name=One-on-One&gameid=$gameid";
    }

    Discord::postToChannel('#1v1-games', $discordText);
}

function printgame($gameid)
{
    global $db;

    $querygetgameid = "SELECT * FROM ibl_one_on_one WHERE gameid = '$gameid'";
    $resultgetgameid = $db->sql_query($querygetgameid);

    $gametext = stripslashes($db->sql_result($resultgetgameid, 0, "playbyplay"));
    $gamewinner = stripslashes($db->sql_result($resultgetgameid, 0, "winner"));
    $gameloser = stripslashes($db->sql_result($resultgetgameid, 0, "loser"));
    $gamewinscore = stripslashes($db->sql_result($resultgetgameid, 0, "winscore"));
    $gamelossscore = stripslashes($db->sql_result($resultgetgameid, 0, "lossscore"));
    $owner = stripslashes($db->sql_result($resultgetgameid, 0, "owner"));

    echo "<center><h2>Replay of Game Number $gameid<br>$gamewinner $gamewinscore, $gameloser $gamelossscore<br><small>(Game played by $owner)</small></h2></center> $gametext";
}

CloseTable();
include "footer.php";
