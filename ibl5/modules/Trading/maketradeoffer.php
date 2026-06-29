<?php

declare(strict_types=1);

try {
    require __DIR__ . '/../../mainfile.php';
} catch (Exception $e) {
    error_log("Failed to load mainfile.php: " . $e->getMessage());
    die("Error loading system files. Please contact the administrator.");
}

global $mysqli_db, $user;

if (!isset($mysqli_db) || !($mysqli_db instanceof mysqli)) {
    \Logging\LoggerFactory::getChannel('trade')->critical('Database connection not available');
    die("Error: Database connection failed");
}

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
$validator = new \Trading\TradeValidator($mysqli_db);
$salaryCapRepo = new \Repositories\SalaryCapRepository($mysqli_db);
$cashRepo = new \Trading\TradeCashRepository($mysqli_db);
$executionService = new \Trading\TradeExecutionService(
    $offerRepo, $processor, $validator, $salaryCapRepo, $teamIdentityRepo, $cashRepo
);
global $authService;
$controller = new \Trading\TradingController(
    $service, $offerRepo, $tradeOffer, $view,
    $teamIdentityRepo, $nukeCompat, $mysqli_db, $executionService, $authService
);

$controller->submitTradeOffer($user, $_POST);
