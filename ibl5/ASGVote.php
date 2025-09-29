<?php

require 'mainfile.php';

Nuke\Header::header();

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

echo "
        Eastern Frontcourt: $ECF1<br>
        Eastern Frontcourt: $ECF2<br>
        Eastern Frontcourt: $ECF3<br>
        Eastern Frontcourt: $ECF4<br>
        <br>
        Eastern Backcourt: $ECB1<br>
        Eastern Backcourt: $ECB2<br>
        Eastern Backcourt: $ECB3<br>
        Eastern Backcourt: $ECB4<br>
        <br>
        Western Frontcourt: $WCF1<br>
        Western Frontcourt: $WCF2<br>
        Western Frontcourt: $WCF3<br>
        Western Frontcourt: $WCF4<br>
        <br>
        Western Backcourt: $WCB1<br>
        Western Backcourt: $WCB2<br>
        Western Backcourt: $WCB3<br>
        Western Backcourt: $WCB4<br>
        <br>";

$positions = [
    'WCF' => 'Western Frontcourt',
    'WCB' => 'Western Backcourt',
    'ECF' => 'Eastern Frontcourt',
    'ECB' => 'Eastern Backcourt'
];

// VOTING FOR OWN PLAYERS
foreach ($positions as $abbreviation => $label) {
    for ($i = 1; $i <= 4; $i++) {
        $varName = $abbreviation . $i;
        if (strpos($$varName, $Team_Name) !== false) {
            $court = (strpos($abbreviation, 'F') !== false) ? 'Frontcourt' : 'Backcourt';
            echo "Sorry, you cannot vote for your own player ($court: {$$varName}).<p>Please go back, unselect that player, select a different player not on your team, and try again.<br>";
            Nuke\Footer::footer();
            exit;
        }
    }
}

// MISSING VOTES
foreach ($positions as $abbreviation => $label) {
    for ($i = 1; $i <= 4; $i++) {
        $varName = $abbreviation . $i;
        if (empty($$varName)) {
            echo "Sorry, you selected less than FOUR $label Players.<p>Please go back, select FOUR players, and try again.<br>";
            Nuke\Footer::footer();
            exit;
        }
    }
}

// TOO MANY VOTES
foreach ($positions as $abbreviation => $label) {
    if (isset($_POST[$abbreviation]) && count($_POST[$abbreviation]) > 4) {
        echo "Oops, you've selected more than four $label Players.<p>Please go back, select FOUR players, and try again.";
        Nuke\Footer::footer();
        exit;
    }
}

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
    echo "</font><b>Thank you for voting - the $Team_Name vote has been recorded!</b><p>";

    $queryUpdateASGVoteSubmissionTime = "UPDATE ibl_team_history SET asg_vote = NOW() + INTERVAL 2 HOUR WHERE team_name = '$Team_Name'";
    $resultUpdateASGVoteSubmissionTime = $db->sql_query($queryUpdateASGVoteSubmissionTime);
}

Nuke\Footer::footer();
