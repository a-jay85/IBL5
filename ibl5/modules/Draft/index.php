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
use Draft\DraftSelectionHandler;
use Draft\DraftView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$op = is_string($_REQUEST['op'] ?? null) ? $_REQUEST['op'] : '';

function userinfo($username)
{
    global $user_prefix, $mysqli_db;
    $commonRepository = new \Repositories\TeamIdentityRepository($mysqli_db);
    $season = new \Season\Season($mysqli_db);
    $repository = new DraftRepository($mysqli_db, $commonRepository);
    $view = new DraftView();

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
        $pickOwner = $repository->getCurrentOwnerOfDraftPick($season->endingYear, $draft_round, $draft_tid);
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

function submitDraftSelection(): void
{
    global $mysqli_db, $user, $cookie;

    // (1) Auth gate — op=select bypasses main()'s is_user() check.
    if (!is_user($user)) {
        loginbox();
        return;
    }

    // (2) CSRF — validate before any DB mutation. Inline error mirrors
    // DepthChartEntry::submit(); op=select already echoes inline HTML.
    if (!\Security\CsrfGuard::validateSubmittedToken('draft_selection')) {
        PageLayout\PageLayout::header();
        echo '<strong class="ibl-form-error">Invalid or expired form submission. Please reload and try again.</strong>';
        PageLayout\PageLayout::footer();
        return;
    }

    cookiedecode($user);
    $username = is_string($cookie[1] ?? null) ? $cookie[1] : '';

    $teamname = is_string($_POST['teamname'] ?? null) ? $_POST['teamname'] : '';
    $playerToBeDrafted = $_POST['player'] ?? null;
    $draft_round = (int) ($_POST['draft_round'] ?? 0);
    $draft_pick = (int) ($_POST['draft_pick'] ?? 0);

    $commonRepository = new \Repositories\TeamIdentityRepository($mysqli_db);

    // (3) Ownership — POST team must equal the session user's team.
    // Reject null / Free Agents so a teamless session cannot draft.
    $sessionTeam = $commonRepository->getTeamnameFromUsername($username);
    if ($sessionTeam === null
        || $sessionTeam === \League\League::FREE_AGENTS_TEAM_NAME
        || $sessionTeam !== $teamname) {
        PageLayout\PageLayout::header();
        echo '<strong class="ibl-form-error">You can only make selections for your own team.</strong>';
        PageLayout\PageLayout::footer();
        return;
    }

    $season = new \Season\Season($mysqli_db);
    $handler = new DraftSelectionHandler($mysqli_db, $commonRepository, $season);
    echo $handler->handleDraftSelection($teamname, $playerToBeDrafted, $draft_round, $draft_pick);
}

switch ($op) {
    case 'select':
        submitDraftSelection();
        break;
    default:
        main($user);
        break;
}
