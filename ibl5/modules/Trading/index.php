<?php

declare(strict_types=1);

$phpSelf = $_SERVER['PHP_SELF'] ?? '';
if (stripos(is_string($phpSelf) ? $phpSelf : '', "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$op      = is_string($_REQUEST['op']      ?? null) ? $_REQUEST['op']      : '';
$partner = is_string($_REQUEST['partner'] ?? null) ? $_REQUEST['partner'] : null;

$pagetitle = "- Team Pages";

global $mysqli_db, $authService, $user;
/** @var \mysqli $mysqli_db */
/** @var \Auth\Contracts\AuthServiceInterface $authService */

$httpRequest = \Http\HttpRequest::fromGlobals();
$serverNameRaw = $httpRequest->server('SERVER_NAME');
$serverName = is_string($serverNameRaw) ? $serverNameRaw : '';
$teamIdentityRepo = new \Repositories\TeamIdentityRepository($mysqli_db);
$offerRepo = new \Trading\TradeOfferRepository($mysqli_db, $serverName);
$assetRepo = new \Trading\TradeAssetRepository($mysqli_db);
$formRepo = new \Trading\TradeFormRepository($mysqli_db);
$cashRepo = new \Trading\TradeCashRepository($mysqli_db);
$offerGrouper = new \Trading\TradeOfferGrouper($assetRepo, $cashRepo);
$futureSalaryCalc = new \Trading\FutureSalaryCalculator();
$service = new \Trading\TradingService($offerRepo, $formRepo, $teamIdentityRepo, $mysqli_db, $offerGrouper, $futureSalaryCalc);
$processor = new \Trading\TradeProcessor($mysqli_db, $teamIdentityRepo, $serverName, $offerRepo, $assetRepo);
$tradeOffer = new \Trading\TradeOffer($mysqli_db, $teamIdentityRepo, $serverName);
$view = new \Trading\TradingView();
$nukeCompat = new \Utilities\NukeCompat();
$validator = new \Trading\TradeValidator($mysqli_db);
$salaryCapRepo = new \Repositories\SalaryCapRepository($mysqli_db);
$season = new \Season\Season($mysqli_db);
$executionService = new \Trading\TradeExecutionService(
    $offerRepo, $processor, $validator, $salaryCapRepo, $teamIdentityRepo, $cashRepo, $season
);
$controller = new \Trading\TradingController(
    $service, $offerRepo, $tradeOffer, $view,
    $teamIdentityRepo, $nukeCompat, $mysqli_db, $executionService, $authService
);

switch ($op) {
    case "reviewtrade":
        $controller->handleTradeReview($user);
        break;
    case "offertrade":
        $controller->handleTradeOffer($user, $partner);
        break;
    case "roster-preview-api":
        $controller->handleRosterPreviewApi($user);
        break;
    default:
        $controller->handleTradeReview($user);
        break;
}
