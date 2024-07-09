<?php

require 'mainfile.php';

NukeHeader::header();

echo "<HTML><HEAD><TITLE>ASG Voting Result</TITLE></HEAD><BODY>";

$Team_Name = $_POST['teamname'];
$ECF1 = $_POST['ECF'][0];
$ECF2 = $_POST['ECF'][1];
$ECF3 = $_POST['ECF'][2];
$ECF4 = $_POST['ECF'][3];
$ECB1 = $_POST['ECB'][0];
$ECB2 = $_POST['ECB'][1];
$ECB3 = $_POST['ECB'][2];
$ECB4 = $_POST['ECB'][3];
$WCF1 = $_POST['WCF'][0];
$WCF2 = $_POST['WCF'][1];
$WCF3 = $_POST['WCF'][2];
$WCF4 = $_POST['WCF'][3];
$WCB1 = $_POST['WCB'][0];
$WCB2 = $_POST['WCB'][1];
$WCB3 = $_POST['WCB'][2];
$WCB4 = $_POST['WCB'][3];

// VOTING FOR OWN PLAYERS
if (strpos($WCF1, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $WCF1).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($WCF2, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $WCF2).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($WCF3, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $WCF3).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($WCF4, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $WCF4).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($WCB1, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Backcourt: $WCB1).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($WCB2, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Backcourt: $WCB2).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($WCB3, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Backcourt: $WCB3).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($WCB4, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Backcourt: $WCB4).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($ECF1, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $ECF1).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($ECF2, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $ECF2).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($ECF3, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $ECF3).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($ECF4, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Frontcourt: $ECF4).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($ECB1, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Backcourt: $ECB1).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($ECB2, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Backcourt: $ECB2).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($ECB3, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Backcourt: $ECB3).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
} else if (strpos($ECB4, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player (Backcourt: $ECB4).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
}

// MISSING VOTES
else if ($ECF1 == "" OR $ECF2 == "" OR $ECF3 == "" OR $ECF4 == "") {
    echo "Sorry, you selected less than FOUR Eastern Conference Frontcourt Players.<p>Please go back, select FOUR players, and try again.<br>";
} else if ($ECB1 == "" OR $ECB2 == "" OR $ECB3 == "" OR $ECB4 == "") {
    echo "Sorry, you selected less than FOUR Eastern Conference Backcourt Players.<p>Please go back, select FOUR players, and try again.<br>";
} else if ($WCF1 == "" OR $WCF2 == "" OR $WCF3 == "" OR $WCF4 == "") {
    echo "Sorry, you selected less than FOUR Western Conference Frontcourt Players.<p>Please go back, select FOUR players, and try again.<br>";
} else if ($WCB1 == "" OR $WCB2 == "" OR $WCB3 == "" OR $WCB4 == "") {
    echo "Sorry, you selected less than FOUR Western Conference Backcourt Players.<p>Please go back, select FOUR players, and try again.<br>";
}

// TOO MANY VOTES
else if (count($_POST['ECF']) > 4) {
    echo "Oops, you've selected more than four Eastern Conference Frontcourt Players.<p>Please go back, select FOUR players, and try again.";
} else if (count($_POST['ECB']) > 4) {
    echo "Oops, you've selected more than four Eastern Conference Backcourt Players.<p>Please go back, select FOUR players, and try again.";
} else if (count($_POST['WCF']) > 4) {
    echo "Oops, you've selected more than four Western Conference Frontcourt Players.<p>Please go back, select FOUR players, and try again.";
} else if (count($_POST['WCB']) > 4) {
    echo "Oops, you've selected more than four Western Conference Backcourt Players.<p>Please go back, select FOUR players, and try again.";
} else {
    $queryUpdateVotes = "UPDATE ibl_votes_ASG 
    SET East_F1 = '$ECF1',
        East_F2 = '$ECF2',
        East_F3 = '$ECF3',
        East_F4 = '$ECF4',
        East_B1 = '$ECB1',
        East_B2 = '$ECB2',
        East_B3 = '$ECB3',
        East_B4 = '$ECB4',
        West_F1 = '$WCF1',
        West_F2 = '$WCF2',
        West_F3 = '$WCF3',
        West_F4 = '$WCF4',
        West_B1 = '$WCB1',
        West_B2 = '$WCB2',
        West_B3 = '$WCB3',
        West_B4 = '$WCB4'
    WHERE team_name = '$Team_Name'";

    if ($db->sql_query($queryUpdateVotes)) {
        echo "The $Team_Name vote has been recorded.<p>

        Eastern Frontcourt Player: $ECF1<br>
        Eastern Frontcourt Player: $ECF2<br>
        Eastern Frontcourt Player: $ECF3<br>
        Eastern Frontcourt Player: $ECF4<br>
        <br>
        Eastern Backcourt Player: $ECB1<br>
        Eastern Backcourt Player: $ECB2<br>
        Eastern Backcourt Player: $ECB3<br>
        Eastern Backcourt Player: $ECB4<br>
        <br>
        Western Frontcourt Player: $WCF1<br>
        Western Frontcourt Player: $WCF2<br>
        Western Frontcourt Player: $WCF3<br>
        Western Frontcourt Player: $WCF4<br>
        <br>
        Western Backcourt Player: $WCB1<br>
        Western Backcourt Player: $WCB2<br>
        Western Backcourt Player: $WCB3<br>
        Western Backcourt Player: $WCB4";

        $queryUpdateASGVoteSubmissionTime = "UPDATE ibl_team_history SET asg_vote = NOW() + INTERVAL 2 HOUR WHERE team_name = '$Team_Name'";
        $resultUpdateASGVoteSubmissionTime = $db->sql_query($queryUpdateASGVoteSubmissionTime);
    }
}

include "footer.php";
