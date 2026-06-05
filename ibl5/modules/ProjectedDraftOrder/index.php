<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use Auth\AuthRepository;
use Auth\AuthService;
use ProjectedDraftOrder\ProjectedDraftOrderRepository;
use ProjectedDraftOrder\ProjectedDraftOrderService;
use ProjectedDraftOrder\ProjectedDraftOrderView;

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

global $mysqli_db;

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// Module inputs are read from $_REQUEST explicitly here.
$op = is_string($_REQUEST['op'] ?? null) ? $_REQUEST['op'] : '';

/**
 * Persist a reordered draft lottery — admin-only JSON POST endpoint.
 *
 * Replaces the former standalone modules/ProjectedDraftOrder/save_order.php,
 * routed via modules.php?name=ProjectedDraftOrder&op=save_order. Auth check,
 * method guard (405), and JSON content-type are preserved verbatim.
 */
function saveOrder(): void
{
    global $mysqli_db;

    header('Content-Type: application/json');

    $authService = new AuthService(new AuthRepository($mysqli_db));

    if (!$authService->isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || !isset($input['order']) || !is_array($input['order'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid request body']);
        return;
    }

    $order = $input['order'];

    if (count($order) !== 12) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Exactly 12 team IDs required']);
        return;
    }

    $intOrder = [];
    foreach ($order as $item) {
        if (!is_int($item) && !is_string($item)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid team ID']);
            return;
        }
        $intOrder[] = (int) $item;
    }

    if (count(array_unique($intOrder)) !== 12) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Duplicate team IDs not allowed']);
        return;
    }

    // Validate all team IDs are within valid range
    foreach ($intOrder as $teamid) {
        if ($teamid < 1 || $teamid > \League\League::MAX_REAL_TEAMID) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid team ID: ' . $teamid]);
            return;
        }
    }

    try {
        $season = new \Season\Season($mysqli_db);
        $repository = new ProjectedDraftOrderRepository($mysqli_db);
        $service = new ProjectedDraftOrderService($repository);
        $service->saveLotteryOrder($season->endingYear, $intOrder);

        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save draft order']);
    }
}

/**
 * Render the projected/final draft-order page (default route).
 */
function renderDraftOrderPage(): void
{
    global $mysqli_db, $pagetitle;

    $season = new \Season\Season($mysqli_db);
    $repository = new ProjectedDraftOrderRepository($mysqli_db);
    $service = new ProjectedDraftOrderService($repository);
    $view = new ProjectedDraftOrderView();

    $authService = new AuthService(new AuthRepository($mysqli_db));
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
}

switch ($op) {
    case 'save_order':
        saveOrder();
        break;
    default:
        renderDraftOrderPage();
        break;
}
