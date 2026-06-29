<?php

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$op = is_string($_REQUEST['op'] ?? null) ? $_REQUEST['op'] : '';

$pagetitle = " - Depth Chart Entry";

global $mysqli_db, $commonRepo;
$commonRepo = new Repositories\TeamIdentityRepository($mysqli_db);

function userinfo($username)
{
    global $mysqli_db, $commonRepo, $leagueContext;

    $controller = new DepthChartEntry\DepthChartEntryController($mysqli_db, $commonRepo, $leagueContext);
    $controller->displayForm($username);
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

function renderInlineSubmitError(): void
{
    PageLayout\PageLayout::header();
    echo '<strong class="ibl-form-error">Invalid or expired form submission. Please reload and try again.</strong>';
    PageLayout\PageLayout::footer();
}

function submit($user)
{
    global $mysqli_db, $commonRepo, $leagueContext, $cookie;

    // Auth + ownership gate (IDOR fix D-09). The write target is derived from
    // the session team inside the handler, never from POST `Team_Name`, so an
    // authenticated GM cannot replay a valid CSRF token against another team.
    if (!is_user($user)) {
        renderInlineSubmitError();
        return;
    }

    cookiedecode($user);
    $username = is_string($cookie[1] ?? null) ? $cookie[1] : '';
    $sessionTeam = $commonRepo->getTeamnameFromUsername($username);
    if ($sessionTeam === null || $sessionTeam === '' || $sessionTeam === \League\League::FREE_AGENTS_TEAM_NAME) {
        renderInlineSubmitError();
        return;
    }

    // CSRF failure stays inline — no in-flight edits to preserve, and
    // "Please reload and try again" is already the correct instruction.
    // Every other outcome (success, validation fail, empty team name)
    // takes the PRG path below so Back never lands on a stale form with
    // a consumed token.
    if (!\Security\CsrfGuard::validateSubmittedToken('depth_chart')) {
        renderInlineSubmitError();
        return;
    }

    $controller = new DepthChartEntry\DepthChartEntryController($mysqli_db, $commonRepo, $leagueContext);
    $controller->handleSubmit($_POST, $username);
}

function tabApi()
{
    global $mysqli_db, $commonRepo, $leagueContext;

    $handler = new DepthChartEntry\DepthChartEntryApiHandler($mysqli_db, $commonRepo, $leagueContext);
    $handler->handle();
}

function nextSimApi()
{
    global $mysqli_db;

    $handler = new NextSim\NextSimTabApiHandler($mysqli_db);
    $handler->handle();
}

function api($user)
{
    global $mysqli_db, $cookie, $commonRepo;

    if (!is_user($user)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    cookiedecode($user);
    $username = $cookie[1];

    $teamName = $commonRepo->getTeamnameFromUsername($username);
    if ($teamName === null || $teamName === '' || $teamName === 'Free Agents') {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['error' => 'No team assigned']);
        return;
    }

    $teamid = $commonRepo->getTidFromTeamname($teamName) ?? 0;
    if ($teamid === 0) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(403);
        echo json_encode(['error' => 'Team not found']);
        return;
    }

    $action = $_GET['action'] ?? '';

    // For rename, use POST params; for list/load, use GET params
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawBody = file_get_contents('php://input');
        $params = is_string($rawBody) && $rawBody !== '' ? (json_decode($rawBody, true) ?? []) : [];
        if (!is_array($params)) {
            $params = [];
        }
    } else {
        $params = $_GET;
    }

    $handler = new SavedDepthChart\SavedDepthChartApiHandler($mysqli_db, $commonRepo);
    $handler->handle($action, $teamid, $username, $params);
}

switch ($op) {
    case "submit":
        submit($user);
        break;
    case "tab-api":
        tabApi();
        break;
    case "nextsim-api":
        nextSimApi();
        break;
    case "api":
        api($user);
        break;
    default:
        main($user);
        break;
}
