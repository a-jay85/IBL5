<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use Auth\AuthService;
use ProjectedDraftOrder\ProjectedDraftOrderRepository;
use ProjectedDraftOrder\ProjectedDraftOrderService;

header('Content-Type: application/json');

global $mysqli_db;

$authService = new AuthService($mysqli_db);

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
foreach ($intOrder as $tid) {
    if ($tid < 1 || $tid > \League\League::MAX_REAL_TEAMID) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid team ID: ' . $tid]);
        return;
    }
}

try {
    $season = new Season($mysqli_db);
    $repository = new ProjectedDraftOrderRepository($mysqli_db);
    $service = new ProjectedDraftOrderService($repository);
    $service->saveLotteryOrder($season->endingYear, $intOrder);

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save draft order']);
}
