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
    global $user_prefix, $db;
    $commonRepository = new \Services\CommonRepository($db);
    $season = new Season($db);
    $repository = new DraftRepository($db);
    $view = new DraftView();

    $sql2 = "SELECT * FROM " . $user_prefix . "_users WHERE username = '$username'";
    $result2 = $db->sql_query($sql2);
    $userinfo = $db->sql_fetchrow($result2);

    Nuke\Header::header();

    OpenTable();

    $teamlogo = $userinfo['user_ibl_team'];
    $tid = $commonRepository->getTidFromTeamname($teamlogo);

    UI::displaytopmenu($db, $tid);

    // Get current draft pick information
    $currentPick = $repository->getCurrentDraftPick();

    $draft_team = $currentPick['team'];
    $draft_round = $currentPick['round'];
    $draft_pick = $currentPick['pick'];

    $pickOwner = $sharedFunctions->getCurrentOwnerOfDraftPick($season->endingYear, $draft_round, $draft_team);

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
