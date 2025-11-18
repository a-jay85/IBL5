<?php

use Player\Player;

if (!mb_eregi("modules.php", $_SERVER['PHP_SELF'])) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Player Archives";

function showpage($playerID, $pageView)
{
    global $db, $cookie;
    $sharedFunctions = new Shared($db);
    $commonRepository = new Services\CommonRepository($db);
    $season = new Season($db);
    
    $player = Player::withPlayerID($db, $playerID);
    $playerStats = PlayerStats::withPlayerID($db, $playerID);
    $pageView = ($pageView !== null) ? intval($pageView) : null;
    
    // Initialize service and view helper
    $pageService = new \Player\PlayerPageService($db);
    $viewHelper = new \Player\PlayerPageViewHelper();

    // DISPLAY PAGE

    Nuke\Header::header();
    OpenTable();
    UI::playerMenu();

    // Render player header
    echo $viewHelper->renderPlayerHeader($player, $playerID);

    // Render action buttons based on business logic
    $userTeamName = $commonRepository->getTeamnameFromUsername($cookie[1]);
    $userTeam = Team::initialize($db, $userTeamName);

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

    // Query All-Star Activity data
    $escapedName = Services\DatabaseService::escapeString($db, $player->name);
    
    $allstarquery = $db->sql_query("SELECT * FROM ibl_awards WHERE name='" . $escapedName . "' AND Award LIKE '%Conference All-Star'");
    $asg = $db->sql_numrows($allstarquery);

    $allstarquery2 = $db->sql_query("SELECT * FROM ibl_awards WHERE name='" . $escapedName . "' AND Award LIKE 'Three-Point Contest%'");
    $threepointcontests = $db->sql_numrows($allstarquery2);

    $allstarquery3 = $db->sql_query("SELECT * FROM ibl_awards WHERE name='" . $escapedName . "' AND Award LIKE 'Slam Dunk Competition%'");
    $dunkcontests = $db->sql_numrows($allstarquery3);
    
    $allstarquery4 = $db->sql_query("SELECT * FROM ibl_awards WHERE name='" . $escapedName . "' AND Award LIKE 'Rookie-Sophomore Challenge'");
    $rooksoph = $db->sql_numrows($allstarquery4);

    // Render player highs table with All-Star Activity data
    echo $viewHelper->renderPlayerHighsTable($playerStats, $asg, $threepointcontests, $dunkcontests, $rooksoph);
    
    // Close the outer row started in renderPlayerHeader
    echo "</tr>";

    // Render player menu
    echo $viewHelper->renderPlayerMenu($playerID);

    if ($pageView == PlayerPageType::OVERVIEW) {
        require_once __DIR__ . '/views/OverviewView.php';
        $view = new OverviewView($db, $player, $playerStats, $season, $sharedFunctions);
        $view->render();
    } elseif ($pageView == PlayerPageType::SIM_STATS) {
        require_once __DIR__ . '/views/SimStatsView.php';
        $view = new SimStatsView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::REGULAR_SEASON_TOTALS) {
        require_once __DIR__ . '/views/RegularSeasonTotalsView.php';
        $view = new RegularSeasonTotalsView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::REGULAR_SEASON_AVERAGES) {
        require_once __DIR__ . '/views/RegularSeasonAveragesView.php';
        $view = new RegularSeasonAveragesView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::PLAYOFF_TOTALS) {
        require_once __DIR__ . '/views/PlayoffTotalsView.php';
        $view = new PlayoffTotalsView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::PLAYOFF_AVERAGES) {
        require_once __DIR__ . '/views/PlayoffAveragesView.php';
        $view = new PlayoffAveragesView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::HEAT_TOTALS) {
        require_once __DIR__ . '/views/HeatTotalsView.php';
        $view = new HeatTotalsView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::HEAT_AVERAGES) {
        require_once __DIR__ . '/views/HeatAveragesView.php';
        $view = new HeatAveragesView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::OLYMPIC_TOTALS) {
        require_once __DIR__ . '/views/OlympicTotalsView.php';
        $view = new OlympicTotalsView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::OLYMPIC_AVERAGES) {
        require_once __DIR__ . '/views/OlympicAveragesView.php';
        $view = new OlympicAveragesView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::RATINGS_AND_SALARY) {
        require_once __DIR__ . '/views/RatingsAndSalaryView.php';
        $view = new RatingsAndSalaryView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::AWARDS_AND_NEWS) {
        require_once __DIR__ . '/views/AwardsAndNewsView.php';
        $view = new AwardsAndNewsView($db, $player, $playerStats);
        $view->render();
    } elseif ($pageView == PlayerPageType::ONE_ON_ONE) {
        require_once __DIR__ . '/views/OneOnOneView.php';
        $view = new OneOnOneView($db, $player, $playerStats);
        $view->render();
    }

    echo "</table>";

    CloseTable();
    Nuke\Footer::footer();

    // END OF DISPLAY PAGE
}

function negotiate($playerID)
{
    global $prefix, $db, $cookie;

    $playerID = intval($playerID);
    
    // Get user's team name using existing CommonRepository
    $commonRepository = new Services\CommonRepository($db);
    $userTeamName = $commonRepository->getTeamnameFromUsername($cookie[1]);

    Nuke\Header::header();
    OpenTable();
    UI::playerMenu();

    // Use NegotiationProcessor to handle all business logic
    $processor = new Negotiation\NegotiationProcessor($db);
    echo $processor->processNegotiation($playerID, $userTeamName, $prefix);

    CloseTable();
    Nuke\Footer::footer();
}

function rookieoption($pid)
{
    global $db, $cookie;
    
    // Initialize dependencies
    $commonRepository = new \Services\CommonRepository($db);
    $season = new Season($db);
    $validator = new \RookieOption\RookieOptionValidator();
    $formView = new \RookieOption\RookieOptionFormView();
    
    // Get user's team name
    $userTeamName = $commonRepository->getTeamnameFromUsername($cookie[1]);
    
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
