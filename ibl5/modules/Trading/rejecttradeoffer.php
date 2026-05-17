<?php

declare(strict_types=1);

try {
    require __DIR__ . '/../../mainfile.php';
} catch (Exception $e) {
    error_log("Failed to load mainfile.php: " . $e->getMessage());
    die("Error loading system files. Please contact the administrator.");
}

global $mysqli_db;

if (!\Security\CsrfGuard::validateSubmittedToken('trade_reject')) {
    \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&error=' . rawurlencode('Invalid or expired form submission. Please try again.'));
}

if (!isset($_POST['offer']) || empty($_POST['offer'])) {
    \Logging\LoggerFactory::getChannel('trade')->warning('Missing offer ID in POST data');
    die("Error: Missing trade offer ID");
}

if (!isset($mysqli_db) || !($mysqli_db instanceof mysqli)) {
    \Logging\LoggerFactory::getChannel('trade')->critical('Database connection not available');
    die("Error: Database connection failed");
}

$offerId = (int) $_POST['offer'];
$teamRejecting = $_POST['teamRejecting'] ?? '';
$teamReceiving = $_POST['teamReceiving'] ?? '';

// Check if trade still exists (may have been accepted/declined via Discord)
$repository = new Trading\TradeOfferRepository($mysqli_db);
$tradeRows = $repository->getTradesByOfferId($offerId);

if ($tradeRows === []) {
    \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade&result=already_processed');
}

// Delete trade offer
$repository->deleteTradeOffer($offerId);

\Logging\LoggerFactory::getChannel('audit')->info('trade_offer_rejected', [
    'offer_id' => $offerId,
]);

// Attempt Discord notification (gracefully fail if not available)
try {
    $commonRepo = new \Services\TeamIdentityRepository($mysqli_db);
    $discord = new \Discord\Discord($commonRepo);
    $rejectingUserDiscordID = $discord->getDiscordIDFromTeamname($teamRejecting);
    $receivingUserDiscordID = $discord->getDiscordIDFromTeamname($teamReceiving);
    $declineMessage = Trading\TradingService::buildDeclineMessage($rejectingUserDiscordID, $teamRejecting);
    \Discord\Discord::sendDM($receivingUserDiscordID, $declineMessage);
} catch (Exception $e) {
    // Silently fail if Discord notification fails
    // The trade rejection itself has already succeeded
}

\Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade&result=trade_rejected');
