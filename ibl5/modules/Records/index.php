<?php

declare(strict_types=1);

/**
 * Records Module - tabbed page for All-Time, By Franchise, and This Season records.
 *
 * @see Records\RecordsController For the tab whitelist, tab bar, and dispatch
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use Records\RecordsController;

$services = \Module\ModuleServices::current();
$recordsFactory = $services->factory(\Module\Factories\RecordsFactory::class);
$recordHolders = $services->factory(\Module\Factories\RecordHoldersFactory::class);
$franchise = $services->factory(\Module\Factories\FranchiseRecordBookFactory::class);
$seasonHighs = $services->factory(\Module\Factories\SeasonHighsFactory::class);

$controller = $recordsFactory->controller([
    /** @param array<mixed> $query */
    RecordsController::TAB_ALLTIME => static function (array $query) use ($recordHolders): string {
        return $recordHolders->view()->render($recordHolders->service()->getAllRecords());
    },
    /** @param array<mixed> $query */
    RecordsController::TAB_BYFRANCHISE => static function (array $query) use ($franchise): string {
        $service = $franchise->service();
        $teamId = is_string($query['teamid'] ?? null) ? (int) $query['teamid'] : 0;
        $data = \League\League::isRealFranchise($teamId)
            ? $service->getTeamRecordBook($teamId)
            : $service->getLeagueRecordBook();
        return $franchise->view()->render($data);
    },
    /** @param array<mixed> $query */
    RecordsController::TAB_THISSEASON => static function (array $query) use ($seasonHighs): string {
        $season = $seasonHighs->season();
        $rawPhase = $query['seasonPhase'] ?? null;
        $seasonPhase = (is_string($rawPhase) && $rawPhase !== '' && $rawPhase !== '0') ? $rawPhase : $season->phase;
        $service = $seasonHighs->service();
        $homeAwayData = $service->getHomeAwayHighs($seasonPhase);
        $discrepancies = $service->validateAgainstRcb($homeAwayData, $season->beginningYear);
        $view = $seasonHighs->view();
        return $view->render($seasonPhase, $service->getSeasonHighsData($seasonPhase))
            . $view->renderHomeAwayHighs($seasonPhase, $homeAwayData, $discrepancies);
    },
]);

$rawTab = $_GET['tab'] ?? null;
$pagetitle = RecordsController::pageTitle(RecordsController::resolveTab($rawTab));
$body = $controller->render($rawTab, $_GET);

PageLayout\PageLayout::header();
echo $body;
PageLayout\PageLayout::footer();
