<?php

declare(strict_types=1);

/**
 * Next_Sim Module - Display upcoming simulation games
 *
 * Shows the user's upcoming games with matchup information.
 *
 * Refactored to use the interface-driven architecture pattern.
 *
 * @see NextSim\NextSimService For business logic
 * @see NextSim\NextSimView For HTML rendering
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

global $db, $user, $authService;

if (!is_user($user)) {
    loginbox();
} else {
    $factory = \Module\ModuleServices::current()->factory(\Module\Factories\NextSimFactory::class);
    $commonRepository = $factory->teamIdentity();
    $season = $factory->season();

    $module_name = basename(dirname(__FILE__));
    $pagetitle = "- $module_name";

    // Load power rankings for SOS tier indicators
    $standingsRepo = $factory->standingsRepository();
    $allStreakData = $standingsRepo->getAllStreakData();
    /** @var array<int, float> $teamPowerRankings */
    $teamPowerRankings = [];
    foreach ($allStreakData as $teamid => $data) {
        $teamPowerRankings[$teamid] = (float)$data['ranking'];
    }

    PageLayout\PageLayout::header();

    $username = $authService->getUsername() ?? '';
    $userTeamName = $commonRepository->getTeamnameFromUsername($username) ?? '';
    $userTeam = \Team\Team::initialize(\Module\ModuleServices::current()->db(), $userTeamName);

    // Initialize services
    $service = $factory->service($teamPowerRankings);
    $view = $factory->view();

    // Get next sim games
    $games = $service->getNextSimGames($userTeam->teamid, $season);

    $userStarters = $service->getUserStartingLineup($userTeam);

    echo $view->render($games, $userTeam, $userStarters);

    PageLayout\PageLayout::footer();
}
