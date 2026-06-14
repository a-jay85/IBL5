<?php

declare(strict_types=1);

/**
 * CapWhatIf Module - owner-only salary-cap "what-if" sandbox.
 *
 * Starts from the authenticated GM's real contracts and models two hypothetical
 * deltas (waive one player, add one signing) via {@see CapWhatIf\CapWhatIfService}.
 * The endpoint is an idempotent GET — it mutates no server state and carries no
 * CSRF token. The team is resolved server-side from the session identity; a
 * request-supplied `teamid` is never read.
 *
 * @see CapWhatIf\CapWhatIfService For scenario computation
 * @see CapWhatIf\CapWhatIfView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use CapWhatIf\CapWhatIfService;
use CapWhatIf\CapWhatIfView;
use League\League;
use Repositories\TeamIdentityRepository;

global $mysqli_db, $authService;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

PageLayout\PageLayout::header();

// Resolve the OWNER's team from session identity — never from the request.
$cookieArray = $authService->getCookieArray();
$username = is_array($cookieArray) && isset($cookieArray[1]) && is_string($cookieArray[1])
    ? $cookieArray[1]
    : '';

$commonRepo = new TeamIdentityRepository($mysqli_db);
$teamName = $commonRepo->getTeamnameFromUsername($username);

if ($teamName === null || $teamName === '' || $teamName === League::FREE_AGENTS_TEAM_NAME) {
    echo '<p class="ibl-centerbox">You must be a team GM to use the Cap Calculator.</p>';
    PageLayout\PageLayout::footer();
    return;
}

$teamid = $commonRepo->getTidFromTeamname($teamName) ?? 0;
if ($teamid === 0) {
    echo '<p class="ibl-centerbox">Team not found.</p>';
    PageLayout\PageLayout::footer();
    return;
}

// Scenario inputs come only from $_GET, each narrowed ($_GET values are mixed).
// teamid is NEVER read from the request.
$waivePid = isset($_GET['waive']) && is_numeric($_GET['waive']) ? (int) $_GET['waive'] : null;
$years = isset($_GET['years']) && is_numeric($_GET['years']) ? (int) $_GET['years'] : 0;
$salary = isset($_GET['salary']) && is_numeric($_GET['salary']) ? (int) $_GET['salary'] : 0;

$season = new \Season\Season($mysqli_db);
$teamQueryRepo = new \Team\TeamQueryRepository($mysqli_db);
$rosterPlayers = $teamQueryRepo->getRosterUnderContractOrderedByName($teamid);

$service = new CapWhatIfService($mysqli_db, $teamQueryRepo);
$view = new CapWhatIfView();

$scenarioData = $service->computeScenario($teamName, $teamid, $season, $waivePid, $years, $salary);

$beginningYear = $season->isOffseasonPhase() ? $season->beginningYear + 1 : $season->beginningYear;
$endingYear = $season->isOffseasonPhase() ? $season->endingYear + 1 : $season->endingYear;

echo $view->render($scenarioData, $rosterPlayers, $beginningYear, $endingYear);

PageLayout\PageLayout::footer();
