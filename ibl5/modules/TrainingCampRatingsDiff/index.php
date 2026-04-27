<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use TrainingCampRatingsDiff\TrainingCampRatingsDiffRepository;
use TrainingCampRatingsDiff\TrainingCampRatingsDiffService;
use TrainingCampRatingsDiff\TrainingCampRatingsDiffView;

global $mysqli_db, $user;

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

$repository = new TrainingCampRatingsDiffRepository($mysqli_db);
$service    = new TrainingCampRatingsDiffService($repository);
$view       = new TrainingCampRatingsDiffView();

$baselineYear = $service->getBaselineYear($overrideYear);
$rows = $service->getDiffs($overrideYear, $filterTid, $filterStatus);

PageLayout\PageLayout::header();
echo $view->render($baselineYear, $rows, $filterStatus);
PageLayout\PageLayout::footer();
