<?php

declare(strict_types=1);

/**
 * Compare Players Module
 *
 * Side-by-side comparison of two players' ratings, season stats, and career stats.
 *
 * @see ComparePlayers\ComparePlayersService For comparison logic
 * @see ComparePlayers\ComparePlayersView For HTML rendering
 */

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

/**
 * Display compare players interface
 */
function userinfo($username, $bypass = 0, $hid = 0, $url = 0): void
{
    global $user, $prefix, $user_prefix, $mysqli_db;
    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);

    $stmt = $mysqli_db->prepare("SELECT * FROM " . $user_prefix . "_users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result2 = $stmt->get_result();
    $userinfo = $result2->fetch_assoc() ?? [];
    if (!$bypass) {
        cookiedecode($user);
    }

    $teamlogo = $userinfo['user_ibl_team'] ?? '';
    $tid = $commonRepository->getTidFromTeamname($teamlogo);

    Nuke\Header::header();

    $repository = new \ComparePlayers\ComparePlayersRepository($mysqli_db);
    $service = new \ComparePlayers\ComparePlayersService($repository);
    $view = new \ComparePlayers\ComparePlayersView();

    $playerNames = $repository->getAllPlayerNames();

    if (!isset($_POST['Player1'])) {
        echo $view->renderSearchForm($playerNames);
    } else {
        $player1Name = filter_input(INPUT_POST, 'Player1', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';
        $player2Name = filter_input(INPUT_POST, 'Player2', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

        if (strlen($player1Name) > 100 || strlen($player2Name) > 100) {
            echo '<div class="ibl-empty-state"><p class="ibl-empty-state__text">Player names must be 100 characters or less.</p></div>';
            echo $view->renderSearchForm($playerNames);
            Nuke\Footer::footer();
            return;
        }

        $comparison = $service->comparePlayers($player1Name, $player2Name);

        if ($comparison !== null) {
            echo $view->renderSearchForm($playerNames);
            echo $view->renderComparisonResults($comparison);
        } else {
            echo '<div class="ibl-empty-state"><p class="ibl-empty-state__text">One or both players not found. Please check the player names and try again.</p></div>';
            echo $view->renderSearchForm($playerNames);
        }
    }

    Nuke\Footer::footer();
}

/**
 * Main entry point - validate user and display interface
 */
function main($user): void
{
    global $stop;
    if (!is_user($user)) {
        Nuke\Header::header();
        echo '<div class="ibl-empty-state"><p class="ibl-empty-state__text">' . ($stop ? _LOGININCOR : _USERREGLOGIN) . '</p></div>';
        if (!is_user($user)) {
            loginbox();
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
