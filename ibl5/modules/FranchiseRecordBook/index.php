<?php

declare(strict_types=1);

/**
 * FranchiseRecordBook Module - Per-team and league-wide all-time records
 *
 * Displays single-season and career records parsed from the JSB engine's .rcb file.
 * Supports team-specific views (best performances by franchise) and league-wide records.
 *
 * @see FranchiseRecordBook\FranchiseRecordBookRepository For database operations
 * @see FranchiseRecordBook\FranchiseRecordBookService For business logic
 * @see FranchiseRecordBook\FranchiseRecordBookView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

// Route HTMX API requests (no PageLayout, returns HTML fragment only)
$op = is_string($_GET['op'] ?? null) ? $_GET['op'] : '';
if ($op === 'api') {
    $factory = \Module\ModuleServices::current()->factory(\Module\Factories\FranchiseRecordBookFactory::class);
    $handler = $factory->apiHandler();
    $handler->handle();
    return;
}

PageLayout\PageLayout::header();

// Initialize services
$factory = \Module\ModuleServices::current()->factory(\Module\Factories\FranchiseRecordBookFactory::class);
$service = $factory->service();
$view = $factory->view();

// Determine which team to show (0 or missing = league-wide)
$teamId = 0;
if (is_string($_GET['teamid'] ?? null)) {
    $teamId = (int) $_GET['teamid'];
}

// Get record book data
if (\League\League::isRealFranchise($teamId)) {
    $data = $service->getTeamRecordBook($teamId);
} else {
    $data = $service->getLeagueRecordBook();
}

// Render output
echo $view->render($data);

PageLayout\PageLayout::footer();
