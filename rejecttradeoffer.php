<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_iblleague";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$offer_id = $_POST['offer'];

$queryclear="DELETE FROM nuke_ibl_trade_info WHERE `tradeofferid` = '$offer_id'";
$resultclear=mysql_query($queryclear);

?>

<HTML><HEAD><TITLE>Trade Offer Processing</TITLE>
<meta http-equiv="refresh" content="0;url=http://www.iblhoops.net/modules.php?name=Team&op=reviewtrades">
</HEAD><BODY>
Trade Offer Rejected.  Redirecting you to trade review page...
</BODY></HTML>