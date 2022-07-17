<?php

require 'mainfile.php';

$offer_id = $_POST['offer'];

$queryclear = "DELETE FROM nuke_ibl_trade_info WHERE `tradeofferid` = '$offer_id'";
$resultclear = $db->sql_query($queryclear);

?>

<HTML><HEAD><TITLE>Trade Offer Processing</TITLE>
<meta http-equiv="refresh" content="0;url=modules.php?name=Trading&op=reviewtrade">
</HEAD><BODY>
Trade Offer Rejected. Redirecting you to trade review page...
</BODY></HTML>
