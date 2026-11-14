<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use DraftInfo\DraftInfoView;
use DraftInfo\HistorySubTabState;

$module_name = basename(dirname(__FILE__));
global $authService;
/** @var \Auth\Contracts\AuthServiceInterface $authService */

// Admin-only JSON POST endpoint. Replaces the former standalone
// modules/ProjectedDraftOrder/save_order.php, now reached via
// modules.php?name=DraftInfo&op=save_order. Auth check, method
// guard (405), JSON content-type, and validation branches are preserved
// verbatim; each early `return` exits this included file before the page render.
$httpRequest = \Http\HttpRequest::fromGlobals();
$op = is_string($httpRequest->request('op')) ? $httpRequest->request('op') : '';

if ($op === 'save_order') {
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

    $rawBody = file_get_contents('php://input');
    $input = is_string($rawBody) ? json_decode($rawBody, true) : null;
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
        $orderFactory = \Module\ModuleServices::current()->factory(\Module\Factories\ProjectedDraftOrderFactory::class);
        $season = $orderFactory->season();
        $orderService = $orderFactory->service();
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
    $handler = \Module\ModuleServices::current()->factory(\Module\Factories\DraftHistoryFactory::class)->apiHandler();
    $handler->handle();
    return;
}

// Tab resolution
$infoFactory = \Module\ModuleServices::current()->factory(\Module\Factories\DraftInfoFactory::class);
$tabs = $infoFactory->tabs();
$tab = $tabs->resolve($_GET['tab'] ?? null);

$trailingHtml = '';
$draftInfoView = $infoFactory->view();

if ($tab === DraftInfoView::TAB_PICKS) {
    $picksFactory = \Module\ModuleServices::current()->factory(\Module\Factories\DraftPickLocatorFactory::class);
    $season = $picksFactory->season();
    $picksService = $picksFactory->service();
    $picksView = $picksFactory->view();
    $teamsWithPicks = $picksService->getAllTeamsWithPicks();
    $tabBodyHtml = $picksView->render($teamsWithPicks, $season->endingYear);
    $pagetitle = '- Draft Pick Locator';
} elseif ($tab === DraftInfoView::TAB_HISTORY) {
    $historyFactory = \Module\ModuleServices::current()->factory(\Module\Factories\DraftHistoryFactory::class);
    $historyRepository = $historyFactory->repository();
    $historyView = $historyFactory->view();

    $teamid = is_string($_GET['teamid'] ?? null) ? (int) $_GET['teamid'] : 0;

    $team = null;
    if ($teamid > 0) {
        $candidate = $historyFactory->team($teamid);
        if ($candidate->teamid > 0) {
            $team = $candidate;
        }
    }

    $view = is_string($_GET['view'] ?? null) ? $_GET['view'] : '';
    $identityRepository = $infoFactory->teamIdentity();
    $viewerTeamId = HistorySubTabState::resolveViewerTeamId(
        $authService->getUsername(),
        $identityRepository->getTeamnameFromUsername(...),
        $identityRepository->getTidFromTeamname(...),
    );
    $state = HistorySubTabState::resolve($team?->teamid, $team?->name, $view, $viewerTeamId);

    if ($state->showLoginPrompt) {
        $panelHtml = $draftInfoView->renderTeamLoginPrompt();
        $pagetitle = '- My Team Draft History';
    } elseif ($state->teamIdToRender !== null) {
        $renderTeam = ($team instanceof \Team\Team && $team->teamid === $state->teamIdToRender)
            ? $team
            : $historyFactory->team($state->teamIdToRender);
        $panelHtml = $historyView->renderTeamHistory($renderTeam, $historyRepository->getDraftPicksByTeam($renderTeam->name));
        $pagetitle = "- {$renderTeam->name} Draft History";
    } else {
        $startYear = $historyRepository->getFirstDraftYear();
        $endYear = $historyRepository->getLastDraftYear();
        $year = is_string($_REQUEST['year'] ?? null) ? (int) $_REQUEST['year'] : $endYear;
        $panelHtml = $historyView->render($year, $startYear, $endYear, $historyRepository->getDraftPicksByYear($year));
        $pagetitle = "- $year Draft";
    }
    $tabBodyHtml = $draftInfoView->renderHistorySubTabs($state->activeSubTab, $state->teamTabLabel, $state->otherTeamId, $panelHtml);
} else {
    // order tab (default)
    $orderFactory = \Module\ModuleServices::current()->factory(\Module\Factories\ProjectedDraftOrderFactory::class);
    $season = $orderFactory->season();
    $orderRepository = $orderFactory->repository();
    $orderService = $orderFactory->service();
    $orderView = $orderFactory->view();

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

PageLayout\PageLayout::header();
echo $draftInfoView->render($tab, $tabBodyHtml);
echo $trailingHtml;
PageLayout\PageLayout::footer();
