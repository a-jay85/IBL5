<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

global $leagueContext, $authService;
/** @var \League\LeagueContext $leagueContext */
/** @var \Auth\Contracts\AuthServiceInterface $authService */

// Auth guard, copied from modules/LeagueControlPanel/index.php. Order is load-bearing: authenticated, then admin.
if (!$authService->isAuthenticated()) {
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    if (is_string($queryString) && $queryString !== '') {
        $_SESSION['redirect_after_login'] = $queryString;
    }
    \Utilities\HtmxHelper::redirect('modules.php?name=YourAccount');
}

if (!$authService->isAdmin()) {
    http_response_code(403);
    echo 'Access denied. Administrator privileges required.';
    exit;
}

// Wire dependencies. Read-only page: GET filters only, never a POST read.
$factory = \Module\ModuleServices::current()->factory(\Module\Factories\TradeSuggestionsTuningFactory::class);

\PageLayout\PageLayout::header();
echo $factory->controller()->handle($_GET);
\PageLayout\PageLayout::footer();
