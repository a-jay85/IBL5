<?php

require 'mainfile.php';
$sharedFunctions = new Shared($db);
$season = new Season($db);

$Team_Name = $_POST['teamname'];
$Player_Name = $_POST['playername'];
$ExtensionAmount = $_POST['rookieOptionValue'];
$player_exp = $_POST['player_exp'];
$player_draftround = $_POST['player_draftround'];

$tid = $sharedFunctions->getTidFromTeamname($Team_Name);

$recipient = 'ibldepthcharts@gmail.com';
$emailsubject = "Rookie Extension Option - " . $Player_Name;
$filetext = $Team_Name . " exercise the rookie extension option on " . $Player_Name . " in the amount of " . $ExtensionAmount . ".";

if (($season->phase == "Free Agency" and $player_exp == 2 and $player_draftround == 1) or
    (($season->phase == "Preseason" or $season->phase == "HEAT") and $player_exp == 3 and $player_draftround == 1)) {
    $queryrookieoption = "UPDATE ibl_plr SET cy4 = '$ExtensionAmount' WHERE name = '$Player_Name'";
} elseif (($season->phase == "Free Agency" and $player_exp == 1 and $player_draftround == 2) or
    (($season->phase == "Preseason" or $season->phase == "HEAT") and $player_exp == 2 and $player_draftround == 2)) {
    $queryrookieoption = "UPDATE ibl_plr SET cy3 = '$ExtensionAmount' WHERE name = '$Player_Name'";
} else {
    die("This player's experience doesn't match their rookie status; please let the commish know about this error.");
}

$resultrookieoption = $db->sql_query($queryrookieoption);

echo "<html><head><title>Rookie Option Page</title></head><body>

Your rookie option has been updated in the database and should reflect on your team pages immediately.<br>";

if ($season->phase == "Free Agency") {
    echo "Please <a href=\"modules.php?name=Free_Agency\">click here to return to the Free Agency Screen</a>.";
} else {
    echo "Please <a href=\"modules.php?name=Team&op=team&tid=$tid\">click here to return to your team page</a>.";
}

Discord::postToChannel('#rookie-options', $filetext);

if (mail($recipient, $emailsubject, $filetext, "From: rookieoption@iblhoops.net")) {
    $rookieOptionInMillions = $ExtensionAmount / 100;
    $timestamp = date('Y-m-d H:i:s', time());
    $storytitle = $Player_Name . " extends their contract with the " . $Team_Name;
    $hometext = $Team_Name . " exercise the rookie extension option on " . $Player_Name . " in the amount of " . $rookieOptionInMillions . " million dollars.";

    $querytopic = "SELECT * FROM nuke_topics WHERE topicname = '$Team_Name'";
    $resulttopic = $db->sql_query($querytopic);
    $topicid = $db->sql_result($resulttopic, 0, "topicid");

    $querycat = "SELECT * FROM nuke_stories_cat WHERE title = 'Rookie Extension'";
    $resultcat = $db->sql_query($querycat);
    $RookieExtensions = $db->sql_result($resultcat, 0, "counter");
    $catid = $db->sql_result($resultcat, 0, "catid");

    $querycat2 = "UPDATE nuke_stories_cat SET counter = $RookieExtensions WHERE title = 'Rookie Extension'";
    $resultcat2 = $db->sql_query($querycat2);

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
    $resultstor = $db->sql_query($querystor);

    echo "<center>An e-mail regarding this extension has been successfully sent to the commissioner's office. Thank you.</center>";
} else {
    echo "Message failed to e-mail properly; please notify the commissioner of the error.</center>";
}
