<?php

declare(strict_types=1);

try {
    require __DIR__ . '/../../mainfile.php';
} catch (Exception $e) {
    error_log("Failed to load mainfile.php: " . $e->getMessage());
    die("Error loading system files. Please contact the administrator.");
}

global $mysqli_db;

if (!isset($mysqli_db) || !($mysqli_db instanceof mysqli)) {
    error_log("Database connection not available");
    die("Error: Database connection failed");
}

if (!\Utilities\CsrfGuard::validateSubmittedToken('trade_accept')) {
    \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&error=' . rawurlencode('Invalid or expired form submission. Please try again.'));
}

$offerId = $_POST['offer'] ?? null;

if ($offerId !== null) {
    $offerId = (int) $offerId;

    // Check if trade still exists (may have been accepted/declined via Discord)
    $repository = new Trading\TradeOfferRepository($mysqli_db);
    $tradeRows = $repository->getTradesByOfferId($offerId);

    if ($tradeRows === []) {
        \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade&result=already_processed');
    }

    try {
        $tradeProcessor = new Trading\TradeProcessor($mysqli_db);
        $result = $tradeProcessor->processTrade($offerId);

        if ($result['success']) {
            \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade&result=trade_accepted');
        } else {
            \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade&result=accept_error&error=' . rawurlencode($result['error'] ?? 'Unknown error'));
        }
    } catch (Exception $e) {
        error_log("Failed to process trade: " . $e->getMessage());
        \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade&result=accept_error&error=' . rawurlencode($e->getMessage()));
    }
} else {
    \Utilities\HtmxHelper::redirect('/ibl5/modules.php?name=Trading&op=reviewtrade');
}
