<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

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

    $querySetNewPlayerPosition = "UPDATE nuke_iblplyr SET altpos = '$playerNewPosition' WHERE name = '$playerName'";
    $queryIncrementTeamPositionChanges = "UPDATE nuke_ibl_team_info SET poschanges = poschanges + 1 WHERE team_name = '$teamName'";
    $queryIncrementPlayerPositionChanges = "UPDATE nuke_iblplyr SET poschange = '1' WHERE name = '$playerName'";

    if (mysql_query($querySetNewPlayerPosition) AND mysql_query($queryIncrementTeamPositionChanges) AND mysql_query($queryIncrementPlayerPositionChanges)) {
        // ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE

        $querycat = "SELECT * FROM nuke_stories_cat WHERE title = 'Position Changes'";
        $resultcat = mysql_query($querycat);
        $catid = mysql_result($resultcat,0,"catid");

        $storytitle = $playerName." changes his position with the ".$teamName;
        $timestamp = date('Y-m-d H:i:s',time());
        $hometext = $playerName." today changed his position with the ".$teamName." from " .$playerOldPosition. " to " .$playerNewPosition. ".";

        $querytopic = "SELECT * FROM nuke_topics WHERE topicname = '$teamName'";
        $resulttopic = mysql_query($querytopic);
        $topicid = mysql_result($resulttopic,0,"topicid");

        $querystor = "INSERT INTO nuke_stories (catid,aid,title,time,hometext,topic,informant,counter,alanguage) VALUES ('$catid','Associated Press','$storytitle','$timestamp','$hometext','$topicid','Associated Press','0','english')";
        $resultstor = mysql_query($querystor);

        $querycat2 = "UPDATE nuke_stories_cat SET counter = counter + 1 WHERE title = 'Position Changes'";
        $resultcat2 = mysql_query($querycat2);

        echo "Message from the commissioner's office:<br><font color=#0000cc>Your position change has been submitted and approved. $playerName is now a $playerNewPosition.</font></br>";
        echo "<a href=\"javascript:history.go(-1)\">GO BACK</a>";
    } else {
        echo "Message from the commissioner's office: <font color=#FF0000>Your position change has been DECLINED: the database couldn't be written properly.</font><p>";
        echo "Please let the commissioner know something went wrong, or go back and try again.<p>";
        echo "<a href=\"javascript:history.go(-1)\">GO BACK</a>";
    }
}

?>
