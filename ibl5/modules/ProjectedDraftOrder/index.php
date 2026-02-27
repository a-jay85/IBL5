<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use ProjectedDraftOrder\ProjectedDraftOrderRepository;
use ProjectedDraftOrder\ProjectedDraftOrderService;
use ProjectedDraftOrder\ProjectedDraftOrderView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

global $mysqli_db;

$season = new Season($mysqli_db);
$pagetitle = "- Projected Draft Order";

$repository = new ProjectedDraftOrderRepository($mysqli_db);
$service = new ProjectedDraftOrderService($repository);
$view = new ProjectedDraftOrderView();

$draftOrder = $service->calculateDraftOrder($season->endingYear);

PageLayout\PageLayout::header();
echo $view->render($draftOrder, $season->endingYear);
PageLayout\PageLayout::footer();
