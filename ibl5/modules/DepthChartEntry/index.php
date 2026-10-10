<?php

declare(strict_types=1);

$phpSelf = $_SERVER['PHP_SELF'] ?? '';
if (stripos(is_string($phpSelf) ? $phpSelf : '', "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$op = is_string($_REQUEST['op'] ?? null) ? $_REQUEST['op'] : '';

$pagetitle = " - Depth Chart Entry";

global $mysqli_db, $commonRepo, $user;
/** @var \mysqli $mysqli_db */
$commonRepo = new Repositories\TeamIdentityRepository($mysqli_db);

function userinfo(string $username): void
{
    global $mysqli_db, $commonRepo, $leagueContext;
    /** @var \mysqli $mysqli_db */
    /** @var \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepo */
    /** @var \League\LeagueContext $leagueContext */

    $repository = new DepthChart\DepthChartRepository($mysqli_db);
    $service = new DepthChart\DepthChartService();
    $view = new DepthChart\DepthChartView($leagueContext, $service);
    $teamRepository = new Team\TeamRepository($mysqli_db);
    $teamTableService = new Team\TeamTableService($mysqli_db, $teamRepository);
    $submissionHandler = new DepthChart\DepthChartSubmissionHandler($mysqli_db, $commonRepo);
    $controller = new DepthChart\DepthChartController($mysqli_db, $commonRepo, $repository, $service, $view, $teamTableService, $submissionHandler, \Http\HttpRequest::fromGlobals());
    $controller->displayForm($username);
}

function main(mixed $user): void
{
    if (!is_user($user)) {
        loginbox();
    } else {
        global $authService;
        /** @var \Auth\Contracts\AuthServiceInterface $authService */
        cookiedecode($user);
        userinfo($authService->getUsername() ?? '');
    }
}

function renderInlineSubmitError(): void
{
    PageLayout\PageLayout::header();
    echo '<strong class="ibl-form-error">Invalid or expired form submission. Please reload and try again.</strong>';
    PageLayout\PageLayout::footer();
}

function submit(mixed $user): void
{
    global $mysqli_db, $commonRepo, $leagueContext, $authService;
    /** @var \mysqli $mysqli_db */
    /** @var \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepo */
    /** @var \League\LeagueContext $leagueContext */
    /** @var \Auth\Contracts\AuthServiceInterface $authService */

    // Auth + ownership gate (IDOR fix D-09). The write target is derived from
    // the session team inside the handler, never from POST `Team_Name`, so an
    // authenticated GM cannot replay a valid CSRF token against another team.
    if (!is_user($user)) {
        renderInlineSubmitError();
        return;
    }

    cookiedecode($user);
    $username = $authService->getUsername() ?? '';
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

    $repository = new DepthChart\DepthChartRepository($mysqli_db);
    $service = new DepthChart\DepthChartService();
    $view = new DepthChart\DepthChartView($leagueContext, $service);
    $teamRepository = new Team\TeamRepository($mysqli_db);
    $teamTableService = new Team\TeamTableService($mysqli_db, $teamRepository);
    $submissionHandler = new DepthChart\DepthChartSubmissionHandler($mysqli_db, $commonRepo);
    $httpRequest = \Http\HttpRequest::fromGlobals();
    $controller = new DepthChart\DepthChartController($mysqli_db, $commonRepo, $repository, $service, $view, $teamTableService, $submissionHandler, $httpRequest);
    $controller->handleSubmit($httpRequest->allPost(), $username);
}

function tabApi(): void
{
    global $mysqli_db, $commonRepo, $leagueContext;
    /** @var \mysqli $mysqli_db */
    /** @var \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepo */
    /** @var \League\LeagueContext $leagueContext */

    $handler = new DepthChart\DepthChartApiHandler($mysqli_db, $commonRepo, $leagueContext);
    $handler->handle();
}

function nextSimApi(): void
{
    global $mysqli_db;
    /** @var \mysqli $mysqli_db */

    $handler = new NextSim\NextSimTabApiHandler($mysqli_db);
    $handler->handle();
}

function api(mixed $user): void
{
    global $mysqli_db, $commonRepo, $authService;
    /** @var \mysqli $mysqli_db */
    /** @var \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepo */
    /** @var \Auth\Contracts\AuthServiceInterface $authService */

    if (!is_user($user)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    cookiedecode($user);
    $username = $authService->getUsername() ?? '';

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

    $httpRequest = \Http\HttpRequest::fromGlobals();
    $actionRaw = $httpRequest->get('action');
    $action = is_string($actionRaw) ? $actionRaw : '';

    // For rename, use POST params; for list/load, use GET params
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rawBody = file_get_contents('php://input');
        $decoded = is_string($rawBody) && $rawBody !== '' ? (json_decode($rawBody, true) ?? []) : [];
        /** @var array<string, mixed> $params */
        $params = is_array($decoded) ? $decoded : [];
    } else {
        $params = $httpRequest->allGet();
    }

    $handler = new DepthChartSnapshot\DepthChartSnapshotApiHandler($mysqli_db);
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
