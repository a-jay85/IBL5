<?php

declare(strict_types=1);

global $authService, $prefix, $user;
/** @var \Auth\AuthService $authService */

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\PlayerFactory::class);

$module_name = basename(dirname(__FILE__));

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$pa       = is_string($_REQUEST['pa']       ?? null) ? $_REQUEST['pa']       : '';
$pid      = is_string($_REQUEST['pid']      ?? null) ? $_REQUEST['pid']      : null;
$pageView = is_string($_REQUEST['pageView'] ?? null) ? $_REQUEST['pageView'] : null;

$pagetitle = "- Player Archives";

switch ($pa) {

    case "negotiate":
        $actionController = $factory->actionController();
        $username = $authService->getUsername() ?? '';
        $debugSession = \Debug\DebugSession::forRequest(
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
        $actionController = $factory->actionController();
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
        $rookieController = $factory->rookieOptionController();
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
        $pageController = $factory->pageController();
        $username = $authService->getUsername() ?? '';
        $pageHtml = $pageController->showPage($pid, $pageView, $username);
        if ($pageController->responseStatus() !== 200) {
            http_response_code($pageController->responseStatus());
        }
        PageLayout\PageLayout::header();
        echo $pageHtml;
        PageLayout\PageLayout::footer();
        break;

    default:
        // No (or unrecognized) action: the Player module has no landing view, but
        // a bare `?name=Player` request must still paint content — a blank 200
        // body fails Lighthouse with NO_FCP. Render page chrome with a notice.
        PageLayout\PageLayout::header();
        echo '<div class="ibl-alert ibl-alert--info">No player selected. Choose a player from a roster, leaderboard, or search to view their archives.</div>';
        echo '<a href="index.php" class="ibl-btn ibl-btn--primary" style="margin-top: 0.5rem; display: inline-block;">Return to Home</a>';
        PageLayout\PageLayout::footer();
        break;
}
