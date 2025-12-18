<?php

/************************************************************************/
/* ibl College Scout Module added by Spencer Cooley                     */
/* 3/22/2005                                                            */
/************************************************************************/

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

use Draft\DraftRepository;
use Draft\DraftView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

function userinfo($username)
{
    global $user_prefix, $mysqli_db;
    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    $season = new Season($mysqli_db);
    $repository = new DraftRepository($mysqli_db);
    $view = new DraftView();
    $sharedFunctions = new \Shared($mysqli_db);

    $stmt = $mysqli_db->prepare("SELECT * FROM " . $user_prefix . "_users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result2 = $stmt->get_result();
    $userinfo = $result2->fetch_assoc();

    Nuke\Header::header();

    OpenTable();

    $teamlogo = $userinfo['user_ibl_team'];
    $tid = $commonRepository->getTidFromTeamname($teamlogo);

    UI::displaytopmenu($mysqli_db, $tid);

    // Get current draft pick information
    $currentPick = $repository->getCurrentDraftPick();

    $draft_team = $currentPick['team'] ?? null;
    $draft_round = $currentPick['round'] ?? null;
    $draft_pick = $currentPick['pick'] ?? null;

    $pickOwner = null;
    if ($draft_round !== null && $draft_team !== null) {
        $pickOwner = $sharedFunctions->getCurrentOwnerOfDraftPick($season->endingYear, $draft_round, $draft_team);
    }

    // Get all draft class players
    $players = $repository->getAllDraftClassPlayers();

    // Render the draft interface
    echo $view->renderDraftInterface($players, $teamlogo, $pickOwner, $draft_round, $draft_pick, $season->endingYear, $tid);

    CloseTable();
    Nuke\Footer::footer();
}

function main($user)
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
