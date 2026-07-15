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

use GameBoxscore\GameBoxscoreRepository;
use GameBoxscore\GameBoxscoreService;
use GameBoxscore\GameBoxscoreView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

global $mysqli_db, $leagueContext;

$repository = new GameBoxscoreRepository($mysqli_db, $leagueContext);
$service = new GameBoxscoreService($repository);
$view = new GameBoxscoreView();

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
