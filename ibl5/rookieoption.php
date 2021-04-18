<?php

require $_SERVER['DOCUMENT_ROOT'] . '/config.php';
mysql_connect($dbhost, $dbuname, $dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

require_once $_SERVER['DOCUMENT_ROOT'] . '/discordWebhooks.php';

$Team_Name = $_POST['teamname'];
$Player_Name = $_POST['playername'];
$ExtensionAmount = $_POST['rookieOptionValue'];
$player_exp = $_POST['player_exp'];
$player_draftround = $_POST['player_draftround'];

$recipient = 'ibldepthcharts@gmail.com';
$emailsubject = "Rookie Extension Option - ".$Player_Name;
$filetext = $Team_Name . " exercise the rookie extension option on " . $Player_Name . " in the amount of " . $ExtensionAmount . ".";

if ($Player_Exp == 2) {
	$queryrookieoption="UPDATE nuke_iblplyr SET cy4 = '$ExtensionAmount' WHERE name = '$Player_Name'";
} elseif ($Player_Exp == 1) {
	$queryrookieoption="UPDATE nuke_iblplyr SET cy3 = '$ExtensionAmount' WHERE name = '$Player_Name'";
} else die("This player's experience doesn't match their rookie status; please let the commish know about this error.");

$resultrookieoption = mysql_query($queryrookieoption);

echo "<html><head><title>Rookie Option Page</title></head><body>

Your rookie option has been updated in the database and should show on the Free Agency page immediately.<br>
Please <a href=\"modules.php?name=Free_Agency\">click here to return to the Free Agency Screen</a>.<br><br>
";

postToDiscordChannel('#rookie-options', $filetext);

if (mail($recipient, $emailsubject, $filetext, "From: rookieoption@iblhoops.net")) {
	$rookieOptionInMillions = $ExtensionAmount/100;
	$timestamp = date('Y-m-d H:i:s',time());
	$storytitle = $Player_Name . " extends his contract with the " . $Team_Name;
	$hometext = $Team_Name . " exercise the rookie extension option on " . $Player_Name . " in the amount of " . $rookieOptionInMillions . " million dollars.";

	$querytopic = "SELECT * FROM nuke_topics WHERE topicname = '$Team_Name'";
	$resulttopic = mysql_query($querytopic);
	$topicid = mysql_result($resulttopic, 0, "topicid");

	$querycat = "SELECT * FROM nuke_stories_cat WHERE title = 'Rookie Extension'";
	$resultcat = mysql_query($querycat);
	$RookieExtensions = mysql_result($resultcat, 0, "counter");
	$catid = mysql_result($resultcat, 0, "catid");

	$querycat2 = "UPDATE nuke_stories_cat SET counter = $RookieExtensions WHERE title = 'Rookie Extension'";
	$resultcat2 = mysql_query($querycat2);

	$querystor = "INSERT INTO nuke_stories
            (catid,
             aid,
             title,
             time,
             hometext,
             topic,
             informant,
             counter,
             alanguage)
VALUES      ('$catid',
             'Associated Press',
             '$storytitle',
             '$timestamp',
             '$hometext',
             '$topicid',
             'Associated Press',
             '0',
             'english')";
	$resultstor = mysql_query($querystor);

	echo "<center>An e-mail regarding this extension has been successfully sent to the commissioner's office. Thank you.</center>";
} else {
	echo "Message failed to e-mail properly; please notify the commissioner of the error.</center>";
}

?>
