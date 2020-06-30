<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");


include("header.php");

echo "<HTML><HEAD><TITLE>ASG Voting Result</TITLE></HEAD><BODY>";

$Team_Name = $_POST['teamname'];
$Pos = $_POST['pos'];
$ECC = $_POST['ECC'];
$ECF1 = $_POST['ECF1'];
$ECF2 = $_POST['ECF2'];
$ECG1 = $_POST['ECG1'];
$ECG2 = $_POST['ECG2'];
$WCC = $_POST['WCC'];
$WCF1 = $_POST['WCF1'];
$WCF2 = $_POST['WCF2'];
$WCG1 = $_POST['WCG1'];
$WCG2 = $_POST['WCG2'];


if ($ECC == "") {
echo "Sorry, you must select an Eastern Conference Center. Try again.<br>";
}
else if ($ECF1 == "") {
echo "Sorry, you must select an Eastern Conference Forward. Try again.<br>";
}
else if ($ECF2 == "") {
echo "Sorry, you must select an Eastern Conference Forward. Try again.<br>";
}
else if ($ECG1 == "") {
echo "Sorry, you must select an Eastern Conference Guard. Try again.<br>";
}
else if ($ECG2 == "") {
echo "Sorry, you must select an Eastern Conference Guard. Try again.<br>";
}
else if ($WCC == "") {
echo "Sorry, you must select a Western Conference Center. Try again.<br>";
}
else if ($WCF1 == "") {
echo "Sorry, you must select a Western Conference Forward. Try again.<br>";
}
else if ($WCF2 == "") {
echo "Sorry, you must select a Western Conference Forward. Try again.<br>";
}
else if ($WCG1 == "") {
echo "Sorry, you must select a Western Conference Guard. Try again.<br>";
}
else if ($WCG2 == "") {
echo "Sorry, you must select a Western Conference Guard. Try again.<br>";
}
else if ($ECF1 == $ECF2) {
echo "Sorry, you have selected the same player for both Eastern Conference Forward slots. Try again.<br>";
}
else if ($ECG1 == $ECG2) {
echo "Sorry, you have selected the same player for both Eastern Conference Guard slots. Try again.<br>";
}
else if ($WCF1 == $WCF2) {
echo "Sorry, you have selected the same player for both Western Conference Forward slots. Try again.<br>";
}
else if ($WCG1 == $WCG2) {
echo "Sorry, you have selected the same player for both Western Conference Guard slots. Try again.<br>";
}
else {

echo "The $Team_Name vote has been recorded.</br><br>

Eastern Center: $ECC<br>
Eastern Forward: $ECF1<br>
Eastern Forward: $ECF2<br>
Eastern Guard: $ECG1<br>
Eastern Guard: $ECG2<br><br>
Western Center: $WCC<br>
Western Forward: $WCF1<br>
Western Forward: $WCF2<br>
Western Guard: $WCG1<br>
Western Guard: $WCG2<br><br>


";


// ==== UPDATE SELECTED VOTES IN DATABASE ====

$query1="UPDATE nuke_asg_votes SET East_C = '$ECC' WHERE team_name = '$Team_Name'";
$result1=mysql_query($query1);

$query2="UPDATE nuke_asg_votes SET East_F1 = '$ECF1' WHERE team_name = '$Team_Name'";
$result2=mysql_query($query2);

$query3="UPDATE nuke_asg_votes SET East_F2 = '$ECF2' WHERE team_name = '$Team_Name'";
$result3=mysql_query($query3);

$query4="UPDATE nuke_asg_votes SET East_G1 = '$ECG1' WHERE team_name = '$Team_Name'";
$result4=mysql_query($query4);

$query5="UPDATE nuke_asg_votes SET East_G2 = '$ECG2' WHERE team_name = '$Team_Name'";
$result5=mysql_query($query5);

$query6="UPDATE nuke_asg_votes SET West_C = '$WCC' WHERE team_name = '$Team_Name'";
$result6=mysql_query($query6);

$query7="UPDATE nuke_asg_votes SET West_F1 = '$WCF1' WHERE team_name = '$Team_Name'";
$result7=mysql_query($query7);

$query8="UPDATE nuke_asg_votes SET West_F2 = '$WCF2' WHERE team_name = '$Team_Name'";
$result8=mysql_query($query8);

$query9="UPDATE nuke_asg_votes SET West_G1 = '$WCG1' WHERE team_name = '$Team_Name'";
$result9=mysql_query($query9);

$query10="UPDATE nuke_asg_votes SET West_G2 = '$WCG2' WHERE team_name = '$Team_Name'";
$result10=mysql_query($query10);

$query11="UPDATE ibl_team_history SET asg_vote = NOW() + INTERVAL 2 HOUR WHERE team_name = '$Team_Name'";
$result11=mysql_query($query11);
}

include("footer.php");

?>
