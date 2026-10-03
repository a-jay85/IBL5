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

global $mysqli_db;
/** @var \mysqli $mysqli_db */

$pagetitle = '- All-Star Appearances';

PageLayout\PageLayout::header();

$appearancesRepository = new \AllStarAppearances\AllStarAppearancesRepository($mysqli_db);
$appearancesView = new \AllStarAppearances\AllStarAppearancesView();
echo $appearancesView->render($appearancesRepository->getAllStarAppearances());

PageLayout\PageLayout::footer();
