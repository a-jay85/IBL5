<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

$Team_Name = $_POST['teamname'];
$Player_Name = $_POST['playername'];
if (is_null($_POST['rookie_cy3'])) {
	$ExtensionAmount = $_POST['rookie_cy4'];
	$rookieOptionYear = 4;
} else {
	$ExtensionAmount = $_POST['rookie_cy3'];
	$rookieOptionYear = 3;
}

$recipient = 'ajaynicolas@gmail.com';
$emailsubject = "Rookie Extension Option - ".$Player_Name;
$filetext = $Team_Name." exercise the rookie extension option on ".$Player_Name." in the amount of ".$ExtensionAmount." in year ".$rookieOptionYear.".";

if (is_null($_POST['rookie_cy3'])) {
	$queryrookieoption="UPDATE nuke_iblplyr SET cy4 = '$ExtensionAmount' WHERE name = '$Player_Name'";
} else {
	$queryrookieoption="UPDATE nuke_iblplyr SET cy3 = '$ExtensionAmount' WHERE name = '$Player_Name'";
}

$resultrookieoption=mysql_query($queryrookieoption);

echo "<html><head><title>Rookie Option Page</title></head><body>

Your rookie option has been updated in the database and should show on the Free Agency page immediately.  Please <a href=\"modules.php?name=Free_Agency\">click here to return to the Free Agency Screen</a>.<br><br>
";

if (mail($recipient, $emailsubject, $filetext, "From: rookieoption@iblhoops.net"))
{
$rookieOptionInMillions = $ExtensionAmount/100;
$timestamp=date('Y-m-d H:i:s',time());
$storytitle=$Player_Name." extends his contract with the ".$Team_Name;
$hometext=$Team_Name." exercise the rookie extension option on ".$Player_Name." in the amount of ".$rookieOptionInMillions." million dollars.";

$querytopic="SELECT * FROM nuke_topics WHERE topicname = '$Team_Name'";
$resulttopic=mysql_query($querytopic);
$topicid=mysql_result($resulttopic,0,"topicid");

$querycat="SELECT * FROM nuke_stories_cat WHERE title = 'Rookie Extension'";
$resultcat=mysql_query($querycat);
$RookieExtensions=mysql_result($resultcat,0,"counter");
$catid=mysql_result($resultcat,0,"catid");

$querycat2="UPDATE nuke_stories_cat SET counter = $RookieExtensions WHERE title = 'Rookie Extension'";
$resultcat2=mysql_query($querycat2);

$querystor="INSERT INTO nuke_stories (catid,aid,title,time,hometext,topic,informant,counter,alanguage) VALUES ('$catid','Associated Press','$storytitle','$timestamp','$hometext','$topicid','Associated Press','0','english')";
$resultstor=mysql_query($querystor);

echo "<center> An e-mail regarding this extension has been successfully sent to the commissioner's office.  Thank you. </center>";
} else {
         echo " Message failed to e-mail properly; please notify the commissioner of the error.</center>";
}

?>
