<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

use Utilities\HtmlSanitizer;

Nuke\Header::header();

echo "<HTML><HEAD><TITLE>End of Year Voting Result</TITLE></HEAD><BODY>";

$Team_Name = $_POST['teamname'] ?? '';
$MVP1 = $_POST['MVP'][1] ?? '';
$MVP2 = $_POST['MVP'][2] ?? '';
$MVP3 = $_POST['MVP'][3] ?? '';
$Six1 = $_POST['Six'][1] ?? '';
$Six2 = $_POST['Six'][2] ?? '';
$Six3 = $_POST['Six'][3] ?? '';
$ROY1 = $_POST['ROY'][1] ?? '';
$ROY2 = $_POST['ROY'][2] ?? '';
$ROY3 = $_POST['ROY'][3] ?? '';
$GM1 = $_POST['GM'][1] ?? '';
$GM2 = $_POST['GM'][2] ?? '';
$GM3 = $_POST['GM'][3] ?? '';

echo "
    MVP Choice 1: " . HtmlSanitizer::safeHtmlOutput($MVP1) . "<br>
    MVP Choice 2: " . HtmlSanitizer::safeHtmlOutput($MVP2) . "<br>
    MVP Choice 3: " . HtmlSanitizer::safeHtmlOutput($MVP3) . "<br><br>
    6th Man Choice 1: " . HtmlSanitizer::safeHtmlOutput($Six1) . "<br>
    6th Man Choice 2: " . HtmlSanitizer::safeHtmlOutput($Six2) . "<br>
    6th Man Choice 3: " . HtmlSanitizer::safeHtmlOutput($Six3) . "<br><br>
    ROY Choice 1: " . HtmlSanitizer::safeHtmlOutput($ROY1) . "<br>
    ROY Choice 2: " . HtmlSanitizer::safeHtmlOutput($ROY2) . "<br>
    ROY Choice 3: " . HtmlSanitizer::safeHtmlOutput($ROY3) . "<br><br>
    GM Choice 1: " . HtmlSanitizer::safeHtmlOutput($GM1) . "<br>
    GM Choice 2: " . HtmlSanitizer::safeHtmlOutput($GM2) . "<br>
    GM Choice 3: " . HtmlSanitizer::safeHtmlOutput($GM3) . "<br><br>";

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
    echo "</font><strong style=\"font-weight: bold;\">Thank you for voting - the " . HtmlSanitizer::safeHtmlOutput($Team_Name) . " vote has been recorded!</strong>";

// ==== UPDATE SELECTED VOTES IN DATABASE ====

    $stmt = $mysqli_db->prepare("UPDATE ibl_votes_EOY
        SET MVP_1 = ?,
            MVP_2 = ?,
            MVP_3 = ?,
            Six_1 = ?,
            Six_2 = ?,
            Six_3 = ?,
            ROY_1 = ?,
            ROY_2 = ?,
            ROY_3 = ?,
            GM_1 = ?,
            GM_2 = ?,
            GM_3 = ?
        WHERE team_name = ?");

    $stmt->bind_param('sssssssssssss',
        $MVP1, $MVP2, $MVP3,
        $Six1, $Six2, $Six3,
        $ROY1, $ROY2, $ROY3,
        $GM1, $GM2, $GM3,
        $Team_Name
    );

    $stmt->execute();
    $stmt->close();

    $stmtUpdateTime = $mysqli_db->prepare("UPDATE ibl_team_history SET eoy_vote = NOW() + INTERVAL 2 HOUR WHERE team_name = ?");
    $stmtUpdateTime->bind_param('s', $Team_Name);
    $stmtUpdateTime->execute();
    $stmtUpdateTime->close();

}
Nuke\Footer::footer();
