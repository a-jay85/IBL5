<?php

use Player\Player;

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
    $sharedFunctions = new Shared($db);
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

    
    $stmt = $mysqli_db->prepare("SELECT * FROM ibl_awards WHERE name = ? AND Award LIKE '%Conference All-Star'");
    $stmt->bind_param('s', $player->name);
    $stmt->execute();
    $allstarquery = $stmt->get_result();
    $asg = $allstarquery->num_rows;

    $stmt2 = $mysqli_db->prepare("SELECT * FROM ibl_awards WHERE name = ? AND Award LIKE 'Three-Point Contest%'");
    $stmt2->bind_param('s', $player->name);
    $stmt2->execute();
    $allstarquery2 = $stmt2->get_result();
    $threepointcontests = $allstarquery2->num_rows;

    $stmt3 = $mysqli_db->prepare("SELECT * FROM ibl_awards WHERE name = ? AND Award LIKE 'Slam Dunk Competition%'");
    $stmt3->bind_param('s', $player->name);
    $stmt3->execute();
    $allstarquery3 = $stmt3->get_result();
    $dunkcontests = $allstarquery3->num_rows;
    
    $stmt4 = $mysqli_db->prepare("SELECT * FROM ibl_awards WHERE name = ? AND Award LIKE 'Rookie-Sophomore Challenge'");
    $stmt4->bind_param('s', $player->name);
    $stmt4->execute();
    $allstarquery4 = $stmt4->get_result();
    $rooksoph = $allstarquery4->num_rows;

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
