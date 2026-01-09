<?php

declare(strict_types=1);

/**
 * One-on-One Module - Player matchup game
 * 
 * This module allows users to simulate a One-on-One basketball game
 * between any two players in the league. Games are played to 21 points.
 * 
 * Refactored to use the interface-driven architecture pattern.
 * 
 * @see OneOnOne\OneOnOneService For business logic
 * @see OneOnOne\OneOnOneGameEngine For game simulation
 * @see OneOnOne\OneOnOneRepository For database operations
 * @see OneOnOne\OneOnOneView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use OneOnOne\OneOnOneRepository;
use OneOnOne\OneOnOneService;
use OneOnOne\OneOnOneGameEngine;
use OneOnOne\OneOnOneView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- $module_name";

oneonone();

function oneonone(): void
{
    global $prefix, $mysqli_db, $user, $cookie;
    
    Nuke\Header::header();
    cookiedecode($user);

    // Get current user info
    $stmt = $mysqli_db->prepare("SELECT * FROM " . $prefix . "_users WHERE username = ?");
    $stmt->bind_param('s', $cookie[1]);
    $stmt->execute();
    $result = $stmt->get_result();
    $userinfo = $result->fetch_assoc();
    $stmt->close();

    $ownerplaying = stripslashes(check_html($userinfo['username'] ?? '', "nohtml"));

    OpenTable();
    UI::playerMenu();

    // Get form inputs
    $player1 = isset($_POST['pid1']) ? (int) $_POST['pid1'] : null;
    $player2 = isset($_POST['pid2']) ? (int) $_POST['pid2'] : null;
    $gameid = isset($_POST['gameid']) ? (int) $_POST['gameid'] : null;
    $gameidGet = isset($_GET['gameid']) ? (int) $_GET['gameid'] : null;

    // Initialize services
    $repository = new OneOnOneRepository($mysqli_db);
    $gameEngine = new OneOnOneGameEngine();
    $service = new OneOnOneService($repository, $gameEngine);
    $view = new OneOnOneView();

    // Render header
    echo $view->renderHeader();

    // Get players for form
    $players = $service->getActivePlayers();

    // Render forms
    echo $view->renderPlayerSelectionForm($players, $player1, $player2);
    echo $view->renderGameLookupForm();

    // Handle request
    if ($gameid === null && $gameidGet === null) {
        // New game mode - check if we have valid player selections
        $errors = $service->validatePlayerSelection($player1, $player2);
        
        if (!empty($errors)) {
            echo $view->renderErrors($errors);
        } elseif ($player1 !== null && $player2 !== null) {
            // Run new game
            try {
                $result = $service->playGame($player1, $player2, $ownerplaying);
                echo $view->renderGameResult($result, $result->gameId);
            } catch (\Exception $e) {
                echo $view->renderErrors([$e->getMessage()]);
            }
        }
    } else {
        // Replay mode - show old game
        $replayId = $gameidGet ?? $gameid;
        if ($replayId !== null) {
            $gameData = $service->getGameReplay($replayId);
            if ($gameData !== null) {
                echo $view->renderGameReplay($gameData);
            } else {
                echo $view->renderErrors(["Game with ID $replayId not found."]);
            }
        }
    }

    CloseTable();
    Nuke\Footer::footer();
}
