<?php

use Player\Player;
use Player\PlayerStats;
use Player\PlayerPageService;
use Player\PlayerRepository;
use Player\PlayerStatsRepository;
use Player\Views\PlayerViewStyles;
use Player\Views\PlayerHeaderView;
use Player\Views\PlayerButtonsView;
use Player\Views\PlayerBioView;
use Player\Views\PlayerStatsView;
use Player\Views\PlayerMenuView;
use Player\Views\PlayerViewFactory;
use Player\Views\PlayerRatingsView;
use Player\Views\PlayerTradingCardView;
use Player\Views\PlayerTradingCardBackView;
use RookieOption\RookieOptionValidator;
use RookieOption\RookieOptionFormView;
use Services\CommonMysqliRepository;
use Negotiation\NegotiationProcessor;

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
    
    // Initialize service
    $pageService = new PlayerPageService($mysqli_db);

    // DISPLAY PAGE

    Nuke\Header::header();
    OpenTable();
    
    // Include trading card styles (Tailwind CDN + custom CSS)
    echo PlayerTradingCardView::getStyles();
    
    // Include legacy player view styles for other components
    echo PlayerViewStyles::getStyles();
    
    // Render player menu with current page selected
    echo PlayerMenuView::render($playerID, $pageView);

    // Render player as trading card (combines header, bio, ratings)
    $contract_display = implode("/", $player->getRemainingContractArray());
    echo '<tr><td colspan="2">';
    echo PlayerTradingCardView::render($player, $playerID, $contract_display);
    echo '</td></tr>';

    // Render action buttons based on business logic
    $userTeamName = $commonRepository->getTeamnameFromUsername(strval($cookie[1] ?? ''));
    $userTeam = Team::initialize($mysqli_db, $userTeamName);

    if ($pageService->shouldShowRookieOptionUsedMessage($player)) {
        echo PlayerButtonsView::renderRookieOptionUsedMessage();
    } elseif ($pageService->canShowRenegotiationButton($player, $userTeam, $season)) {
        echo PlayerButtonsView::renderRenegotiationButton($playerID);
    }

    if ($pageService->canShowRookieOptionButton($player, $userTeam, $season)) {
        echo PlayerButtonsView::renderRookieOptionButton($playerID);
    }
    
    // Get All-Star Activity data using PlayerRepository
    $playerRepository = new PlayerRepository($mysqli_db);
    $asg = $playerRepository->getAllStarGameCount($player->name);
    $threepointcontests = $playerRepository->getThreePointContestCount($player->name);
    $dunkcontests = $playerRepository->getDunkContestCount($player->name);
    $rooksoph = $playerRepository->getRookieSophChallengeCount($player->name);

    // Include styles
    echo PlayerTradingCardBackView::getStyles();

    // Render the back of the card
    echo '<tr><td colspan="2">';
    echo PlayerTradingCardBackView::render(
        $player,
        $playerStats,
        $playerID,
        $asg,
        $threepointcontests,
        $dunkcontests,
        $rooksoph
    );
    echo '</td></tr>';

    // Create view factory with all required dependencies
    $statsRepository = new PlayerStatsRepository($mysqli_db);
    $viewFactory = new PlayerViewFactory($playerRepository, $statsRepository, $commonRepository);

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
    $commonRepository = new CommonMysqliRepository($mysqli_db);
    $userTeamName = $commonRepository->getTeamnameFromUsername(strval($cookie[1] ?? ''));

    Nuke\Header::header();
    OpenTable();
    UI::playerMenu();

    // Use NegotiationProcessor to handle all business logic
    $processor = new NegotiationProcessor($db, $mysqli_db);
    echo $processor->processNegotiation($playerID, $userTeamName, $prefix);

    CloseTable();
    Nuke\Footer::footer();
}

function rookieoption($pid)
{
    global $db, $cookie, $mysqli_db;
    
    // Initialize dependencies
    $commonRepository = new CommonMysqliRepository($mysqli_db);
    $season = new Season($mysqli_db);
    $validator = new RookieOptionValidator();
    $formView = new RookieOptionFormView();
    
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
