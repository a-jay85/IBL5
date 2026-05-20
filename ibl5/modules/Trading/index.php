<?php

declare(strict_types=1);

if (stripos($_SERVER['PHP_SELF'], "modules.php") === false) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

$pagetitle = "- Team Pages";

global $mysqli_db;

$serverName = $_SERVER['SERVER_NAME'] ?? '';
$teamIdentityRepo = new \Repositories\TeamIdentityRepository($mysqli_db);
$offerRepo = new \Trading\TradeOfferRepository($mysqli_db, $serverName);
$assetRepo = new \Trading\TradeAssetRepository($mysqli_db);
$formRepo = new \Trading\TradeFormRepository($mysqli_db);
$service = new \Trading\TradingService($offerRepo, $assetRepo, $formRepo, $teamIdentityRepo, $mysqli_db);
$processor = new \Trading\TradeProcessor($mysqli_db, $teamIdentityRepo, $serverName, $offerRepo, $assetRepo);
$tradeOffer = new \Trading\TradeOffer($mysqli_db, $teamIdentityRepo, $serverName);
$view = new \Trading\TradingView();
$nukeCompat = new \Utilities\NukeCompat();
$controller = new \Trading\TradingController(
    $service, $processor, $offerRepo, $tradeOffer, $view,
    $teamIdentityRepo, $nukeCompat, $mysqli_db
);

switch ($op) {
    case "reviewtrade":
        $controller->handleTradeReview($user);
        break;
    case "offertrade":
        $controller->handleTradeOffer($user, $partner ?? null);
        break;
    case "roster-preview-api":
        $controller->handleRosterPreviewApi($user);
        break;
    default:
        $controller->handleTradeReview($user);
        break;
}
