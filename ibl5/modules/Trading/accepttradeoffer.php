<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';
$sharedFunctions = new Shared($db);
$season = new Season($db);

$offer_id = $_POST['offer'];

if ($offer_id != NULL) {
    $tradeProcessor = new Trading_TradeProcessor($db);
    $result = $tradeProcessor->processTrade($offer_id);
    
    if ($result['success']) {
        // Trade processed successfully
        echo "Trade processed successfully!";
    } else {
        echo "Error processing trade: " . ($result['error'] ?? 'Unknown error');
        exit;
    }
} else {
    echo "Nothing to see here!";
    exit;
}

?>

<HTML><HEAD><TITLE>Trade Offer Processing</TITLE>
<meta http-equiv="refresh" content="3;url=/ibl5/modules.php?name=Trading&op=reviewtrade">
</HEAD><BODY>
Trade Offer accepted!
<p>
<a href="/ibl5/modules.php?name=Trading&op=reviewtrade">Click here to go back to the Trade Review page,</a><br>
or wait 3 seconds to be redirected automatically!
</BODY></HTML>
