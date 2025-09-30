<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';
$sharedFunctions = new Shared($db);
$season = new Season($db);

// Prepare trade data from POST
$tradeData = [
    'offeringTeam' => $_POST['Team_Name'],
    'receivingTeam' => $_POST['Team_Name2'],
    'switchCounter' => $_POST['half'],
    'fieldsCounter' => $_POST['counterfields'] + 1,
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

// Create trade offer using new class
$tradeOffer = new Trading_TradeOffer($db);
$result = $tradeOffer->createTradeOffer($tradeData);

if (!$result['success']) {
    if (isset($result['error'])) {
        echo $result['error'];
    } elseif (isset($result['errors'])) {
        foreach ($result['errors'] as $error) {
            echo $error . "<br>";
        }
    }
    exit;
}
// Display success message and trade details
if (isset($result['capData'])) {
    echo "Your Payroll this season, if this trade is accepted: {$result['capData']['userPostTradeCapTotal']}<br>";
    echo "Their Payroll this season, if this trade is accepted: {$result['capData']['partnerPostTradeCapTotal']}<p>";
}

echo $result['tradeText'] ?? '';

echo "<p>";
echo "Trade Offer Entered Into Database. Go back <a href='/ibl5/modules.php?name=Trading&op=reviewtrade'>Trade Review Page</a>";
?>
