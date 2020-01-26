<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$offer_id = $_POST['offer'];

$queryclear="DELETE FROM nuke_ibl_trade_info WHERE `tradeofferid` = '$offer_id'";
$resultclear=mysql_query($queryclear);

?>

<HTML><HEAD><TITLE>Trade Offer Processing</TITLE>
<meta http-equiv="refresh" content="0;url=modules.php?name=Team&op=reviewtrades">
</HEAD><BODY>
Trade Offer Rejected.  Redirecting you to trade review page...
</BODY></HTML>
