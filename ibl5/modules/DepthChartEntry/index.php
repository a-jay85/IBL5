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

global $commonRepo, $nukeCompat, $user;
$commonRepo = \Module\ModuleServices::current()->teamIdentity();
$nukeCompat = \Module\ModuleServices::current()->nukeCompat();

function userinfo(string $username): void
{
    $factory = \Module\ModuleServices::current()->factory(\Module\Factories\DepthChartEntryFactory::class);
    $controller = $factory->controller();
    $controller->displayForm($username);
}

function main(mixed $user): void
{
    global $nukeCompat;
    /** @var \Utilities\NukeCompat $nukeCompat */

    if (!$nukeCompat->isUser($user)) {
        $nukeCompat->loginBox();
    } else {
        global $authService;
        /** @var \Auth\Contracts\AuthServiceInterface $authService */
        $nukeCompat->cookieDecode($user);
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
    global $commonRepo, $authService, $nukeCompat;
    /** @var \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepo */
    /** @var \Auth\Contracts\AuthServiceInterface $authService */
    /** @var \Utilities\NukeCompat $nukeCompat */

    // Auth + ownership gate (IDOR fix D-09). The write target is derived from
    // the session team inside the handler, never from POST `Team_Name`, so an
    // authenticated GM cannot replay a valid CSRF token against another team.
    if (!$nukeCompat->isUser($user)) {
        renderInlineSubmitError();
        return;
    }

    $nukeCompat->cookieDecode($user);
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

    $services = \Module\ModuleServices::current();
    $factory = $services->factory(\Module\Factories\DepthChartEntryFactory::class);
    $controller = $factory->controller();
    $controller->handleSubmit($services->request()->allPost(), $username);
}

function tabApi(): void
{
    $factory = \Module\ModuleServices::current()->factory(\Module\Factories\DepthChartEntryFactory::class);
    $handler = $factory->apiHandler();
    $handler->handle();
}

function nextSimApi(): void
{
    $factory = \Module\ModuleServices::current()->factory(\Module\Factories\DepthChartEntryFactory::class);
    $handler = $factory->nextSimTabApiHandler();
    $handler->handle();
}

function api(mixed $user): void
{
    global $commonRepo, $authService, $nukeCompat;
    /** @var \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepo */
    /** @var \Auth\Contracts\AuthServiceInterface $authService */
    /** @var \Utilities\NukeCompat $nukeCompat */

    if (!$nukeCompat->isUser($user)) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        return;
    }

    $nukeCompat->cookieDecode($user);
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

    $factory = \Module\ModuleServices::current()->factory(\Module\Factories\DepthChartEntryFactory::class);
    $handler = $factory->savedDepthChartApiHandler();
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
