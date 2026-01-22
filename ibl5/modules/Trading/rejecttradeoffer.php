<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__FILE__) . '/reject_trade_errors.log');

try {
    require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';
} catch (Exception $e) {
    error_log("Failed to load mainfile.php: " . $e->getMessage());
    die("Error loading system files. Please contact the administrator.");
}

global $mysqli_db;

// Validate POST data
if (!isset($_POST['offer']) || empty($_POST['offer'])) {
    error_log("Missing offer ID in POST data");
    die("Error: Missing trade offer ID");
}

$offer_id = (int)$_POST['offer'];
$teamRejecting = $_POST['teamRejecting'] ?? '';
$teamReceiving = $_POST['teamReceiving'] ?? '';

// Check database connection
if (!isset($mysqli_db) || !($mysqli_db instanceof mysqli)) {
    error_log("Database connection not available");
    die("Error: Database connection failed");
}

// Delete trade offer info
$stmtClearInfo = $mysqli_db->prepare("DELETE FROM ibl_trade_info WHERE tradeOfferID = ?");
if ($stmtClearInfo === false) {
    error_log("Failed to prepare DELETE ibl_trade_info statement: " . $mysqli_db->error);
    die("Error: Failed to process trade rejection (info)");
}
$stmtClearInfo->bind_param("i", $offer_id);
if (!$stmtClearInfo->execute()) {
    error_log("Failed to execute DELETE ibl_trade_info: " . $stmtClearInfo->error);
    die("Error: Failed to process trade rejection (info execute)");
}
$stmtClearInfo->close();

// Delete trade offer cash
$stmtClearCash = $mysqli_db->prepare("DELETE FROM ibl_trade_cash WHERE tradeOfferID = ?");
if ($stmtClearCash === false) {
    error_log("Failed to prepare DELETE ibl_trade_cash statement: " . $mysqli_db->error);
    die("Error: Failed to process trade rejection (cash)");
}
$stmtClearCash->bind_param("i", $offer_id);
if (!$stmtClearCash->execute()) {
    error_log("Failed to execute DELETE ibl_trade_cash: " . $stmtClearCash->error);
    die("Error: Failed to process trade rejection (cash execute)");
}
$stmtClearCash->close();

// Attempt Discord notification (gracefully fail if database doesn't support it yet)
try {
    $discord = new Discord($mysqli_db);
    $rejectingUserDiscordID = $discord->getDiscordIDFromTeamname($teamRejecting);
    $receivingUserDiscordID = $discord->getDiscordIDFromTeamname($teamReceiving);
    $discordDMmessage = 'Sorry, trade proposal declined by <@!' . $rejectingUserDiscordID . '>.
Go here to make another offer: http://www.iblhoops.net/ibl5/modules.php?name=Trading&op=reviewtrade';
    $arrayContent = array(
            'message' => $discordDMmessage,
            'receivingUserDiscordID' => $receivingUserDiscordID,);

    echo "<p>";
    // $response = Discord::sendCurlPOST('http://localhost:50000/discordDM', $arrayContent);
} catch (Exception $e) {
    // Silently fail if Discord notification fails (e.g., discordID column doesn't exist yet)
    // The trade rejection itself has already succeeded
}

?>

<HTML><HEAD><TITLE>Trade Offer Processing</TITLE>
<meta http-equiv="refresh" content="0;url='/ibl5/modules.php?name=Trading&op=reviewtrade'">
</HEAD><BODY>
Trade Offer Rejected. Redirecting you to trade review page...
</BODY></HTML>
