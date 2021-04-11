<?php

require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");

include("header.php");

echo "<HTML><HEAD><TITLE>ASG Voting Result</TITLE></HEAD><BODY>";

$Team_Name = $_POST['teamname'];
$ECF1 = $_POST['ECF'][0];
$ECF2 = $_POST['ECF'][1];
$ECF3 = $_POST['ECF'][2];
$ECB1 = $_POST['ECB'][0];
$ECB2 = $_POST['ECB'][1];
$WCF1 = $_POST['WCF'][0];
$WCF2 = $_POST['WCF'][1];
$WCF3 = $_POST['WCF'][2];
$WCB1 = $_POST['WCB'][0];
$WCB2 = $_POST['WCB'][1];

// VOTING FOR OWN PLAYERS
if (strpos($WCF1, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $WCF1). Try again.<br>";
}
else if (strpos($WCF2, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $WCF2). Try again.<br>";
}
else if (strpos($WCF3, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $WCF3). Try again.<br>";
}
else if (strpos($WCB1, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Backcourt: $WCB1). Try again.<br>";
}
else if (strpos($WCB2, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Backcourt: $WCB2). Try again.<br>";
}
else if (strpos($ECF1, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $ECF1). Try again.<br>";
}
else if (strpos($ECF2, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $ECF2). Try again.<br>";
}
else if (strpos($ECF3, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $ECF3). Try again.<br>";
}
else if (strpos($ECB1, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Backcourt: $ECB1). Try again.<br>";
}
else if (strpos($ECB2, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Backcourt: $ECB2). Try again.<br>";
}

// MISSING VOTES
else if ($ECF1 == "" OR $ECF2 == "" OR $ECF3 == "") {
    echo "Sorry, you must select THREE Eastern Conference Frontcourt Players. Try again.<br>";
}
else if ($ECB1 == "" OR $ECB2 == "") {
    echo "Sorry, you must select TWO Eastern Conference Backcourt Players. Try again.<br>";
}
else if ($WCF1 == "" OR $WCF2 == "" OR $WCF3 == "") {
    echo "Sorry, you must select THREE Western Conference Frontcourt Players. Try again.<br>";
}
else if ($WCB1 == "" OR $WCB2 == "") {
    echo "Sorry, you must select TWO Western Conference Backcourt Players. Try again.<br>";
}

// DUPLICATE VOTES
else if ($ECF1 == $ECF2 OR $ECF1 == $ECF3 OR $ECF2 == $ECF3) {
    echo "Oops, you selected the same Eastern Conference Frontcourt Player more than once. Try again.<br>";
}
else if ($ECB1 == $ECB2) {
    echo "Oops, you selected the same Eastern Conference Backcourt Player more than once. Try again.<br>";
}
else if ($WCF1 == $WCF2 OR $WCF1 == $WCF3 OR $WCF2 == $WCF3) {
    echo "Oops, you selected the same Western Conference Frontcourt Player more than once. Try again.<br>";
}
else if ($WCB1 == $WCB2) {
    echo "Oops, you selected the same Western Conference Backcourt Player more than once. Try again.<br>";
}

// TOO MANY VOTES
else if (count($_POST['ECF']) > 3) {
    echo "Oops, you've selected more than three Eastern Conference Frontcourt Players. Please go back and only select THREE.";
}
else if (count($_POST['ECB']) > 2) {
    echo "Oops, you've selected more than two Eastern Conference Backcourt Players. Please go back and only select TWO.";
}
else if (count($_POST['WCF']) > 3) {
    echo "Oops, you've selected more than three Western Conference Frontcourt Players. Please go back and only select THREE.";
}
else if (count($_POST['WCB']) > 2) {
    echo "Oops, you've selected more than two Western Conference Backcourt Players. Please go back and only select TWO.";
}

else {
    echo "The $Team_Name vote has been recorded.</br><br>

    Eastern Frontcourt Player: $ECF1<br>
    Eastern Frontcourt Player: $ECF2<br>
    Eastern Frontcourt Player: $ECF3<br>
    Eastern Backcourt Player: $ECB1<br>
    Eastern Backcourt Player: $ECB2<br>
    <br>
    Western Frontcourt Player: $WCF1<br>
    Western Frontcourt Player: $WCF2<br>
    Western Frontcourt Player: $WCF3<br>
    Western Backcourt Player: $WCB1<br>
    Western Backcourt Player: $WCB2";

    // ==== UPDATE SELECTED VOTES IN DATABASE ====

    $query1 = "UPDATE IBL_ASG_Votes SET East_C = '$ECF3' WHERE team_name = '$Team_Name'"; //TODO: rename these database columns to match
    $result1 = mysql_query($query1);

    $query2 = "UPDATE IBL_ASG_Votes SET East_F1 = '$ECF1' WHERE team_name = '$Team_Name'";
    $result2 = mysql_query($query2);

    $query3 = "UPDATE IBL_ASG_Votes SET East_F2 = '$ECF2' WHERE team_name = '$Team_Name'";
    $result3 = mysql_query($query3);

    $query4 = "UPDATE IBL_ASG_Votes SET East_G1 = '$ECB1' WHERE team_name = '$Team_Name'";
    $result4 = mysql_query($query4);

    $query5 = "UPDATE IBL_ASG_Votes SET East_G2 = '$ECB2' WHERE team_name = '$Team_Name'";
    $result5 = mysql_query($query5);

    $query6 = "UPDATE IBL_ASG_Votes SET West_C = '$WCF3' WHERE team_name = '$Team_Name'";
    $result6 = mysql_query($query6);

    $query7 = "UPDATE IBL_ASG_Votes SET West_F1 = '$WCF1' WHERE team_name = '$Team_Name'";
    $result7 = mysql_query($query7);

    $query8 = "UPDATE IBL_ASG_Votes SET West_F2 = '$WCF2' WHERE team_name = '$Team_Name'";
    $result8 = mysql_query($query8);

    $query9 = "UPDATE IBL_ASG_Votes SET West_G1 = '$WCB1' WHERE team_name = '$Team_Name'";
    $result9 = mysql_query($query9);

    $query10 = "UPDATE IBL_ASG_Votes SET West_G2 = '$WCB2' WHERE team_name = '$Team_Name'";
    $result10 = mysql_query($query10);

    $query11 = "UPDATE ibl_team_history SET asg_vote = NOW() + INTERVAL 2 HOUR WHERE team_name = '$Team_Name'";
    $result11 = mysql_query($query11);
}

include("footer.php");

?>
