<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

if (!is_user($user)) {
    $_SESSION['redirect_after_login_path'] = 'ratingsDiff.php';
    \Utilities\HtmxHelper::redirect('modules.php?name=YourAccount');
}

if (!is_admin()) {
    http_response_code(403);
    echo 'Access denied. Administrator privileges required.';
    exit;
}

$repository = new RatingsDiff\RatingsDiffRepository($mysqli_db);
$service    = new RatingsDiff\RatingsDiffService($repository);
$view       = new RatingsDiff\RatingsDiffView();

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

$baselineYear = $service->getBaselineYear($overrideYear);
$rows = $service->getDiffs($overrideYear, $filterTid, $filterStatus);

\PageLayout\PageLayout::header();
echo $view->render($baselineYear, $rows, $filterStatus);
\PageLayout\PageLayout::footer();
