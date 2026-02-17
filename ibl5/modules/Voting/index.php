<?php

declare(strict_types=1);

/**
 * Voting Module - Display ballot form for All-Star and end-of-year voting
 *
 * Requires authenticated user. Shows checkboxes (ASG) or radio buttons (EOY)
 * for each voting category with player statistics.
 *
 * @see Voting\VotingBallotService For ballot data assembly
 * @see Voting\VotingBallotView For ballot form rendering
 */

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

use Voting\VotingBallotService;
use Voting\VotingBallotView;

/**
 * Display the ballot form for an authenticated user
 */
function userinfo(string $username): void
{
    global $mysqli_db;

    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    $season = new \Season($mysqli_db);
    $league = new \League($mysqli_db);

    // Use prepared statement to prevent SQL injection
    $userRow = \DatabaseConnection::fetchRow(
        "SELECT * FROM nuke_users WHERE username = ?",
        [$username]
    );

    if ($userRow === null) {
        Nuke\Header::header();
        echo '<div class="ibl-alert ibl-alert--error">User not found.</div>';
        Nuke\Footer::footer();
        return;
    }

    $voterTeamName = (string)($userRow['user_ibl_team'] ?? '');
    $tid = $commonRepository->getTidFromTeamname($voterTeamName);

    $formAction = ($season->phase === 'Regular Season')
        ? 'modules/Voting/ASGVote.php'
        : 'modules/Voting/EOYVote.php';

    // Assemble ballot data and render
    $service = new VotingBallotService($mysqli_db);
    $view = new VotingBallotView();

    $categories = $service->getBallotData($voterTeamName, $season, $league);

    Nuke\Header::header();
    echo $view->renderBallotForm($formAction, $voterTeamName, $tid, $season->phase, $categories);
    Nuke\Footer::footer();
}

/**
 * Entry point — check authentication before showing ballot
 *
 * @param mixed $user User authentication data
 */
function main(mixed $user): void
{
    global $stop;

    if (!is_user($user)) {
        Nuke\Header::header();
        if ($stop) {
            echo '<div class="ibl-alert ibl-alert--error">' . _LOGININCOR . '</div>';
        } else {
            echo '<div class="ibl-alert ibl-alert--error">' . _USERREGLOGIN . '</div>';
        }
        if (!is_user($user)) {
            loginbox();
        }
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        global $cookie;
        cookiedecode($user);
        userinfo((string)($cookie[1] ?? ''));
    }
}

switch ($op) {
    default:
        main($user);
        break;
}
