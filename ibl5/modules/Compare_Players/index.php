<?php

declare(strict_types=1);

/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2002 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/*                                                                      */
/* Refactored: December 2025                                            */
/* Modern implementation with interface-driven architecture             */
/************************************************************************/

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

/**
 * Display user info and compare players interface
 *
 * @param string $username Username of current user
 * @param int $bypass Bypass cookie validation (default: 0)
 * @param int $hid Hidden parameter (default: 0)
 * @param int $url URL parameter (default: 0)
 */
function userinfo($username, $bypass = 0, $hid = 0, $url = 0): void
{
    global $user, $prefix, $user_prefix, $db;
    $commonRepository = new \Services\CommonRepository($db);

    $sql2 = "SELECT * FROM " . $user_prefix . "_users WHERE username = '$username'";
    $result2 = $db->sql_query($sql2);
    $userinfo = $db->sql_fetchrow($result2);
    if (!$bypass) {
        cookiedecode($user);
    }

    $teamlogo = $userinfo['user_ibl_team'];
    $tid = $commonRepository->getTidFromTeamname($teamlogo);

    Nuke\Header::header();
    OpenTable();
    UI::displaytopmenu($db, $tid);

    // Initialize compare players classes
    $repository = new \ComparePlayers\ComparePlayersRepository($db);
    $service = new \ComparePlayers\ComparePlayersService($repository);
    $view = new \ComparePlayers\ComparePlayersView();

    // Get player names for autocomplete
    $playerNames = $service->getPlayerNames();

    if (!isset($_POST['Player1'])) {
        // Display search form
        echo $view->renderSearchForm($playerNames);
    } else {
        // Process comparison
        $player1Name = $_POST['Player1'] ?? '';
        $player2Name = $_POST['Player2'] ?? '';
        
        $comparison = $service->comparePlayers($player1Name, $player2Name);
        
        if ($comparison !== null) {
            echo $view->renderComparisonResults($comparison);
        } else {
            echo '<p style="color: red; font-weight: bold;">Error: One or both players not found. Please check the player names and try again.</p>';
            echo $view->renderSearchForm($playerNames);
        }
    }

    CloseTable();
    Nuke\Footer::footer();
}

/**
 * Main entry point - validate user and display interface
 *
 * @param mixed $user Current user data
 */
function main($user): void
{
    global $stop;
    if (!is_user($user)) {
        Nuke\Header::header();
        OpenTable();
        echo "<center><font class=\"title\"><b>" . ($stop ? _LOGININCOR : _USERREGLOGIN) . "</b></font></center>";
        CloseTable();
        echo "<br>";
        if (!is_user($user)) {
            OpenTable();
            loginbox();
            CloseTable();
        }
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        userinfo($cookie[1]);
    }
}

switch ($op) {
    default:
        main($user);
        break;
}
