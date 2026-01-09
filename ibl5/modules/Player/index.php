<?php

use Player\Player;
use Player\PlayerStats;
use Player\PlayerPageService;
use Player\PlayerRepository;
use Player\PlayerStatsRepository;
use Player\Views\PlayerViewStyles;
use Player\Views\PlayerButtonsView;
use Player\Views\PlayerMenuView;
use Player\Views\PlayerViewFactory;
use Player\Views\PlayerTradingCardFrontView;
use Player\Views\PlayerTradingCardBackView;
use Player\Views\PlayerTradingCardFlipView;
use Player\Views\PlayerStatsCardView;
use Player\Views\PlayerStatsFlipCardView;
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
    
    // Include trading card styles (both front and back)
    echo PlayerTradingCardFrontView::getStyles();
    echo PlayerTradingCardBackView::getStyles();
    echo PlayerTradingCardFlipView::getFlipStyles();
    
    // Include legacy player view styles for other components
    echo PlayerViewStyles::getStyles();
    
    // Include stats card styles AFTER legacy styles to ensure they override
    echo PlayerStatsCardView::getStyles();
    echo PlayerStatsFlipCardView::getFlipStyles();
    
    // Render player menu with current page selected
    echo PlayerMenuView::render($playerID, $pageView);

    // Get All-Star Activity data using PlayerRepository
    $playerRepository = new PlayerRepository($mysqli_db);
    $asg = $playerRepository->getAllStarGameCount($player->name);
    $threepointcontests = $playerRepository->getThreePointContestCount($player->name);
    $dunkcontests = $playerRepository->getDunkContestCount($player->name);
    $rooksoph = $playerRepository->getRookieSophChallengeCount($player->name);

    // Render flippable trading card (combines front and back with flip animation)
    $contract_display = implode("/", $player->getRemainingContractArray());
    echo '<tr><td colspan="2">';
    echo PlayerTradingCardFlipView::render(
        $player,
        $playerStats,
        $playerID,
        $contract_display,
        $asg,
        $threepointcontests,
        $dunkcontests,
        $rooksoph
    );
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

    // Create view factory with all required dependencies
    $statsRepository = new PlayerStatsRepository($mysqli_db);
    $viewFactory = new PlayerViewFactory($playerRepository, $statsRepository, $commonRepository);

    // Render the appropriate view using the factory with stats card styling
    // For views with Averages/Totals pairs, use flip cards
    // For single views, use the stats card wrapper
    // All card outputs are wrapped in <tr><td> for table-based page layout
    
    if ($pageView === PlayerPageType::OVERVIEW) {
        $view = $viewFactory->createView($pageView);
        echo $view->renderOverview($playerID, $player, $playerStats, $season, $sharedFunctions);
    } elseif ($pageView === PlayerPageType::SIM_STATS) {
        // Sim stats - single view with stats card wrapper
        $view = $viewFactory->createSimStatsView();
        echo '<tr><td colspan="2">';
        echo PlayerStatsCardView::render($view->renderSimStats($playerID));
        echo '</td></tr>';
    } elseif ($pageView === PlayerPageType::REGULAR_SEASON_TOTALS || $pageView === PlayerPageType::REGULAR_SEASON_AVERAGES) {
        // Regular Season - flip card with Averages/Totals toggle
        $averagesView = $viewFactory->createRegularSeasonAveragesView();
        $totalsView = $viewFactory->createRegularSeasonTotalsView();
        $showAveragesFirst = ($pageView === PlayerPageType::REGULAR_SEASON_AVERAGES);
        echo '<tr><td colspan="2">';
        echo PlayerStatsFlipCardView::render(
            $averagesView->renderAverages($playerID),
            $totalsView->renderTotals($playerID),
            'Regular Season',
            $showAveragesFirst
        );
        echo '</td></tr>';
    } elseif ($pageView === PlayerPageType::PLAYOFF_TOTALS || $pageView === PlayerPageType::PLAYOFF_AVERAGES) {
        // Playoffs - flip card with Averages/Totals toggle
        $averagesView = $viewFactory->createPlayoffAveragesView();
        $totalsView = $viewFactory->createPlayoffTotalsView();
        $showAveragesFirst = ($pageView === PlayerPageType::PLAYOFF_AVERAGES);
        echo '<tr><td colspan="2">';
        echo PlayerStatsFlipCardView::render(
            $averagesView->renderAverages($player->name),
            $totalsView->renderTotals($player->name),
            'Playoffs',
            $showAveragesFirst
        );
        echo '</td></tr>';
    } elseif ($pageView === PlayerPageType::HEAT_TOTALS || $pageView === PlayerPageType::HEAT_AVERAGES) {
        // H.E.A.T. - flip card with Averages/Totals toggle
        $averagesView = $viewFactory->createHeatAveragesView();
        $totalsView = $viewFactory->createHeatTotalsView();
        $showAveragesFirst = ($pageView === PlayerPageType::HEAT_AVERAGES);
        echo '<tr><td colspan="2">';
        echo PlayerStatsFlipCardView::render(
            $averagesView->renderAverages($player->name),
            $totalsView->renderTotals($player->name),
            'H.E.A.T.',
            $showAveragesFirst
        );
        echo '</td></tr>';
    } elseif ($pageView === PlayerPageType::OLYMPIC_TOTALS || $pageView === PlayerPageType::OLYMPIC_AVERAGES) {
        // Olympics - flip card with Averages/Totals toggle
        $averagesView = $viewFactory->createOlympicAveragesView();
        $totalsView = $viewFactory->createOlympicTotalsView();
        $showAveragesFirst = ($pageView === PlayerPageType::OLYMPIC_AVERAGES);
        echo '<tr><td colspan="2">';
        echo PlayerStatsFlipCardView::render(
            $averagesView->renderAverages($player->name),
            $totalsView->renderTotals($player->name),
            'Olympics',
            $showAveragesFirst
        );
        echo '</td></tr>';
    } elseif ($pageView === PlayerPageType::RATINGS_AND_SALARY) {
        // Ratings and Salary - single view with stats card wrapper
        $view = $viewFactory->createRatingsAndSalaryView();
        echo '<tr><td colspan="2">';
        echo PlayerStatsCardView::render($view->renderRatingsAndSalary($playerID));
        echo '</td></tr>';
    } elseif ($pageView === PlayerPageType::AWARDS_AND_NEWS) {
        $view = $viewFactory->createView($pageView);
        echo '<tr><td colspan="2">';
        echo PlayerStatsCardView::render($view->renderAwardsAndNews($player->name));
        echo '</td></tr>';
    } elseif ($pageView === PlayerPageType::ONE_ON_ONE) {
        $view = $viewFactory->createView($pageView);
        echo '<tr><td colspan="2">';
        echo PlayerStatsCardView::render($view->renderOneOnOneResults($player->name));
        echo '</td></tr>';
    } else {
        // Default to overview
        $view = $viewFactory->createOverviewView();
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
