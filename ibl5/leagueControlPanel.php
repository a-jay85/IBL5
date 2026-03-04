<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

// Auth guard
if (!is_user($user)) {
    header('Location: modules.php?name=Your_Account');
    exit;
}

if (!is_admin()) {
    http_response_code(403);
    echo 'Access denied. Administrator privileges required.';
    exit;
}

// Wire dependencies
$repository = new LeagueControlPanel\LeagueControlPanelRepository($mysqli_db);
$service    = new LeagueControlPanel\LeagueControlPanelService($repository);
$processor  = new LeagueControlPanel\LeagueControlPanelProcessor($repository);
$view       = new LeagueControlPanel\LeagueControlPanelView();

// POST → Processor → PRG redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $result = $processor->dispatch($action, $_POST);

    $queryParam = $result['success'] ? 'success' : 'error';
    header('Location: leagueControlPanel.php?' . $queryParam . '=' . rawurlencode($result['message']));
    exit;
}

// GET → Service + View → render
$leagueConfig  = $leagueContext->getConfig();
$currentLeague = $leagueContext->getCurrentLeague();
$panelData     = $service->getPanelData();

// Flash message from PRG redirect
$resultMessage = null;
$resultSuccess = false;
if (is_string($_GET['success'] ?? null) && $_GET['success'] !== '') {
    $resultMessage = $_GET['success'];
    $resultSuccess = true;
} elseif (is_string($_GET['error'] ?? null) && $_GET['error'] !== '') {
    $resultMessage = $_GET['error'];
    $resultSuccess = false;
}

echo $view->render($leagueConfig, $currentLeague, $panelData, $resultMessage, $resultSuccess);
