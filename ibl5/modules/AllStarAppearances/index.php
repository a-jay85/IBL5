<?php

declare(strict_types=1);

/**
 * AllStarAppearances Module - full all-star appearances list
 *
 * @see AllStarAppearances\AllStarAppearancesRepository For all-star queries
 * @see AllStarAppearances\AllStarAppearancesView For all-star rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$pagetitle = '- All-Star Appearances';

PageLayout\PageLayout::header();

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\AllStarAppearancesFactory::class);
$appearancesRepository = $factory->repository();
$appearancesView = $factory->view();
echo $appearancesView->render($appearancesRepository->getAllStarAppearances());

PageLayout\PageLayout::footer();
