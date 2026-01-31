<?php

try {
    require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';
} catch (Exception $e) {
    error_log("Failed to load mainfile.php: " . $e->getMessage());
    die("Error loading system files. Please contact the administrator.");
}

global $mysqli_db;

if (!isset($_POST['offer']) || empty($_POST['offer'])) {
    error_log("Missing offer ID in POST data");
    die("Error: Missing trade offer ID");
}

if (!isset($mysqli_db) || !($mysqli_db instanceof mysqli)) {
    error_log("Database connection not available");
    die("Error: Database connection failed");
}

$offerId = (int) $_POST['offer'];
$teamRejecting = $_POST['teamRejecting'] ?? '';
$teamReceiving = $_POST['teamReceiving'] ?? '';

// Delete trade offer using repository
$repository = new Trading\TradingRepository($mysqli_db);
$repository->deleteTradeOffer($offerId);

// Attempt Discord notification (gracefully fail if not available)
try {
    $discord = new Discord($mysqli_db);
    $rejectingUserDiscordID = $discord->getDiscordIDFromTeamname($teamRejecting);
    $receivingUserDiscordID = $discord->getDiscordIDFromTeamname($teamReceiving);
    $discordDMmessage = 'Sorry, trade proposal declined by <@!' . $rejectingUserDiscordID . '>.
Go here to make another offer: http://www.iblhoops.net/ibl5/modules.php?name=Trading&op=reviewtrade';
    $arrayContent = [
        'message' => $discordDMmessage,
        'receivingUserDiscordID' => $receivingUserDiscordID,
    ];
} catch (Exception $e) {
    // Silently fail if Discord notification fails
    // The trade rejection itself has already succeeded
}

$view = new Trading\TradingView();
echo $view->renderTradeRejected();
