<?php

declare(strict_types=1);

$phpSelfGuard = $_SERVER['PHP_SELF'] ?? '';
if (stripos(is_string($phpSelfGuard) ? $phpSelfGuard : '', "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$op     = is_string($_REQUEST['op']     ?? null) ? $_REQUEST['op']     : '';
$teamidInput = $_REQUEST['teamid'] ?? $_REQUEST['tid'] ?? $_REQUEST['teamID'] ?? null;
$teamid = is_numeric($teamidInput) ? (int) $teamidInput : 0;

$pagetitle = "- Team Pages";

global $mysqli_db, $authService, $leagueContext;
/** @var \mysqli $mysqli_db */
/** @var \Auth\Contracts\AuthServiceInterface $authService */
/** @var \League\LeagueContext $leagueContext */

$commonRepo = new \Repositories\TeamIdentityRepository($mysqli_db);
$teamRepository = new Team\TeamRepository($mysqli_db);
// Rivalries reads IBL tables only, so the Olympics Team page gets no rivals card.
$docRoot = is_string($_SERVER['DOCUMENT_ROOT'] ?? null) ? $_SERVER['DOCUMENT_ROOT'] : '';
$rivalsCardBuilder = $leagueContext->isOlympics()
    ? null
    : new Team\RivalsCardBuilder(
        \Rivalries\RivalriesServiceFactory::create($mysqli_db, \Season\SharedSeason::get($mysqli_db, $leagueContext), $docRoot . '/ibl5/images/logo'),
        fn (int $tid): ?string => $commonRepo->getOwnerName($tid),
    );
$service = new Team\TeamService($mysqli_db, $teamRepository, $leagueContext, rivalsCardBuilder: $rivalsCardBuilder);
$view = new Team\TeamView();
$controller = new Team\TeamController($mysqli_db, $commonRepo, $authService, $service, $view, \Http\HttpRequest::fromGlobals());

switch ($op) {
    case "team":
        $controller->displayTeamPage($teamid);
        break;

    case "api":
        $handler = new Team\TeamApiHandler($mysqli_db);
        $handler->handle();
        break;

    default:
        $controller->displayMenu();
        break;
}
