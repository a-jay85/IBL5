<?php

require 'mainfile.php';
$sharedFunctions = new Shared($db);

$offer_id = $_POST['offer'];
$teamRejecting = $_POST['teamRejecting'];
$teamReceiving = $_POST['teamReceiving'];

$queryclear = "DELETE FROM ibl_trade_info WHERE `tradeofferid` = '$offer_id'";
$resultclear = $db->sql_query($queryclear);

$rejectingUserDiscordID = $sharedFunctions->getDiscordIDFromTeamname($teamRejecting);
$receivingUserDiscordID = $sharedFunctions->getDiscordIDFromTeamname($teamReceiving);
$discordDMmessage = 'Sorry, trade proposal declined by <@!' . $rejectingUserDiscordID . '>.

Go here to make another offer: http://www.iblhoops.net/ibl5/modules.php?name=Trading&op=reviewtrade';
$arrayContent = array(
        'message' => $discordDMmessage,
        'receivingUserDiscordID' => $receivingUserDiscordID,);

echo "<p>";
// $response = Discord::sendCurlPOST('http://localhost:50000/discordDM', $arrayContent);

?>

<HTML><HEAD><TITLE>Trade Offer Processing</TITLE>
<meta http-equiv="refresh" content="0;url=modules.php?name=Trading&op=reviewtrade">
</HEAD><BODY>
Trade Offer Rejected. Redirecting you to trade review page...
</BODY></HTML>
