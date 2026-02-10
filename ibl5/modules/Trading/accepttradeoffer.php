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
    $offerId = (int) $offerId;

    // Check if trade still exists (may have been accepted/declined via Discord)
    $repository = new Trading\TradingRepository($mysqli_db);
    $tradeRows = $repository->getTradesByOfferId($offerId);

    if ($tradeRows === []) {
        header('Location: /ibl5/modules.php?name=Trading&op=reviewtrade&result=already_processed');
        exit;
    }

    try {
        $tradeProcessor = new Trading\TradeProcessor($mysqli_db);
        $result = $tradeProcessor->processTrade($offerId);

        if ($result['success']) {
            header('Location: /ibl5/modules.php?name=Trading&op=reviewtrade&result=trade_accepted');
        } else {
            header('Location: /ibl5/modules.php?name=Trading&op=reviewtrade&result=accept_error&error=' . rawurlencode($result['error'] ?? 'Unknown error'));
        }
    } catch (Exception $e) {
        error_log("Failed to process trade: " . $e->getMessage());
        header('Location: /ibl5/modules.php?name=Trading&op=reviewtrade&result=accept_error&error=' . rawurlencode($e->getMessage()));
    }
} else {
    header('Location: /ibl5/modules.php?name=Trading&op=reviewtrade');
}
exit;
