<?php

declare(strict_types=1);

/**
 * GameBoxscore Module — renders one game's boxscore inside ibl5.
 * Ported from the retired IBL6 SvelteKit boxscore page.
 *
 * @see GameBoxscore\GameBoxscoreRepository
 * @see GameBoxscore\GameBoxscoreService
 * @see GameBoxscore\GameBoxscoreView
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\GameBoxscoreFactory::class);
$service = $factory->service();
$view = $factory->view();

$viewModel = $service->getBoxscore($_GET['date'] ?? null, $_GET['game'] ?? null);

if ($viewModel['found'] !== true) {
    http_response_code(404);
    $pagetitle = "- Boxscore Not Found";
} else {
    $pagetitle = "- Boxscore " . $viewModel['date'] . " Game " . $viewModel['gameOfThatDay'];
}

PageLayout\PageLayout::header();
echo $view->render($viewModel);
PageLayout\PageLayout::footer();
