<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__FILE__) . '/accept_trade_errors.log');

try {
    require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';
} catch (Exception $e) {
    error_log("Failed to load mainfile.php: " . $e->getMessage());
    die("Error loading system files. Please contact the administrator.");
}

global $mysqli_db;

// Check database connection
if (!isset($mysqli_db) || !($mysqli_db instanceof mysqli)) {
    error_log("Database connection not available");
    die("Error: Database connection failed");
}

$offerId = $_POST['offer'] ?? null;

if ($offerId != NULL) {
    try {
        $tradeProcessor = new Trading\TradeProcessor($mysqli_db);
        $result = $tradeProcessor->processTrade((int)$offerId);

        if ($result['success']) {
            // Trade processed successfully
            echo "Trade accepted!<p>";
        } else {
            echo "Error processing trade: " . htmlspecialchars($result['error'] ?? 'Unknown error');
            exit;
        }
    } catch (Exception $e) {
        error_log("Failed to process trade: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        die("Error processing trade: " . htmlspecialchars($e->getMessage()));
    }
} else {
    echo "Nothing to see here!";
    exit;
}

?>

<HTML><HEAD><TITLE>Trade Offer Processing</TITLE>
<meta http-equiv="refresh" content="3;url=/ibl5/modules.php?name=Trading&op=reviewtrade">
</HEAD><BODY>
<a href="/ibl5/modules.php?name=Trading&op=reviewtrade">Click here to go back to the Trade Review page,</a><br>
or wait 3 seconds to be redirected automatically!
</BODY></HTML>
