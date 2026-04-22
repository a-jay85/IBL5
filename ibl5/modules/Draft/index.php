<?php

declare(strict_types=1);

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
    $season = new \Season\Season($mysqli_db);
    $repository = new DraftRepository($mysqli_db);
    $view = new DraftView();
    $sharedRepository = new \Shared\SharedRepository($mysqli_db);

    PageLayout\PageLayout::header();

    $teamlogo = $commonRepository->getTeamnameFromUsername($username) ?? '';
    $teamid = $commonRepository->getTidFromTeamname($teamlogo);

    // Get current draft pick information
    $currentPick = $repository->getCurrentDraftPick();

    $draft_team = $currentPick['team'] ?? null;
    $draft_round = $currentPick['round'] ?? null;
    $draft_pick = $currentPick['pick'] ?? null;

    $draft_tid = $currentPick['teamid'] ?? 0;

    $pickOwner = null;
    if ($draft_round !== null && $draft_tid !== 0) {
        $pickOwner = $sharedRepository->getCurrentOwnerOfDraftPick($season->endingYear, $draft_round, $draft_tid);
    }

    // Get all draft class players
    $players = $repository->getAllDraftClassPlayers();

    // Render the draft interface
    echo $view->renderDraftInterface($players, $teamlogo, $pickOwner, $draft_round, $draft_pick, $season->endingYear, $teamid);

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
