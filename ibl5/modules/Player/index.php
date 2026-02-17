<?php

use Player\Player;
use Player\PlayerPageController;
use Player\PlayerRepository;
use RookieOption\RookieOptionValidator;
use RookieOption\RookieOptionFormView;
use RookieOption\RookieOptionController;
use Services\CommonMysqliRepository;
use Negotiation\NegotiationProcessor;

global $mysqli_db;

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Player Archives";

/**
 * Show a player page — thin wrapper around PlayerPageController
 *
 * @param mixed $playerID Player ID or UUID string
 * @param mixed $pageView Page view type
 */
function showpage($playerID, $pageView): void
{
    global $mysqli_db, $cookie;

    // Resolve UUID to numeric PID if a UUID string was passed instead of an integer
    if (!is_numeric($playerID) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) $playerID)) {
        $playerRepo = new PlayerRepository($mysqli_db);
        $resolvedPid = $playerRepo->getPlayerIdByUuid((string) $playerID);
        if ($resolvedPid !== null) {
            $playerID = $resolvedPid;
        }
    }
    $playerID = (int) $playerID;
    $pageView = ($pageView !== null) ? intval($pageView) : null;

    $controller = new PlayerPageController($mysqli_db);

    Nuke\Header::header();
    echo $controller->renderPage($playerID, $pageView, strval($cookie[1] ?? ''));
    Nuke\Footer::footer();
}

function negotiate($playerID)
{
    global $prefix, $mysqli_db, $cookie;

    $playerID = intval($playerID);

    Nuke\Header::header();

    // Get user's team name using existing CommonRepository (must be after header() which populates $cookie)
    $commonRepository = new CommonMysqliRepository($mysqli_db);
    $userTeamName = $commonRepository->getTeamnameFromUsername(strval($cookie[1] ?? ''));

    // Use NegotiationProcessor to handle all business logic
    $processor = new NegotiationProcessor($mysqli_db);
    echo $processor->processNegotiation($playerID, $userTeamName, $prefix);

    Nuke\Footer::footer();
}

function rookieoption($pid)
{
    global $cookie, $mysqli_db;

    // Initialize dependencies
    $commonRepository = new CommonMysqliRepository($mysqli_db);
    $season = new Season($mysqli_db);
    $validator = new RookieOptionValidator();
    $formView = new RookieOptionFormView();

    // Load player
    $player = Player::withPlayerID($mysqli_db, $pid);

    Nuke\Header::header();

    // Get user's team name (must be after header() which populates $cookie)
    $userTeamName = $commonRepository->getTeamnameFromUsername(strval($cookie[1] ?? ''));

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
