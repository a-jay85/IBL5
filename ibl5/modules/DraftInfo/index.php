<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use Auth\AuthRepository;
use Auth\AuthService;
use DraftHistory\DraftHistoryRepository;
use DraftHistory\DraftHistoryView;
use DraftInfo\DraftInfoView;
use DraftPickLocator\DraftPickLocatorRepository;
use DraftPickLocator\DraftPickLocatorService;
use DraftPickLocator\DraftPickLocatorView;
use ProjectedDraftOrder\ProjectedDraftOrderRepository;
use ProjectedDraftOrder\ProjectedDraftOrderService;
use ProjectedDraftOrder\ProjectedDraftOrderView;

$module_name = basename(dirname(__FILE__));
global $mysqli_db;
/** @var \mysqli $mysqli_db */

// Admin-only JSON POST endpoint. Replaces the former standalone
// modules/ProjectedDraftOrder/save_order.php, now reached via
// modules.php?name=DraftInfo&op=save_order. Auth check, method
// guard (405), JSON content-type, and validation branches are preserved
// verbatim; each early `return` exits this included file before the page render.
$httpRequest = \Http\HttpRequest::fromGlobals();
$op = is_string($httpRequest->request('op')) ? $httpRequest->request('op') : '';

if ($op === 'save_order') {
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
        $orderRepository = new ProjectedDraftOrderRepository($mysqli_db);
        $orderService = new ProjectedDraftOrderService($orderRepository);
        $orderService->saveLotteryOrder($season->endingYear, $intOrder);

        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save draft order']);
    }

    return;
}

// Route HTMX API requests for the history tab (no PageLayout, returns HTML fragment only)
$getOp = is_string($_GET['op'] ?? null) ? $_GET['op'] : '';
if ($getOp === 'api') {
    $handler = new DraftHistory\DraftHistoryApiHandler($mysqli_db);
    $handler->handle();
    return;
}

// Tab resolution
$tabs = new \UI\Components\PageTabs(DraftInfoView::TABS, DraftInfoView::DEFAULT_TAB);
$tab = $tabs->resolve($_GET['tab'] ?? null);

$trailingHtml = '';

if ($tab === DraftInfoView::TAB_PICKS) {
    $season = new \Season\Season($mysqli_db);
    $picksRepository = new DraftPickLocatorRepository($mysqli_db);
    $picksService = new DraftPickLocatorService($picksRepository);
    $picksView = new DraftPickLocatorView();
    $teamsWithPicks = $picksService->getAllTeamsWithPicks();
    $tabBodyHtml = $picksView->render($teamsWithPicks, $season->endingYear);
    $pagetitle = '- Draft Pick Locator';
} elseif ($tab === DraftInfoView::TAB_HISTORY) {
    $historyRepository = new DraftHistoryRepository($mysqli_db);
    $historyView = new DraftHistoryView();

    $teamid = isset($_GET['teamid']) ? (int) $_GET['teamid'] : 0;

    $team = null;
    if ($teamid > 0) {
        $candidate = \Team\Team::initialize($mysqli_db, $teamid);
        if ($candidate->teamid > 0) {
            $team = $candidate;
        }
    }

    if ($team instanceof \Team\Team) {
        $tabBodyHtml = $historyView->renderTeamHistory($team, $historyRepository->getDraftPicksByTeam($team->name));
        $pagetitle = "- {$team->name} Draft History";
    } else {
        $startYear = $historyRepository->getFirstDraftYear();
        $endYear = $historyRepository->getLastDraftYear();
        $year = isset($_REQUEST['year']) ? (int) $_REQUEST['year'] : $endYear;
        $tabBodyHtml = $historyView->render($year, $startYear, $endYear, $historyRepository->getDraftPicksByYear($year));
        $pagetitle = "- $year Draft";
    }
} else {
    // order tab (default)
    $season = new \Season\Season($mysqli_db);
    $orderRepository = new ProjectedDraftOrderRepository($mysqli_db);
    $orderService = new ProjectedDraftOrderService($orderRepository);
    $orderView = new ProjectedDraftOrderView();

    $authService = new AuthService(new AuthRepository($mysqli_db));
    $isAdmin = $authService->isAdmin();
    $isFinalized = $orderRepository->isDraftOrderFinalized();
    $isDraftStarted = $isFinalized && $orderRepository->isDraftStarted($season->endingYear);

    $draftOrder = $isFinalized
        ? $orderService->getFinalOrProjectedDraftOrder($season->endingYear)
        : $orderService->calculateDraftOrder($season->endingYear);

    $tabBodyHtml = $orderView->render($draftOrder, $season->endingYear, $isAdmin, $isFinalized, $isDraftStarted);

    if ($isAdmin && !$isDraftStarted) {
        $trailingHtml = '<script src="jslib/draft-order-drag.js"></script>';
    }

    $pagetitle = $isFinalized ? '- Draft Order' : '- Projected Draft Order';
}

$draftInfoView = new DraftInfoView();
PageLayout\PageLayout::header();
echo $draftInfoView->render($tab, $tabBodyHtml);
echo $trailingHtml;
PageLayout\PageLayout::footer();
