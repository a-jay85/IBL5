<?php

/************************************************************************/
/* ibl College Scout Module added by Spencer Cooley                     */
/* 3/22/2005                                                            */
/************************************************************************/

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
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
    $sharedRepository = new \Shared\SharedRepository($mysqli_db);

    $stmt = $mysqli_db->prepare("SELECT * FROM " . $user_prefix . "_users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result2 = $stmt->get_result();
    $userinfo = $result2->fetch_assoc();

    PageLayout\PageLayout::header();

    $teamlogo = $userinfo['user_ibl_team'];
    $tid = $commonRepository->getTidFromTeamname($teamlogo);

    // Get current draft pick information
    $currentPick = $repository->getCurrentDraftPick();

    $draft_team = $currentPick['team'] ?? null;
    $draft_round = $currentPick['round'] ?? null;
    $draft_pick = $currentPick['pick'] ?? null;

    $pickOwner = null;
    if ($draft_round !== null && $draft_team !== null) {
        $pickOwner = $sharedRepository->getCurrentOwnerOfDraftPick($season->endingYear, $draft_round, $draft_team);
    }

    // Get all draft class players
    $players = $repository->getAllDraftClassPlayers();

    // Render the draft interface
    echo $view->renderDraftInterface($players, $teamlogo, $pickOwner, $draft_round, $draft_pick, $season->endingYear, $tid);

    PageLayout\PageLayout::footer();
}

function main($user)
{
    if (!is_user($user)) {
        loginbox();
    } else {
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
