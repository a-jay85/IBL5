<?php

require '../../mainfile.php';

Nuke\Header::header();

echo "<HTML><HEAD><TITLE>End of Year Voting Result</TITLE></HEAD><BODY>";

$Team_Name = $_POST['teamname'];
$MVP1 = $_POST['MVP'][1];
$MVP2 = $_POST['MVP'][2];
$MVP3 = $_POST['MVP'][3];
$Six1 = $_POST['Six'][1];
$Six2 = $_POST['Six'][2];
$Six3 = $_POST['Six'][3];
$ROY1 = $_POST['ROY'][1];
$ROY2 = $_POST['ROY'][2];
$ROY3 = $_POST['ROY'][3];
$GM1 = $_POST['GM'][1];
$GM2 = $_POST['GM'][2];
$GM3 = $_POST['GM'][3];

echo "
    MVP Choice 1: $MVP1<br>
    MVP Choice 2: $MVP2<br>
    MVP Choice 3: $MVP3<br><br>
    6th Man Choice 1: $Six1<br>
    6th Man Choice 2: $Six2<br>
    6th Man Choice 3: $Six3<br><br>
    ROY Choice 1: $ROY1<br>
    ROY Choice 2: $ROY2<br>
    ROY Choice 3: $ROY3<br><br>
    GM Choice 1: $GM1<br>
    GM Choice 2: $GM2<br>
    GM Choice 3: $GM3<br><br>";

echo "<font color=red>";
if (strpos($MVP1, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player. Try again.<br>";
} else if (strpos($MVP2, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player. Try again.<br>";
} else if (strpos($MVP3, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player. Try again.<br>";
} else if (strpos($Six1, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player. Try again.<br>";
} else if (strpos($Six2, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player. Try again.<br>";
} else if (strpos($Six3, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player. Try again.<br>";
} else if (strpos($ROY1, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player. Try again.<br>";
} else if (strpos($ROY2, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player. Try again.<br>";
} else if (strpos($ROY3, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for your own player. Try again.<br>";
} else if (strpos($GM1, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for yourself. Try again.<br>";
} else if (strpos($GM2, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for yourself. Try again.<br>";
} else if (strpos($GM3, $Team_Name) !== false) {
    echo "Sorry, you cannot vote for yourself. Try again.<br>";
} else if ($MVP1 == "") {
    echo "Sorry, you must select an MVP. Try again.<br>";
} else if ($MVP2 == "") {
    echo "Sorry, you must select an MVP. Try again.<br>";
} else if ($MVP3 == "") {
    echo "Sorry, you must select an MVP. Try again.<br>";
} else if ($Six1 == "") {
    echo "Sorry, you must select a 6th Man of the Year. Try again.<br>";
} else if ($Six2 == "") {
    echo "Sorry, you must select a 6th Man of the Year. Try again.<br>";
} else if ($Six3 == "") {
    echo "Sorry, you must select a 6th Man of the Year . Try again.<br>";
} else if ($ROY1 == "") {
    echo "Sorry, you must select a Rookie of the Year. Try again.<br>";
} else if ($ROY2 == "") {
    echo "Sorry, you must select a Rookie of the Year. Try again.<br>";
} else if ($ROY3 == "") {
    echo "Sorry, you must select a Rookie of the Year. Try again.<br>";
} else if ($GM1 == "") {
    echo "Sorry, you must select a GM of the Year. Try again.<br>";
} else if ($GM2 == "") {
    echo "Sorry, you must select a GM of the Year. Try again.<br>";
} else if ($GM3 == "") {
    echo "Sorry, you must select a GM of the Year. Try again.<br>";
} else if ($MVP1 == $MVP2) {
    echo "Sorry, you have selected the same player for multiple MVP slots. Try again.<br>";
} else if ($MVP1 == $MVP3) {
    echo "Sorry, you have selected the same player for multiple MVP slots. Try again.<br>";
} else if ($MVP2 == $MVP3) {
    echo "Sorry, you have selected the same player for multiple MVP slots. Try again.<br>";
} else if ($Six1 == $Six2) {
    echo "Sorry, you have selected the same player for multiple Sixth Man of the Year slots. Try again.<br>";
} else if ($Six1 == $Six3) {
    echo "Sorry, you have selected the same player for multiple Sixth Man of the Year slots. Try again.<br>";
} else if ($Six2 == $Six3) {
    echo "Sorry, you have selected the same player for multiple Sixth Man of the Year slots. Try again.<br>";
} else if ($ROY1 == $ROY2) {
    echo "Sorry, you have selected the same player for multiple Rookie of the Year slots. Try again.<br>";
} else if ($ROY1 == $ROY3) {
    echo "Sorry, you have selected the same player for multiple Rookie of the Year slots. Try again.<br>";
} else if ($ROY2 == $ROY3) {
    echo "Sorry, you have selected the same player for multiple Rookie of the Year slots. Try again.<br>";
} else if ($GM1 == $GM2) {
    echo "Sorry, you have selected the same player for multiple GM of the Year slots. Try again.<br>";
} else if ($GM1 == $GM3) {
    echo "Sorry, you have selected the same player for multiple GM of the Year slots. Try again.<br>";
} else if ($GM2 == $GM3) {
    echo "Sorry, you have selected the same player for multiple GM of the Year slots. Try again.<br>";
} else {
    echo "</font><b>Thank you for voting - the $Team_Name vote has been recorded!<b>";

// ==== UPDATE SELECTED VOTES IN DATABASE ====

    $query1 = "UPDATE ibl_votes_EOY SET MVP_1 = '$MVP1' WHERE team_name = '$Team_Name'";
    $result1 = $db->sql_query($query1);

    $query2 = "UPDATE ibl_votes_EOY SET MVP_2 = '$MVP2' WHERE team_name = '$Team_Name'";
    $result2 = $db->sql_query($query2);

    $query3 = "UPDATE ibl_votes_EOY SET MVP_3 = '$MVP3' WHERE team_name = '$Team_Name'";
    $result3 = $db->sql_query($query3);

    $query4 = "UPDATE ibl_votes_EOY SET Six_1 = '$Six1' WHERE team_name = '$Team_Name'";
    $result4 = $db->sql_query($query4);

    $query5 = "UPDATE ibl_votes_EOY SET Six_2 = '$Six2' WHERE team_name = '$Team_Name'";
    $result5 = $db->sql_query($query5);

    $query6 = "UPDATE ibl_votes_EOY SET Six_3 = '$Six3' WHERE team_name = '$Team_Name'";
    $result6 = $db->sql_query($query6);

    $query7 = "UPDATE ibl_votes_EOY SET ROY_1 = '$ROY1' WHERE team_name = '$Team_Name'";
    $result7 = $db->sql_query($query7);

    $query8 = "UPDATE ibl_votes_EOY SET ROY_2 = '$ROY2' WHERE team_name = '$Team_Name'";
    $result8 = $db->sql_query($query8);

    $query9 = "UPDATE ibl_votes_EOY SET ROY_3 = '$ROY3' WHERE team_name = '$Team_Name'";
    $result9 = $db->sql_query($query9);

    $query10 = "UPDATE ibl_votes_EOY SET GM_1 = '$GM1' WHERE team_name = '$Team_Name'";
    $result10 = $db->sql_query($query10);

    $query11 = "UPDATE ibl_votes_EOY SET GM_2 = '$GM2' WHERE team_name = '$Team_Name'";
    $result11 = $db->sql_query($query11);

    $query12 = "UPDATE ibl_votes_EOY SET GM_3 = '$GM3' WHERE team_name = '$Team_Name'";
    $result12 = $db->sql_query($query12);

    $query13 = "UPDATE ibl_team_history SET eoy_vote = NOW() + INTERVAL 2 HOUR WHERE team_name = '$Team_Name'";
    $result13 = $db->sql_query($query13);

}
Nuke\Footer::footer();
