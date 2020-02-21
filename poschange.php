<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

echo "<HTML><HEAD><TITLE>Position Change Result</TITLE></HEAD><BODY>";

$Team_Name = $_POST['teamname'];
$Player_Name = $_POST['playername'];
$Player_Pos = $_POST['playerpos'];
$Pos = $_POST['pos'];

$acceptablePositions = array('PG', 'G', 'SG', 'GF', 'SF', 'F', 'PF', 'FC', 'C');

if (in_array($Pos, $acceptablePositions)) {
    echo "Message from the commissioner's office:<br><font color=#0000cc>Your position change has been submitted and approved. $Player_Name is now a $Pos.</font></br>";
    echo "<a href=\"javascript:history.go(-1)\">GO BACK</a>";

    $timestamp=date('Y-m-d H:i:s',time());

    $querytopic="SELECT * FROM nuke_topics WHERE topicname = '$Team_Name'";
    $resulttopic=mysql_query($querytopic);
    $topicid=mysql_result($resulttopic,0,"topicid");

    $querycat="SELECT * FROM nuke_stories_cat WHERE title = 'Position Changes'";
    $resultcat=mysql_query($querycat);
    $PositionChanges=mysql_result($resultcat,0,"counter");
    $catid=mysql_result($resultcat,0,"catid");

    $PositionChanges=$PositionChanges+1;

    // ==== PUT ANNOUNCEMENT INTO DATABASE ON NEWS PAGE

    $storytitle=$Player_Name." changes his position with the ".$Team_Name;

    $hometext=$Player_Name." today changed his position with the ".$Team_Name." from " .$Player_Pos. " to " .$Pos. ".";

    $querycat2="UPDATE nuke_stories_cat SET counter = $PositionChanges WHERE title = 'Position Changes'";
    $resultcat2=mysql_query($querycat2);

    $querystor="INSERT INTO nuke_stories (catid,aid,title,time,hometext,topic,informant,counter,alanguage) VALUES ('$catid','Associated Press','$storytitle','$timestamp','$hometext','$topicid','Associated Press','0','english')";
    $resultstor=mysql_query($querystor);

    // ==== UPDATE POSITION CHANGES USED AND ALT POSITION IN DATABASE ====

    $queryseason="UPDATE nuke_ibl_team_info SET poschanges = poschanges + 1 WHERE team_name = '$Team_Name'";
    $resultseason=mysql_query($queryseason);

    $queryseason1="UPDATE nuke_iblplyr SET altpos = '$Pos' WHERE name = '$Player_Name'";
    $resultseason1=mysql_query($queryseason1);

    $queryseason2="UPDATE nuke_iblplyr SET poschange = '1' WHERE name = '$Player_Name'";
    $resultseason2=mysql_query($queryseason2);
} else {
    echo "Message from the commissioner's office: <font color=#FF0000>Your position change has been DECLINED: that was not a valid position.</font><p>";
    echo "<a href=\"javascript:history.go(-1)\">GO BACK</a>";
}

?>
