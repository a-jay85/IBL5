<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use DraftOrder\DraftOrderRepository;
use DraftOrder\DraftOrderService;
use DraftOrder\DraftOrderView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

global $mysqli_db;

$season = new Season($mysqli_db);
$pagetitle = "- Projected Draft Order";

$repository = new DraftOrderRepository($mysqli_db);
$service = new DraftOrderService($repository);
$view = new DraftOrderView();

$draftOrder = $service->calculateDraftOrder($season->endingYear);

PageLayout\PageLayout::header();
echo $view->render($draftOrder, $season->endingYear);
PageLayout\PageLayout::footer();
