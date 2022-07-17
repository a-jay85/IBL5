<?php

require 'mainfile.php';

echo "<HTML><HEAD><TITLE>Position Change Result</TITLE></HEAD><BODY>";

$teamName = $_POST['teamname'];
$playerName = $_POST['playername'];
$playerOldPosition = $_POST['playerpos'];
$playerNewPosition = $_POST['pos'];

$acceptablePositions = array('PG', 'G', 'SG', 'GF', 'SF', 'F', 'PF', 'FC', 'C');

if (!in_array($playerNewPosition, $acceptablePositions)) {
    echo "Message from the commissioner's office: <font color=#FF0000>Your position change has been DECLINED: that was not a valid position.</font><p>";
    echo "<a href=\"javascript:history.go(-1)\">GO BACK</a>";
} else {
    // ==== UPDATE NUMBER OF POSITION CHANGES USED AND ALT POSITION IN DATABASE ====

    $querySetNewPlayerPosition = "UPDATE ibl_plr SET altpos = '$playerNewPosition' WHERE name = '$playerName'";
    $queryIncrementTeamPositionChanges = "UPDATE ibl_team_info SET poschanges = poschanges + 1 WHERE team_name = '$teamName'";
    $queryIncrementPlayerPositionChanges = "UPDATE ibl_plr SET poschange = '1' WHERE name = '$playerName'";

    if ($db->sql_query($querySetNewPlayerPosition) and $db->sql_query($queryIncrementTeamPositionChanges) and $db->sql_query($queryIncrementPlayerPositionChanges)) {
        // ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE

        $querycat = "SELECT * FROM nuke_stories_cat WHERE title = 'Position Changes'";
        $resultcat = $db->sql_query($querycat);
        $catid = $db->sql_result($resultcat, 0, "catid");

        $storytitle = $playerName . " changes their position with the " . $teamName;
        $timestamp = date('Y-m-d H:i:s', time());
        $hometext = $playerName . " today changed their position with the " . $teamName . " from " . $playerOldPosition . " to " . $playerNewPosition . ".";

        $querytopic = "SELECT * FROM nuke_topics WHERE topicname = '$teamName'";
        $resulttopic = $db->sql_query($querytopic);
        $topicid = $db->sql_result($resulttopic, 0, "topicid");

        $querystor = "INSERT INTO nuke_stories (catid,aid,title,time,hometext,topic,informant,counter,alanguage) VALUES ('$catid','Associated Press','$storytitle','$timestamp','$hometext','$topicid','Associated Press','0','english')";
        $resultstor = $db->sql_query($querystor);

        $querycat2 = "UPDATE nuke_stories_cat SET counter = counter + 1 WHERE title = 'Position Changes'";
        $resultcat2 = $db->sql_query($querycat2);

        echo "Message from the commissioner's office:<br><font color=#0000cc>Your position change has been submitted and approved. $playerName is now a $playerNewPosition.</font></br>";
        echo "<a href=\"javascript:history.go(-1)\">GO BACK</a>";
    } else {
        echo "Message from the commissioner's office: <font color=#FF0000>Your position change has been DECLINED: the database couldn't be written properly.</font><p>";
        echo "Please let the commissioner know something went wrong, or go back and try again.<p>";
        echo "<a href=\"javascript:history.go(-1)\">GO BACK</a>";
    }
}
