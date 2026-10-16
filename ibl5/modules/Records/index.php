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

use Cache\DatabaseCache;
use FranchiseRecordBook\FranchiseRecordBookRepository;
use FranchiseRecordBook\FranchiseRecordBookService;
use FranchiseRecordBook\FranchiseRecordBookView;
use RecordHolders\CachedRecordHoldersService;
use RecordHolders\RecordHoldersRepository;
use RecordHolders\RecordHoldersService;
use RecordHolders\RecordHoldersView;
use Records\RecordsController;
use SeasonHighs\CachedSeasonHighsRepository;
use SeasonHighs\SeasonHighsRepository;
use SeasonHighs\SeasonHighsService;
use SeasonHighs\SeasonHighsView;

global $mysqli_db, $leagueContext;
/** @var \mysqli $mysqli_db */
/** @var \League\LeagueContext|null $leagueContext */


$controller = new RecordsController([
    /** @param array<mixed> $query */
    RecordsController::TAB_ALLTIME => static function (array $query) use ($mysqli_db, $leagueContext): string {
        $service = new CachedRecordHoldersService(
            new RecordHoldersService(new RecordHoldersRepository($mysqli_db, $leagueContext)),
            new DatabaseCache($mysqli_db)
        );
        return (new RecordHoldersView())->render($service->getAllRecords());
    },
    /** @param array<mixed> $query */
    RecordsController::TAB_BYFRANCHISE => static function (array $query) use ($mysqli_db): string {
        $service = new FranchiseRecordBookService(new FranchiseRecordBookRepository($mysqli_db));
        $teamId = is_string($query['teamid'] ?? null) ? (int) $query['teamid'] : 0;
        $data = \League\League::isRealFranchise($teamId)
            ? $service->getTeamRecordBook($teamId)
            : $service->getLeagueRecordBook();
        return (new FranchiseRecordBookView())->render($data);
    },
    /** @param array<mixed> $query */
    RecordsController::TAB_THISSEASON => static function (array $query) use ($mysqli_db, $leagueContext): string {
        $season = new \Season\Season($mysqli_db, $leagueContext);
        $rawPhase = $query['seasonPhase'] ?? null;
        $seasonPhase = (is_string($rawPhase) && $rawPhase !== '') ? $rawPhase : $season->phase;
        $repository = new CachedSeasonHighsRepository(
            new SeasonHighsRepository($mysqli_db, $leagueContext),
            new DatabaseCache($mysqli_db),
            $leagueContext instanceof \League\LeagueContext
                ? $leagueContext->getCurrentLeague()
                : \League\LeagueContext::LEAGUE_IBL
        );
        $service = new SeasonHighsService($repository, $season);
        $homeAwayData = $service->getHomeAwayHighs($seasonPhase);
        $discrepancies = $service->validateAgainstRcb($homeAwayData, $season->beginningYear);
        $view = new SeasonHighsView();
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
