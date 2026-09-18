<?php

declare(strict_types=1);

require __DIR__ . '/mainfile.php';

// Auth guard
if (!is_user($user)) {
    $_SESSION['redirect_after_login_path'] = 'leagueControlPanel.php';
    \Utilities\HtmxHelper::redirect('modules.php?name=YourAccount');
}

if (!is_admin()) {
    http_response_code(403);
    echo 'Access denied. Administrator privileges required.';
    exit;
}

// Wire dependencies
$currentLeague = $leagueContext->getCurrentLeague();
$repository = new LeagueControlPanel\LeagueControlPanelRepository($mysqli_db, $leagueContext);
$service    = new LeagueControlPanel\LeagueControlPanelService($repository, $currentLeague);
$votingRepository     = new Voting\VotingRepository($mysqli_db);
$votingResultsService = new Voting\VotingResultsService($votingRepository);
$awardGenerationService = new LeagueControlPanel\AwardGenerationService($repository, $votingResultsService);
$maintenanceRepository = new Maintenance\MaintenanceRepository($mysqli_db);
$processor  = new LeagueControlPanel\LeagueControlPanelProcessor($repository, $awardGenerationService, $currentLeague, $maintenanceRepository);
$view       = new LeagueControlPanel\LeagueControlPanelView();
$csvExporter = new LeagueControlPanel\ActivePlayersCsvExporter($repository);

// POST export=active_players → write CSV to temp dir, reply with its download URL (JSON).
// Writes a file, so it is POST + CSRF like every other LCP action; the reply carries a
// fresh token because tokens are single-use and the button can be clicked again.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['export'] ?? null) === 'active_players') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    if ($currentLeague !== 'ibl' || !in_array($repository->getSetting('Current Season Phase'), ['Preseason', 'Free Agency'], true)) {
        http_response_code(409);
        echo json_encode(['error' => 'This export is only available during Preseason and Free Agency.']);
        exit;
    }

    if (!\Security\CsrfGuard::validateSubmittedToken('lcp_export_active_players')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid or expired form submission. Please reload and try again.']);
        exit;
    }

    try {
        $filename = $csvExporter->export(new \DateTimeImmutable());
    } catch (\RuntimeException $e) {
        \Logging\LoggerFactory::getChannel('admin')->error('active_players_csv_export_failed', ['error' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode([
            'error' => 'Export failed. Please try again.',
            'csrfToken' => \Security\CsrfGuard::generateRawToken('lcp_export_active_players'),
        ]);
        exit;
    }

    echo json_encode([
        'filename' => $filename,
        'url' => 'leagueControlPanel.php?download=' . rawurlencode($filename),
        'csrfToken' => \Security\CsrfGuard::generateRawToken('lcp_export_active_players'),
    ]);
    exit;
}

// GET ?download=<filename> → stream a previous export from the temp dir
if (is_string($_GET['download'] ?? null)) {
    $path = $csvExporter->resolvePath($_GET['download']);
    if ($path === null) {
        http_response_code(404);
        echo 'Export not found. Please generate a new one.';
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

// POST → Processor → PRG redirect
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\Security\CsrfGuard::validateSubmittedToken('lcp_update_all')) {
        \Utilities\HtmxHelper::redirect('leagueControlPanel.php?error=' . rawurlencode('Invalid or expired form submission. Please reload and try again.'));
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $result = $processor->dispatch($action, $_POST);

    $queryParam = $result['success'] ? 'success' : 'error';
    \Utilities\HtmxHelper::redirect('leagueControlPanel.php?' . $queryParam . '=' . rawurlencode($result['message']));
}

// GET → Service + View → render
$leagueConfig  = $leagueContext->getConfig();
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
