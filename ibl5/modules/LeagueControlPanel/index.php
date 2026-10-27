<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

global $leagueContext, $authService;
/** @var \League\LeagueContext $leagueContext */
/** @var \Auth\Contracts\AuthServiceInterface $authService */

// Auth guard. Order is load-bearing: authenticated, then admin, and only
// then any CsrfGuard::generateRawToken() call (pinned by
// tests/Security/LeagueControlPanelExportTokenOrderTest.php). The legacy
// is_user()/is_admin() helpers read these same AuthService methods.
if (!$authService->isAuthenticated()) {
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    if (is_string($queryString) && $queryString !== '') {
        $_SESSION['redirect_after_login'] = $queryString;
    }
    \Utilities\HtmxHelper::redirect('modules.php?name=YourAccount');
}

if (!$authService->isAdmin()) {
    http_response_code(403);
    echo 'Access denied. Administrator privileges required.';
    exit;
}

// Wire dependencies
$factory = \Module\ModuleServices::current()->factory(\Module\Factories\LeagueControlPanelFactory::class);
$currentLeague = $leagueContext->getCurrentLeague();
$repository = $factory->repository();
$service    = $factory->service();
$processor  = $factory->processor();
$view       = $factory->view();
$csvExporter = $factory->csvExporter();

// POST export=active_players → write CSV to temp dir, reply with its download URL (JSON).
// Writes a file, so it is POST + CSRF like every other LCP action; the reply carries a
// fresh token on every JSON reply (success, 500, and CSRF 403) because tokens are
// single-use and the button can be clicked again without a page reload.
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
        echo json_encode([
            'error' => 'Invalid or expired form submission. Please try again.',
            'csrfToken' => \Security\CsrfGuard::generateRawToken('lcp_export_active_players'),
        ]);
        exit;
    }

    try {
        $filename = $csvExporter->export($factory->exportTimestamp());
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
        'url' => 'modules.php?name=LeagueControlPanel&download=' . rawurlencode($filename),
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
        \Utilities\HtmxHelper::redirect('modules.php?name=LeagueControlPanel&error=' . rawurlencode('Invalid or expired form submission. Please reload and try again.'));
    }

    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $postData = [];
    foreach ($_POST as $key => $value) {
        $postData[(string) $key] = $value;
    }
    $result = $processor->dispatch($action, $postData);

    $queryParam = $result['success'] ? 'success' : 'error';
    \Utilities\HtmxHelper::redirect('modules.php?name=LeagueControlPanel&' . $queryParam . '=' . rawurlencode($result['message']));
}

// GET → Service + View → render
$rawLeagueConfig = $leagueContext->getConfig();
$leagueConfig  = [
    'short_name' => $rawLeagueConfig['short_name'] ?? '',
    'full_name'  => $rawLeagueConfig['full_name'] ?? '',
];
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
