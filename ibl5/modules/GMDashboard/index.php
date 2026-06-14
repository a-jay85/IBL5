<?php

declare(strict_types=1);

/**
 * GMDashboard Module - Owner-scoped landing page aggregating six read-only data sources.
 *
 * Read-only. No write surface, no new table, no schema change. Every team-scoped
 * section is filtered server-side to the session owner's teamid; the owner identity
 * is resolved from $user and NEVER from a ?teamid= request parameter.
 *
 * @see Dashboard\DashboardService For aggregation
 * @see Dashboard\DashboardView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

use CapSpace\CapSpaceRepository;
use CapSpace\CapSpaceService;
use Dashboard\DashboardService;
use Dashboard\DashboardView;
use FreeAgencyPreview\FreeAgencyPreviewRepository;
use FreeAgencyPreview\FreeAgencyPreviewService;
use Injuries\InjuriesService;
use League\League;
use NextSim\NextSimService;
use Standings\StandingsRepository;
use Topics\TopicsService;
use TeamSchedule\TeamScheduleRepository;
use Trading\TradeAssetRepository;
use Trading\TradeFormRepository;
use Trading\TradeOfferRepository;
use Trading\TradingService;

global $db, $cookie, $user, $mysqli_db;

if (!is_user($user)) {
    loginbox();
} else {
    $commonRepository = new Repositories\TeamIdentityRepository($mysqli_db);
    $season = new \Season\Season($mysqli_db);

    // Load power rankings for the Next Sim SOS tier indicator.
    $standingsRepo = new StandingsRepository($mysqli_db);
    $allStreakData = $standingsRepo->getAllStreakData();
    /** @var array<int, float> $teamPowerRankings */
    $teamPowerRankings = [];
    foreach ($allStreakData as $teamid => $data) {
        $teamPowerRankings[$teamid] = (float) $data['ranking'];
    }

    // Render header first (populates $cookie via cookiedecode()).
    PageLayout\PageLayout::header();

    // Owner identity is resolved server-side from the session — never a request param.
    $username = strval($cookie[1] ?? '');
    $ownerTeamName = $commonRepository->getTeamnameFromUsername($username) ?? '';
    $ownerTeamId = $commonRepository->getTidFromTeamname($ownerTeamName) ?? League::FREE_AGENTS_TEAMID;

    if ($ownerTeamName === '' || $ownerTeamName === League::FREE_AGENTS_TEAM_NAME || $ownerTeamId === League::FREE_AGENTS_TEAMID) {
        echo '<h1 class="ibl-title">My Dashboard</h1>';
        echo '<p>Your account is not associated with a team, so there is no dashboard to display.</p>';
        PageLayout\PageLayout::footer();

        return;
    }

    // Wire the six source services (mirrors each module's own entry point).
    $serverName = strval($_SERVER['SERVER_NAME'] ?? '');
    $tradingService = new TradingService(
        new TradeOfferRepository($mysqli_db, $serverName),
        new TradeAssetRepository($mysqli_db),
        new TradeFormRepository($mysqli_db),
        $commonRepository,
        $mysqli_db
    );
    $nextSimService = new NextSimService($mysqli_db, new TeamScheduleRepository($mysqli_db), $teamPowerRankings);
    $capSpaceService = new CapSpaceService(new CapSpaceRepository($mysqli_db), $mysqli_db);
    $freeAgencyPreviewService = new FreeAgencyPreviewService(new FreeAgencyPreviewRepository($mysqli_db));
    $injuriesService = new InjuriesService($mysqli_db);
    $topicsService = new TopicsService($mysqli_db);

    $dashboardService = new DashboardService(
        $tradingService,
        $nextSimService,
        $capSpaceService,
        $freeAgencyPreviewService,
        $injuriesService,
        $topicsService
    );
    $view = new DashboardView();

    echo $view->render($dashboardService->getDashboardData($ownerTeamId, $ownerTeamName, $username, $season));

    PageLayout\PageLayout::footer();
}
