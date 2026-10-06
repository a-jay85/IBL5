<?php

declare(strict_types=1);

use Http\HttpRequest;
use Player\PlayerActionController;
use Player\PlayerPageController;
use RookieOption\RookieOptionController;
use Repositories\TeamIdentityRepository;
use Repositories\SalaryCapRepository;

global $mysqli_db, $commonRepository, $salaryCapRepo, $httpRequest, $authService, $prefix, $user;
/** @var \mysqli $mysqli_db */
/** @var \Auth\AuthService $authService */

$commonRepository = new TeamIdentityRepository($mysqli_db);
$salaryCapRepo = new SalaryCapRepository($mysqli_db);

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$pa       = is_string($_REQUEST['pa']       ?? null) ? $_REQUEST['pa']       : '';
$pid      = is_string($_REQUEST['pid']      ?? null) ? $_REQUEST['pid']      : null;
$pageView = is_string($_REQUEST['pageView'] ?? null) ? $_REQUEST['pageView'] : null;

// Single request snapshot for this request, injected into controllers so they
// never read superglobals themselves (maintenance items 14.8 + 14.12).
$httpRequest = HttpRequest::fromGlobals();

$pagetitle = "- Player Archives";

switch ($pa) {

    case "negotiate":
        $actionController = new PlayerActionController($mysqli_db, $commonRepository, $salaryCapRepo);
        $username = $authService->getUsername() ?? '';
        $debugSession = new \Debug\DebugSession(
            $username,
            $_SERVER['SERVER_NAME'] ?? null,
            $_COOKIE[\Debug\DebugSession::COOKIE_NAME] ?? null,
        );
        PageLayout\PageLayout::header();
        echo $actionController->renderNegotiation(
            intval($pid),
            $username,
            is_string($prefix) ? $prefix : '',
            $debugSession->isViewAllExtensionsEnabled(),
        );
        PageLayout\PageLayout::footer();
        break;

    case "rookieoption":
        $actionController = new PlayerActionController($mysqli_db, $commonRepository, $salaryCapRepo);
        $username = $authService->getUsername() ?? '';
        PageLayout\PageLayout::header();
        echo $actionController->renderRookieOption(
            (int) $pid,
            $username,
            is_string($_GET['error'] ?? null) ? $_GET['error'] : null,
            is_string($_GET['result'] ?? null) ? $_GET['result'] : null,
            is_string($_GET['from'] ?? null) ? $_GET['from'] : null,
        );
        PageLayout\PageLayout::footer();
        break;

    case "processrookieoption":
        $rookieController = new RookieOptionController(
            $mysqli_db,
            $commonRepository,
            new \RookieOption\RookieOptionRepository($mysqli_db),
            new \Topics\News\NewsRepository($mysqli_db),
        );
        $redirectUrl = $rookieController->handleSubmission(
            static fn (): bool => is_user($user) === 1,
            static fn (): bool => \Security\CsrfGuard::validateSubmittedToken('rookie_option'),
            static function () use ($user, $authService): string {
                cookiedecode($user);
                return $authService->getUsername() ?? '';
            },
            $_POST,
        );
        if ($redirectUrl === null) {
            loginbox();
        } else {
            \Utilities\HtmxHelper::redirect($redirectUrl);
        }
        break;

    case "showpage":
        $pageController = new PlayerPageController(
            $mysqli_db,
            $commonRepository,
            new \Player\PlayerPageService($mysqli_db, $commonRepository),
            $httpRequest,
        );
        $username = $authService->getUsername() ?? '';
        PageLayout\PageLayout::header();
        echo $pageController->showPage($pid, $pageView, $username);
        PageLayout\PageLayout::footer();
        break;

    default:
        // No (or unrecognized) action: the Player module has no landing view, but
        // a bare `?name=Player` request must still paint content — a blank 200
        // body fails Lighthouse with NO_FCP. Render page chrome with a notice.
        PageLayout\PageLayout::header();
        echo '<div class="ibl-alert ibl-alert--info">No player selected. Choose a player from a roster, leaderboard, or search to view their archives.</div>';
        echo '<a href="index.php" class="ibl-btn ibl-btn--primary mt-2 inline-block">Return to Home</a>';
        PageLayout\PageLayout::footer();
        break;
}
