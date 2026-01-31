<?php

try {
    require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';
} catch (Exception $e) {
    error_log("Failed to load mainfile.php: " . $e->getMessage());
    die("Error loading system files. Please contact the administrator.");
}

global $mysqli_db;

if (!isset($mysqli_db) || !($mysqli_db instanceof mysqli)) {
    error_log("Database connection not available");
    die("Error: Database connection failed");
}

$offerId = $_POST['offer'] ?? null;

if ($offerId !== null) {
    try {
        $tradeProcessor = new Trading\TradeProcessor($mysqli_db);
        $result = $tradeProcessor->processTrade((int) $offerId);

        if ($result['success']) {
            $view = new Trading\TradingView();
            echo $view->renderTradeAccepted();
        } else {
            echo "Error processing trade: " . \Utilities\HtmlSanitizer::safeHtmlOutput($result['error'] ?? 'Unknown error');
        }
    } catch (Exception $e) {
        error_log("Failed to process trade: " . $e->getMessage());
        die("Error processing trade: " . \Utilities\HtmlSanitizer::safeHtmlOutput($e->getMessage()));
    }
} else {
    echo "Nothing to see here!";
}
