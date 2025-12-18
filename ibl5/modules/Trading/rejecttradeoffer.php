<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $mysqli_db;

$offer_id = $_POST['offer'];
$teamRejecting = $_POST['teamRejecting'];
$teamReceiving = $_POST['teamReceiving'];

$stmtClearInfo = $mysqli_db->prepare("DELETE FROM ibl_trade_info WHERE tradeOfferID = ?");
$stmtClearInfo->bind_param("i", $offer_id);
$resultClearInfo = $stmtClearInfo->execute();
$stmtClearInfo->close();

$stmtClearCash = $mysqli_db->prepare("DELETE FROM ibl_trade_cash WHERE tradeOfferID = ?");
$stmtClearCash->bind_param("i", $offer_id);
$resultClearCash = $stmtClearCash->execute();
$stmtClearCash->close();

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

?>

<HTML><HEAD><TITLE>Trade Offer Processing</TITLE>
<meta http-equiv="refresh" content="0;url='/ibl5/modules.php?name=Trading&op=reviewtrade'">
</HEAD><BODY>
Trade Offer Rejected. Redirecting you to trade review page...
</BODY></HTML>
