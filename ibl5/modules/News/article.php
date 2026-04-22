<?php

declare(strict_types=1);

define('INDEX_FILE', true);
/************************************************************************/
/* PHP-NUKE: Web Portal System                                          */
/* ===========================                                          */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

/**
 * Article display page - refactored for security
 *
 * SECURITY NOTES:
 * - All database queries use prepared statements
 * - All user input is validated and cast to appropriate types
 * - Comment system removed (deprecated, see Task #4)
 */

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

global $db, $mysqli_db, $prefix, $user_prefix, $user, $multilingual, $currentlang, $anonymous, $articlecomm, $cookieusrtime;

$optionbox = "";
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

// Validate and sanitize $sid parameter
$sid = isset($sid) && is_numeric($sid) ? (int) $sid : 0;
$REQUEST_URI = $_SERVER['REQUEST_URI'] ?? '';

if (stristr($REQUEST_URI, "mainfile")) {
    Header("Location: modules.php?name=$module_name&file=article&sid=$sid");
    exit;
} elseif ($sid === 0 && !isset($teamid)) {
    Header("Location: index.php");
    exit;
}

// Handle user preference save (uses prepared statement)
// Legacy comment preference saving removed — comment system was deprecated

// Comment system removed - was deprecated and insecure
// The "Reply" operation and comments.php include have been removed

// Fetch article using prepared statement
$stmt = $mysqli_db->prepare(
    "SELECT catid, aid, time, title, hometext, bodytext, topic, informant, notes, acomm, haspoll, pollID
     FROM " . $prefix . "_stories
     WHERE sid = ?"
);
if ($stmt === false) {
    Header("Location: index.php");
    exit;
}

$stmt->bind_param('i', $sid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    $stmt->close();
    Header("Location: index.php");
    exit;
}

$row = $result->fetch_assoc();
$stmt->close();

$catid = (int) ($row['catid'] ?? 0);
$aaid = $row['aid'] ?? '';
$time = $row['time'] ?? '';
/** @var string $title */
$title = \Utilities\HtmlSanitizer::safeHtmlOutput($row['title'] ?? '');
$hometext = $row['hometext'] ?? '';
$bodytext = $row['bodytext'] ?? '';
$topic = (int) ($row['topic'] ?? 0);
$informant = $row['informant'] ?? '';
/** @var string $notes */
$notes = \Utilities\HtmlSanitizer::safeHtmlOutput($row['notes'] ?? '');
$acomm = (int) ($row['acomm'] ?? 0);
$haspoll = (int) ($row['haspoll'] ?? 0);
$pollID = (int) ($row['pollID'] ?? 0);

if (empty($aaid)) {
    Header("Location: modules.php?name=$module_name");
    exit;
}

// Update view counter using prepared statement
$stmtCounter = $mysqli_db->prepare("UPDATE " . $prefix . "_stories SET counter = counter + 1 WHERE sid = ?");
if ($stmtCounter !== false) {
    $stmtCounter->bind_param('i', $sid);
    $stmtCounter->execute();
    $stmtCounter->close();
}

$artpage = 1;
$pagetitle = "- $title";
PageLayout\PageLayout::header();
$artpage = 0;

if (!is_numeric($time)) {
    preg_match('/(\d{4})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})/', $time, $dtParts);
    $time = gmmktime((int) $dtParts[4], (int) $dtParts[5], (int) $dtParts[6], (int) $dtParts[2], (int) $dtParts[3], (int) $dtParts[1]);
}
$time -= date("Z");
$datetime = ucfirst(date(_DATESTRING, $time));
if (!empty($notes)) {
    $notes = "\n\n<b>" . _NOTE . "</b> <i>$notes</i>";
} else {
    $notes = "";
}

if (empty($bodytext)) {
    $bodytext = "$hometext$notes";
} else {
    $bodytext = "$hometext\n\n$bodytext$notes";
}

if (empty($informant)) {
    $informant = $anonymous;
}

$stmtTopics = $mysqli_db->prepare(
    "SELECT t.topicid, t.topicname, t.topicimage, t.topictext
     FROM {$prefix}_stories s
     LEFT JOIN {$prefix}_topics t ON t.topicid = s.topic
     WHERE s.sid = ?"
);
$stmtTopics->bind_param('i', $sid);
$stmtTopics->execute();
$topicRow = $stmtTopics->get_result()->fetch_assoc();
$stmtTopics->close();
$topicid = (int) ($topicRow['topicid'] ?? 0);
$topicname = \Utilities\HtmlSanitizer::e($topicRow['topicname'] ?? '');
$topicimage = \Utilities\HtmlSanitizer::e($topicRow['topicimage'] ?? '');
$topictext = \Utilities\HtmlSanitizer::e($topicRow['topictext'] ?? '');

// Fetch category using prepared statement if catid is set
if ($catid !== 0) {
    $stmtCat = $mysqli_db->prepare("SELECT title FROM " . $prefix . "_stories_cat WHERE catid = ?");
    if ($stmtCat !== false) {
        $stmtCat->bind_param('i', $catid);
        $stmtCat->execute();
        $catResult = $stmtCat->get_result();
        $row2 = $catResult->fetch_assoc();
        $stmtCat->close();

        if ($row2 !== null) {
            /** @var string $title1 */
            $title1 = \Utilities\HtmlSanitizer::safeHtmlOutput($row2['title'] ?? '');
            $title = "<a href=\"modules.php?name=$module_name&amp;file=categories&amp;op=newindex&amp;catid=$catid\"><font class=\"storycat\">$title1</font></a>: $title";
        }
    }
}

themearticle($aaid, $informant, $time, $title, $bodytext, $topic, $topicname, $topicimage, $topictext);

if ($multilingual == 1) {
    $querylang = "AND (blanguage='$currentlang' OR blanguage='')";
} else {
    $querylang = "";
}

cookiedecode($user);

// Comment system and associated topics removed — both were deprecated PHP-Nuke features

PageLayout\PageLayout::footer();
