<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

use Utilities\HtmlSanitizer;

PageLayout\PageLayout::header();

echo "<HTML><HEAD><TITLE>ASG Voting Result</TITLE></HEAD><BODY>";

$Team_Name = $_POST['teamname'] ?? '';
$ECF1 = $_POST['ECF'][0] ?? '';
$ECF2 = $_POST['ECF'][1] ?? '';
$ECF3 = $_POST['ECF'][2] ?? '';
$ECF4 = $_POST['ECF'][3] ?? '';
$ECB1 = $_POST['ECB'][0] ?? '';
$ECB2 = $_POST['ECB'][1] ?? '';
$ECB3 = $_POST['ECB'][2] ?? '';
$ECB4 = $_POST['ECB'][3] ?? '';
$WCF1 = $_POST['WCF'][0] ?? '';
$WCF2 = $_POST['WCF'][1] ?? '';
$WCF3 = $_POST['WCF'][2] ?? '';
$WCF4 = $_POST['WCF'][3] ?? '';
$WCB1 = $_POST['WCB'][0] ?? '';
$WCB2 = $_POST['WCB'][1] ?? '';
$WCB3 = $_POST['WCB'][2] ?? '';
$WCB4 = $_POST['WCB'][3] ?? '';

echo "
        Eastern Frontcourt: " . HtmlSanitizer::safeHtmlOutput($ECF1) . "<br>
        Eastern Frontcourt: " . HtmlSanitizer::safeHtmlOutput($ECF2) . "<br>
        Eastern Frontcourt: " . HtmlSanitizer::safeHtmlOutput($ECF3) . "<br>
        Eastern Frontcourt: " . HtmlSanitizer::safeHtmlOutput($ECF4) . "<br>
        <br>
        Eastern Backcourt: " . HtmlSanitizer::safeHtmlOutput($ECB1) . "<br>
        Eastern Backcourt: " . HtmlSanitizer::safeHtmlOutput($ECB2) . "<br>
        Eastern Backcourt: " . HtmlSanitizer::safeHtmlOutput($ECB3) . "<br>
        Eastern Backcourt: " . HtmlSanitizer::safeHtmlOutput($ECB4) . "<br>
        <br>
        Western Frontcourt: " . HtmlSanitizer::safeHtmlOutput($WCF1) . "<br>
        Western Frontcourt: " . HtmlSanitizer::safeHtmlOutput($WCF2) . "<br>
        Western Frontcourt: " . HtmlSanitizer::safeHtmlOutput($WCF3) . "<br>
        Western Frontcourt: " . HtmlSanitizer::safeHtmlOutput($WCF4) . "<br>
        <br>
        Western Backcourt: " . HtmlSanitizer::safeHtmlOutput($WCB1) . "<br>
        Western Backcourt: " . HtmlSanitizer::safeHtmlOutput($WCB2) . "<br>
        Western Backcourt: " . HtmlSanitizer::safeHtmlOutput($WCB3) . "<br>
        Western Backcourt: " . HtmlSanitizer::safeHtmlOutput($WCB4) . "<br>
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
            echo "<font color='red'>Sorry, you cannot vote for your own player ($court: " . HtmlSanitizer::safeHtmlOutput($$varName) . ").<p>Please go back, unselect that player, select a different player not on your team, and try again.<br></font>";
            PageLayout\PageLayout::footer();
            exit;
        }
    }
}

// MISSING VOTES
foreach ($positions as $abbreviation => $label) {
    for ($i = 1; $i <= 4; $i++) {
        $varName = $abbreviation . $i;
        if (empty($$varName)) {
            echo "<font color='red'>Sorry, you selected less than FOUR $label players.<p>Please go back, select FOUR players, and try again.<br></font>";
            PageLayout\PageLayout::footer();
            exit;
        }
    }
}

// TOO MANY VOTES
foreach ($positions as $abbreviation => $label) {
    if (isset($_POST[$abbreviation]) && count($_POST[$abbreviation]) > 4) {
        echo "<font color='red'>Sorry, you selected more than four $label players.<p>Please go back, select FOUR players, and try again.</font>";
        PageLayout\PageLayout::footer();
        exit;
    }
}

$stmt = $mysqli_db->prepare("UPDATE ibl_votes_ASG
SET East_F1 = ?,
    East_F2 = ?,
    East_F3 = ?,
    East_F4 = ?,
    East_B1 = ?,
    East_B2 = ?,
    East_B3 = ?,
    East_B4 = ?,
    West_F1 = ?,
    West_F2 = ?,
    West_F3 = ?,
    West_F4 = ?,
    West_B1 = ?,
    West_B2 = ?,
    West_B3 = ?,
    West_B4 = ?
WHERE team_name = ?");

$stmt->bind_param('sssssssssssssssss',
    $ECF1, $ECF2, $ECF3, $ECF4,
    $ECB1, $ECB2, $ECB3, $ECB4,
    $WCF1, $WCF2, $WCF3, $WCF4,
    $WCB1, $WCB2, $WCB3, $WCB4,
    $Team_Name
);

if ($stmt->execute()) {
    echo "</font><strong style=\"font-weight: bold;\">Thank you for voting - the " . HtmlSanitizer::safeHtmlOutput($Team_Name) . " vote has been recorded!</strong><p>";

    $stmt->close();

    $stmtUpdateTime = $mysqli_db->prepare("UPDATE ibl_team_info SET asg_vote = NOW() + INTERVAL 2 HOUR WHERE team_name = ?");
    $stmtUpdateTime->bind_param('s', $Team_Name);
    $stmtUpdateTime->execute();
    $stmtUpdateTime->close();
} else {
    echo "There was an error recording your vote. Please contact the IBL Commissioner.<br>";
    $stmt->close();
}

PageLayout\PageLayout::footer();
