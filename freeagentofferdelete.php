<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_iblleague";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

echo "<HTML><HEAD><TITLE>Free Agency Offer Deletion</TITLE></HEAD><BODY>";

$teamname = $_POST['teamname'];
$playername = $_POST['playername'];

// ==== ENTER OFFER INTO DATABASE ====

$querychunk="DELETE FROM `nuke_ibl_fa_offers` WHERE `name` = '$playername' AND `team` = '$teamname'";
$resultchunk=mysql_query($querychunk);

echo "Your offers have been deleted.  This should show up immediately.  Please <a href=\"http://www.iblhoops.net/modules.php?name=Free_Agency\">click here to return to the Free Agency main page</a> (your offer should now be gone).</br>";

?>