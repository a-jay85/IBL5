<?php

declare(strict_types=1);

/**
 * PlayerMovement Module - Display player transactions since last season
 *
 * Shows players who changed teams between seasons.
 *
 * @see PlayerMovement\PlayerMovementRepository For database operations
 * @see PlayerMovement\PlayerMovementView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

use PlayerMovement\PlayerMovementRepository;
use PlayerMovement\PlayerMovementView;

global $mysqli_db;

$season = new Season($mysqli_db);
$previousSeasonEndingYear = $season->endingYear - 1;

$pagetitle = "- Player Movement";

$repository = new PlayerMovementRepository($mysqli_db);
$view = new PlayerMovementView();

$movements = $repository->getPlayerMovements($previousSeasonEndingYear);

PageLayout\PageLayout::header();
echo $view->render($movements);
PageLayout\PageLayout::footer();
