<?php

if (!strpos($_SERVER['PHP_SELF'], 'admin.php')) {
    #show right panel:
    define('INDEX_FILE', true);
}
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
} elseif ($sid === 0 && !isset($tid)) {
    Header("Location: index.php");
    exit;
}

// Handle user preference save (uses prepared statement)
$save = isset($save) ? (bool) $save : false;
if ($save && is_user($user)) {
    cookiedecode($user);
    getusrinfo($user);

    // Cast all user input to safe types
    $mode = isset($mode) && is_string($mode) ? substr($mode, 0, 20) : ($userinfo['umode'] ?? 'flat');
    $order = isset($order) && is_numeric($order) ? (int) $order : ($userinfo['uorder'] ?? 0);
    $thold = isset($thold) && is_numeric($thold) ? (int) $thold : ($userinfo['thold'] ?? 0);
    $userId = isset($cookie[0]) && is_numeric($cookie[0]) ? (int) $cookie[0] : 0;

    // Whitelist valid mode values
    $validModes = ['flat', 'nested', 'nocomments', 'thread'];
    if (!in_array($mode, $validModes, true)) {
        $mode = 'flat';
    }

    // Use prepared statement for user preference update
    if ($userId > 0) {
        $stmt = $mysqli_db->prepare(
            "UPDATE " . $user_prefix . "_users SET umode = ?, uorder = ?, thold = ? WHERE uid = ?"
        );
        if ($stmt !== false) {
            $stmt->bind_param('siii', $mode, $order, $thold, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    getusrinfo($user);
}

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

formatTimestamp($time);
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

getTopics($sid);

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
include "modules/$module_name/associates.php";

// Comment system removed - deprecated functionality
// The old comments.php was a security vulnerability (SQL injection)
// Comments can be re-implemented with proper security if needed

PageLayout\PageLayout::footer();
