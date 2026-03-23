<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use Auth\AuthService;
use ProjectedDraftOrder\ProjectedDraftOrderRepository;
use ProjectedDraftOrder\ProjectedDraftOrderService;
use ProjectedDraftOrder\ProjectedDraftOrderView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

global $mysqli_db;

$season = new \Season\Season($mysqli_db);
$repository = new ProjectedDraftOrderRepository($mysqli_db);
$service = new ProjectedDraftOrderService($repository);
$view = new ProjectedDraftOrderView();

$authService = new AuthService($mysqli_db);
$isAdmin = $authService->isAdmin();
$isFinalized = $repository->isDraftOrderFinalized();
$isDraftStarted = $isFinalized && $repository->isDraftStarted($season->endingYear);

$pagetitle = $isFinalized ? '- Draft Order' : '- Projected Draft Order';

$draftOrder = $isFinalized
    ? $service->getFinalOrProjectedDraftOrder($season->endingYear)
    : $service->calculateDraftOrder($season->endingYear);

PageLayout\PageLayout::header();
echo $view->render($draftOrder, $season->endingYear, $isAdmin, $isFinalized, $isDraftStarted);
if ($isAdmin && !$isDraftStarted) {
    echo '<script src="jslib/draft-order-drag.js"></script>';
}
PageLayout\PageLayout::footer();
