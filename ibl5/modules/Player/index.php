<?php

use Player\Player;
use Player\PlayerStats;

global $mysqli_db;

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Player Archives";

function showpage($playerID, $pageView)
{
    global $db, $mysqli_db, $cookie;
    $sharedFunctions = new Shared($mysqli_db);
    $commonRepository = new Services\CommonMysqliRepository($mysqli_db);
    $season = new Season($mysqli_db);
    
    // Player uses mysqli_db for PlayerRepository (refactored to use prepared statements)
    // Other classes still use legacy $db for backward compatibility
    $player = Player::withPlayerID($mysqli_db, $playerID);
    $playerStats = PlayerStats::withPlayerID($mysqli_db, $playerID);
    $pageView = ($pageView !== null) ? intval($pageView) : null;
    
    // Initialize service and view helper
    $pageService = new \Player\PlayerPageService($mysqli_db);
    $viewHelper = new \Player\PlayerPageViewHelper();

    // DISPLAY PAGE

    Nuke\Header::header();
    OpenTable();
    UI::playerMenu();

    // Render player header
    echo $viewHelper->renderPlayerHeader($player, $playerID);

    // Render action buttons based on business logic
    $userTeamName = $commonRepository->getTeamnameFromUsername(strval($cookie[1] ?? ''));
    $userTeam = Team::initialize($mysqli_db, $userTeamName);

    if ($pageService->shouldShowRookieOptionUsedMessage($player)) {
        echo $viewHelper->renderRookieOptionUsedMessage();
    } elseif ($pageService->canShowRenegotiationButton($player, $userTeam, $season)) {
        echo $viewHelper->renderRenegotiationButton($playerID);
    }

    if ($pageService->canShowRookieOptionButton($player, $userTeam, $season)) {
        echo $viewHelper->renderRookieOptionButton($playerID);
    }

    // Render player bio section
    $contract_display = implode("/", $player->getRemainingContractArray());
    echo $viewHelper->renderPlayerBioSection($player, $contract_display);

    // Get All-Star Activity data using PlayerRepository
    $playerRepository = new \Player\PlayerRepository($mysqli_db);
    $asg = $playerRepository->getAllStarGameCount($player->name);
    $threepointcontests = $playerRepository->getThreePointContestCount($player->name);
    $dunkcontests = $playerRepository->getDunkContestCount($player->name);
    $rooksoph = $playerRepository->getRookieSophChallengeCount($player->name);

    // Render player highs table with All-Star Activity data
    echo $viewHelper->renderPlayerHighsTable($playerStats, $asg, $threepointcontests, $dunkcontests, $rooksoph);
    
    // Close the outer row started in renderPlayerHeader
    echo "</tr>";

    // Render player menu
    echo $viewHelper->renderPlayerMenu($playerID);

    // Create view factory with all required dependencies
    $statsRepository = new \Player\PlayerStatsRepository($mysqli_db);
    $viewFactory = new \Player\Views\PlayerViewFactory($playerRepository, $statsRepository, $commonRepository);

    // Render the appropriate view using the factory
    $view = $viewFactory->createView($pageView);
    
    if ($pageView === PlayerPageType::OVERVIEW) {
        echo $view->renderOverview($playerID, $player, $playerStats, $season, $sharedFunctions);
    } elseif ($pageView === PlayerPageType::SIM_STATS) {
        echo $view->renderSimStats($playerID);
    } elseif ($pageView === PlayerPageType::REGULAR_SEASON_TOTALS) {
        echo $view->renderTotals($playerID);
    } elseif ($pageView === PlayerPageType::REGULAR_SEASON_AVERAGES) {
        echo $view->renderAverages($playerID);
    } elseif ($pageView === PlayerPageType::PLAYOFF_TOTALS) {
        echo $view->renderTotals($player->name);
    } elseif ($pageView === PlayerPageType::PLAYOFF_AVERAGES) {
        echo $view->renderAverages($player->name);
    } elseif ($pageView === PlayerPageType::HEAT_TOTALS) {
        echo $view->renderTotals($player->name);
    } elseif ($pageView === PlayerPageType::HEAT_AVERAGES) {
        echo $view->renderAverages($player->name);
    } elseif ($pageView === PlayerPageType::OLYMPIC_TOTALS) {
        echo $view->renderTotals($player->name);
    } elseif ($pageView === PlayerPageType::OLYMPIC_AVERAGES) {
        echo $view->renderAverages($player->name);
    } elseif ($pageView === PlayerPageType::RATINGS_AND_SALARY) {
        echo $view->renderRatingsAndSalary($playerID);
    } elseif ($pageView === PlayerPageType::AWARDS_AND_NEWS) {
        echo $view->renderAwardsAndNews($player->name);
    } elseif ($pageView === PlayerPageType::ONE_ON_ONE) {
        echo $view->renderOneOnOneResults($player->name);
    } else {
        // Default to overview
        echo $view->renderOverview($playerID, $player, $playerStats, $season, $sharedFunctions);
    }

    CloseTable();
    Nuke\Footer::footer();

    // END OF DISPLAY PAGE
}

function negotiate($playerID)
{
    global $prefix, $db, $mysqli_db, $cookie;

    $playerID = intval($playerID);
    
    // Get user's team name using existing CommonRepository
    $commonRepository = new Services\CommonMysqliRepository($mysqli_db);
    $userTeamName = $commonRepository->getTeamnameFromUsername(strval($cookie[1] ?? ''));

    Nuke\Header::header();
    OpenTable();
    UI::playerMenu();

    // Use NegotiationProcessor to handle all business logic
    $processor = new Negotiation\NegotiationProcessor($db, $mysqli_db);
    echo $processor->processNegotiation($playerID, $userTeamName, $prefix);

    CloseTable();
    Nuke\Footer::footer();
}

function rookieoption($pid)
{
    global $db, $cookie, $mysqli_db;
    
    // Initialize dependencies
    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    $season = new Season($mysqli_db);
    $validator = new \RookieOption\RookieOptionValidator();
    $formView = new \RookieOption\RookieOptionFormView();
    
    // Get user's team name
    $userTeamName = $commonRepository->getTeamnameFromUsername(strval($cookie[1] ?? ''));
    
    // Load player
    $player = Player::withPlayerID($db, $pid);
    
    // Validate player ownership
    $ownershipValidation = $validator->validatePlayerOwnership($player, $userTeamName);
    if (!$ownershipValidation['valid']) {
        $formView->renderError($ownershipValidation['error']);
        return;
    }
    
    // Validate eligibility and get final year salary
    $eligibilityValidation = $validator->validateEligibilityAndGetSalary($player, $season->phase);
    if (!$eligibilityValidation['valid']) {
        $formView->renderError($eligibilityValidation['error']);
        return;
    }
    
    // Calculate rookie option value (2x final year salary)
    $rookieOptionValue = 2 * $eligibilityValidation['finalYearSalary'];
    
    // Render form
    $formView->renderForm($player, $userTeamName, $rookieOptionValue);
}

switch ($pa) {

    case "negotiate":
        negotiate($pid);
        break;

    case "rookieoption":
        rookieoption($pid);
        break;

    case "showpage":
        showpage($pid, $pageView);
        break;
}
