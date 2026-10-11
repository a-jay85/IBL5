<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

global $authService;
/** @var \Auth\Contracts\AuthServiceInterface $authService */

// Module inputs read via the Http\HttpRequest value object (legacy globals are no
// longer auto-extracted). Only the JSON save_order route branches off $op; every other
// request renders the projected/final draft-order page.
$httpRequest = \Http\HttpRequest::fromGlobals();
$op = is_string($httpRequest->request('op')) ? $httpRequest->request('op') : '';

if ($op === 'save_order') {
    // Admin-only JSON POST endpoint. Replaces the former standalone
    // modules/ProjectedDraftOrder/save_order.php, now reached via
    // modules.php?name=ProjectedDraftOrder&op=save_order. Auth check, method
    // guard (405), JSON content-type, and validation branches are preserved
    // verbatim; each early `return` exits this included file before the page render.
    header('Content-Type: application/json');

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
        $factory = \Module\ModuleServices::current()->factory(\Module\Factories\ProjectedDraftOrderFactory::class);
        $season = $factory->season();
        $service = $factory->service();
        $service->saveLotteryOrder($season->endingYear, $intOrder);

        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save draft order']);
    }

    return;
}

$factory = \Module\ModuleServices::current()->factory(\Module\Factories\ProjectedDraftOrderFactory::class);
$season = $factory->season();
$repository = $factory->repository();
$service = $factory->service();
$view = $factory->view();

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
