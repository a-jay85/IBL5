<?php

declare(strict_types=1);

/**
 * SeasonRosterChanges Module - Display player transactions since last season
 *
 * Shows players who changed teams between seasons.
 *
 * @see SeasonRosterChanges\SeasonRosterChangesRepository For database operations
 * @see SeasonRosterChanges\SeasonRosterChangesView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\SeasonRosterChangesFactory::class);
$season = $factory->season();
$previousSeasonEndingYear = $season->endingYear - 1;

$pagetitle = "- Player Movement";

$repository = $factory->repository();
$view = $factory->view();

$movements = $repository->getSeasonRosterChanges($previousSeasonEndingYear);

PageLayout\PageLayout::header();
echo $view->render($movements);
PageLayout\PageLayout::footer();
