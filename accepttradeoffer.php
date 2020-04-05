<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_iblleague";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$offer_id = $_POST['offer'];

$query0="SELECT * FROM nuke_ibl_trade_info WHERE tradeofferid = '$offer_id'";
$result0=mysql_query($query0);
$num0 = mysql_numrows($result0);

$i=0;

$storytext="";

while ($i < $num0)
{
$itemid = mysql_result($result0,$i,"itemid");
$itemtype = mysql_result($result0,$i,"itemtype");
$from = mysql_result($result0,$i,"from");
$to = mysql_result($result0,$i,"to");

if ($itemtype == 0) {
$queryj="SELECT * FROM ibl_draft_picks WHERE `pickid` = '$itemid'";
$resultj=mysql_query($queryj);
$storytext = $storytext."The $from send the ".mysql_result($resultj,0,"year")." ".mysql_result($resultj,0,"teampick")." Round ".mysql_result($resultj,0,"round")." draft pick to the $to.<br>";

$queryi="UPDATE ibl_draft_picks SET `ownerofpick` = '$to' WHERE `pickid` = '$itemid' LIMIT 1";
$resulti=mysql_query($queryi);

} else {
$queryj="SELECT * FROM nuke_ibl_team_info WHERE team_name = '$to'";
$resultj=mysql_query($queryj);
$tid = mysql_result($resultj,0,"teamid");

$queryk="SELECT * FROM nuke_iblplyr WHERE pid = '$itemid'";
$resultk=mysql_query($queryk);

$storytext = $storytext."The $from send ".mysql_result($resultk,0,"pos")." ".mysql_result($resultk,0,"name")." to the $to.<br>";

$queryi="UPDATE nuke_iblplyr SET `teamname` = '$to', `tid` = '$tid' WHERE `pid` = '$itemid' LIMIT 1";
$resulti=mysql_query($queryi);
}

$i++;
}

$timestamp=date('Y-m-d H:i:s',time());
$storytitle="$from and $to make a trade.";

$querystor="INSERT INTO nuke_stories (catid,aid,title,time,hometext,topic,informant,counter,alanguage) VALUES ('2','Associated Press','$storytitle','$timestamp','$storytext','31','Associated Press','0','english')";
$resultstor=mysql_query($querystor);

$queryclear="DELETE FROM nuke_ibl_trade_info WHERE `tradeofferid` = '$offer_id'";
$resultclear=mysql_query($queryclear);

?>

<HTML><HEAD><TITLE>Trade Offer Processing</TITLE>
<meta http-equiv="refresh" content="0;url=http://www.iblhoops.net/modules.php?name=Team&op=reviewtrades">
</HEAD><BODY>
Trade Offer Accepted.  Redirecting you to trade review page...
</BODY></HTML>