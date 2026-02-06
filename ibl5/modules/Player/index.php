<?php

use Player\Player;
use Player\PlayerStats;
use Player\PlayerPageService;
use Player\PlayerRepository;
use Player\PlayerStatsRepository;
use Player\Views\PlayerButtonsView;
use Player\Views\PlayerMenuView;
use Player\Views\PlayerViewFactory;
use Player\Views\PlayerTradingCardFlipView;
use Player\Views\PlayerStatsCardView;
use Player\Views\PlayerStatsFlipCardView;
use Player\Views\TeamColorHelper;
use RookieOption\RookieOptionValidator;
use RookieOption\RookieOptionFormView;
use RookieOption\RookieOptionController;
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

    // Render result banner from PRG redirect (e.g., after rookie option exercise)
    $result = $_GET['result'] ?? null;
    if ($result !== null) {
        $resultBanners = [
            'rookie_option_success' => ['class' => 'ibl-alert--success', 'message' => 'Rookie option has been exercised successfully. The contract update is reflected on the team page.'],
            'email_failed' => ['class' => 'ibl-alert--warning', 'message' => 'Rookie option exercised, but the notification email failed to send. Please notify the commissioner.'],
        ];
        if (isset($resultBanners[$result])) {
            $banner = $resultBanners[$result];
            echo '<tr><td colspan="2"><div class="ibl-alert ' . $banner['class'] . '">' . \Utilities\HtmlSanitizer::safeHtmlOutput($banner['message']) . '</div></td></tr>';
        }
    }

    // Generate team color scheme once for the entire page
    $teamColors = TeamColorHelper::getTeamColors($mysqli_db, $player->teamID);
    $colorScheme = TeamColorHelper::generateColorScheme($teamColors['color1'], $teamColors['color2']);
    
    // CSS is centralized in design/components/player-cards.css and player-views.css.
    // Only JavaScript for flip interactions needs to be emitted here.
    echo PlayerTradingCardFlipView::getFlipStyles();
    echo PlayerStatsFlipCardView::getFlipStyles($colorScheme);

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
        $rooksoph,
        $mysqli_db  // Pass database connection for team colors
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

    // Render player page navigation menu (between card and stats)
    echo PlayerMenuView::render($playerID, $pageView, $colorScheme);

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
            $showAveragesFirst,
            $colorScheme  // Pass the pre-generated color scheme
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
            $showAveragesFirst,
            $colorScheme  // Pass the pre-generated color scheme
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
            $showAveragesFirst,
            $colorScheme  // Pass the pre-generated color scheme
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
            $showAveragesFirst,
            $colorScheme  // Pass the pre-generated color scheme
        );
        echo '</td></tr>';
    } elseif ($pageView === PlayerPageType::RATINGS_AND_SALARY) {
        // Ratings and Salary - single view with stats card wrapper
        $view = $viewFactory->createRatingsAndSalaryView();
        echo '<tr><td colspan="2">';
        echo PlayerStatsCardView::wrap($view->renderRatingsAndSalary($playerID));
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

    // Use NegotiationProcessor to handle all business logic
    $processor = new NegotiationProcessor($db, $mysqli_db);
    echo $processor->processNegotiation($playerID, $userTeamName, $prefix);

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

    Nuke\Header::header();

    // Validate player ownership
    $ownershipValidation = $validator->validatePlayerOwnership($player, $userTeamName);
    if (!$ownershipValidation['valid']) {
        echo '<div class="ibl-alert ibl-alert--error">' . \Utilities\HtmlSanitizer::safeHtmlOutput($ownershipValidation['error']) . '</div>';
        echo '<a href="javascript:history.back()" class="ibl-btn ibl-btn--primary" style="margin-top: 0.5rem; display: inline-block;">Go Back</a>';
        Nuke\Footer::footer();
        return;
    }

    // Validate eligibility and get final year salary
    $eligibilityValidation = $validator->validateEligibilityAndGetSalary($player, $season->phase);
    if (!$eligibilityValidation['valid']) {
        echo '<div class="ibl-alert ibl-alert--error">' . \Utilities\HtmlSanitizer::safeHtmlOutput($eligibilityValidation['error']) . '</div>';
        echo '<a href="javascript:history.back()" class="ibl-btn ibl-btn--primary" style="margin-top: 0.5rem; display: inline-block;">Go Back</a>';
        Nuke\Footer::footer();
        return;
    }

    // Calculate rookie option value (2x final year salary)
    $rookieOptionValue = 2 * $eligibilityValidation['finalYearSalary'];

    // Get PRG redirect params and origin tracking
    $error = $_GET['error'] ?? null;
    $result = $_GET['result'] ?? null;
    $from = $_GET['from'] ?? null;

    // Render form
    echo $formView->renderForm($player, $userTeamName, $rookieOptionValue, $error, $result, $from);

    Nuke\Footer::footer();
}

function processrookieoption()
{
    global $mysqli_db;

    // Get POST parameters
    $teamName = $_POST['teamname'] ?? '';
    $playerID = isset($_POST['playerID']) ? (int) $_POST['playerID'] : 0;
    $extensionAmount = isset($_POST['rookieOptionValue']) ? (int) $_POST['rookieOptionValue'] : 0;
    $from = $_POST['from'] ?? '';

    // Validate input
    if ($teamName === '' || $playerID === 0 || $extensionAmount === 0) {
        header('Location: modules.php?name=Player&pa=rookieoption&pid=' . $playerID . '&from=' . rawurlencode($from) . '&error=' . rawurlencode('Invalid request. Missing required parameters.'));
        exit;
    }

    // Process rookie option using controller
    $controller = new RookieOptionController($mysqli_db);
    $result = $controller->processRookieOption($teamName, $playerID, $extensionAmount);

    $resultParam = '';
    if ($result['success']) {
        $resultParam = ($result['emailSuccess'] ?? true) ? 'rookie_option_success' : 'email_failed';
    }

    if ($result['success'] && $from === 'fa') {
        // Came from Free Agency — redirect back there with result banner
        header('Location: modules.php?name=FreeAgency&result=' . $resultParam);
    } elseif ($result['success']) {
        // Came from Player page (or unknown) — redirect to player page with result banner
        header('Location: modules.php?name=Player&pa=showpage&pid=' . $playerID . '&result=' . $resultParam);
    } else {
        // Error — redirect back to rookie option form with error
        header('Location: modules.php?name=Player&pa=rookieoption&pid=' . $playerID . '&from=' . rawurlencode($from) . '&error=' . rawurlencode($result['message']));
    }
    exit;
}

switch ($pa) {

    case "negotiate":
        negotiate($pid);
        break;

    case "rookieoption":
        rookieoption($pid);
        break;

    case "processrookieoption":
        processrookieoption();
        break;

    case "showpage":
        showpage($pid, $pageView);
        break;
}
