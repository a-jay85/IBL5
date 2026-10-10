<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

global $user;

if (!is_user($user)) {
    loginbox();
}

$overrideYear = null;
if (isset($_GET['year']) && is_string($_GET['year']) && ctype_digit($_GET['year'])) {
    $overrideYear = (int) $_GET['year'];
}
$filterTid = null;
if (isset($_GET['tid']) && is_string($_GET['tid']) && ctype_digit($_GET['tid'])) {
    $filterTid = (int) $_GET['tid'];
}
$filterStatus = '';
if (isset($_GET['status']) && is_string($_GET['status']) && in_array($_GET['status'], ['signed', 'fa'], true)) {
    $filterStatus = $_GET['status'];
}

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\TrainingCampRatingsDiffFactory::class);
$service = $factory->service();
$view    = $factory->view();

$baselineYear  = $service->getBaselineYear($overrideYear);
$baselinePhase = $service->getBaselinePhase($overrideYear);
$rows = $service->getDiffs($overrideYear, $filterTid, $filterStatus);

PageLayout\PageLayout::header();
echo $view->render($baselineYear, $baselinePhase, $rows, $filterStatus);
PageLayout\PageLayout::footer();
