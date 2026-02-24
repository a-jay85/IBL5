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

// Prepare trade data from POST
$tradeData = [
    'offeringTeam' => $_POST['offeringTeam'],
    'listeningTeam' => $_POST['listeningTeam'],
    'switchCounter' => $_POST['switchCounter'],
    'fieldsCounter' => $_POST['fieldsCounter'] + 1,
    'userSendsCash' => [],
    'partnerSendsCash' => [],
    'check' => [],
    'contract' => [],
    'index' => [],
    'type' => []
];

// Extract cash data
$i = 0;
while ($i < 7) {
    $tradeData['userSendsCash'][$i] = (int) ($_POST['userSendsCash' . $i] ?? 0);
    $tradeData['partnerSendsCash'][$i] = (int) ($_POST['partnerSendsCash' . $i] ?? 0);
    $i++;
}

// Extract form field data
for ($j = 0; $j < $tradeData['fieldsCounter']; $j++) {
    $tradeData['check'][$j] = $_POST['check' . $j] ?? null;
    $tradeData['contract'][$j] = $_POST['contract' . $j] ?? 0;
    $tradeData['index'][$j] = $_POST['index' . $j] ?? 0;
    $tradeData['type'][$j] = $_POST['type' . $j] ?? 0;
}

// Create trade offer using existing class
try {
    $tradeOffer = new Trading\TradeOffer($mysqli_db);
    $result = $tradeOffer->createTradeOffer($tradeData);
} catch (Exception $e) {
    error_log("Failed to create trade offer: " . $e->getMessage());
    $result = ['success' => false, 'error' => $e->getMessage()];
}

if ($result['success']) {
    header('Location: /ibl5/modules.php?name=Trading&op=reviewtrade&result=offer_sent');
} else {
    // Store checked items and cash amounts in session so the form can restore them
    $checkedItems = [];
    for ($j = 0; $j < $tradeData['fieldsCounter']; $j++) {
        if (($tradeData['check'][$j] ?? null) === 'on') {
            $itemKey = ($tradeData['type'][$j] ?? '0') . ':' . ($tradeData['index'][$j] ?? '0');
            $checkedItems[$itemKey] = true;
        }
    }
    $_SESSION['tradeFormData'] = [
        'checkedItems' => $checkedItems,
        'userSendsCash' => $tradeData['userSendsCash'],
        'partnerSendsCash' => $tradeData['partnerSendsCash'],
    ];

    $error = $result['error'] ?? ($result['errors'] ? implode('; ', $result['errors']) : 'Unknown error');
    header('Location: /ibl5/modules.php?name=Trading&op=offertrade&partner=' . rawurlencode($tradeData['listeningTeam']) . '&error=' . rawurlencode($error));
}
exit;
