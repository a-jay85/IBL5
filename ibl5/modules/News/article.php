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

// English UI strings defined in this file.
if (!defined('_DATESTRING')) {
    define('_DATESTRING', 'l, F d @ H:i:s T');
}
if (!defined('_NOTE')) {
    define('_NOTE', 'Note:');
}

assert($mysqli_db instanceof \mysqli);
$nukeCompat = new \Utilities\NukeCompat();

$optionbox = "";
$module_name = basename(dirname(__FILE__));

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$sid    = is_numeric($_REQUEST['sid']    ?? null) ? (int) $_REQUEST['sid']    : 0;
$teamid = is_numeric($_REQUEST['teamid'] ?? null) ? (int) $_REQUEST['teamid'] : null;

$newsService = new \Topics\News\NewsService($mysqli_db);

$REQUEST_URI = is_string($_SERVER['REQUEST_URI'] ?? null) ? $_SERVER['REQUEST_URI'] : '';

if (stristr($REQUEST_URI, "mainfile") !== false) {
    header("Location: modules.php?name=$module_name&file=article&sid=$sid");
    exit;
} elseif ($sid === 0 && $teamid === null) {
    header("Location: index.php");
    exit;
}

// Handle user preference save (uses prepared statement)
// Legacy comment preference saving removed — comment system was deprecated

// Comment system removed - was deprecated and insecure
// The "Reply" operation and comments.php include have been removed

// Fetch article via the News service (prepared statement inside the repository).
// Preserves both legacy redirects (prepare-false + num_rows !== 1) — a missing
// single row returns null here and redirects to index.php.
$row = $newsService->getStory($sid);
if ($row === null) {
    header("Location: index.php");
    exit;
}

$catid = is_scalar($row['catid'] ?? null) ? (int) $row['catid'] : 0;
/** @var string $aaid nuke_stories.aid is NOT NULL varchar */
$aaid = is_string($row['aid'] ?? null) ? $row['aid'] : '';
$time = is_int($row['time'] ?? null) || is_string($row['time'] ?? null) ? $row['time'] : '';
/** @var string $title */
$title = \Security\HtmlSanitizer::safeHtmlOutput($row['title'] ?? '');
$hometext = is_string($row['hometext'] ?? null) ? $row['hometext'] : '';
$bodytext = is_string($row['bodytext'] ?? null) ? $row['bodytext'] : '';
$topic = is_scalar($row['topic'] ?? null) ? (int) $row['topic'] : 0;
/** @var string $informant nuke_stories.informant is NOT NULL varchar */
$informant = is_string($row['informant'] ?? null) ? $row['informant'] : '';
/** @var string $notes */
$notes = \Security\HtmlSanitizer::safeHtmlOutput($row['notes'] ?? '');
$acomm = is_scalar($row['acomm'] ?? null) ? (int) $row['acomm'] : 0;

if ($aaid === '' || $aaid === '0') {
    header("Location: modules.php?name=$module_name");
    exit;
}

// Update view counter (prepared statement inside the repository)
$newsService->bumpStory($sid);

$artpage = 1;
$pagetitle = "- $title";
PageLayout\PageLayout::header();
echo '<h1 class="ibl-title">' . \Security\HtmlSanitizer::e($row['title'] ?? '') . '</h1>';
$artpage = 0;

$time = $newsService->normalizeStoryTime($time);
$datetime = ucfirst(date(_DATESTRING, $time));
if ($notes !== '' && $notes !== '0') {
    $notes = "\n\n<b>" . _NOTE . "</b> <i>$notes</i>";
} else {
    $notes = "";
}

if ($bodytext === '' || $bodytext === '0') {
    $bodytext = "$hometext$notes";
} else {
    $bodytext = "$hometext\n\n$bodytext$notes";
}

if ($informant === '' || $informant === '0') {
    /** @var string $informant */
    $informant = $anonymous;
}

$topicRow = $newsService->getTopicForStory($sid);
$topicid = is_scalar($topicRow['topicid'] ?? null) ? (int) $topicRow['topicid'] : 0;
$topicname = \Security\HtmlSanitizer::e($topicRow['topicname'] ?? '');
$topicimage = \Security\HtmlSanitizer::e($topicRow['topicimage'] ?? '');
$topictext = \Security\HtmlSanitizer::e($topicRow['topictext'] ?? '');

// Fetch category title via the News service (prepared statement) if catid is set
if ($catid !== 0) {
    $catTitle = $newsService->getCategoryTitle($catid);
    if ($catTitle !== null) {
        /** @var string $title1 */
        $title1 = \Security\HtmlSanitizer::safeHtmlOutput($catTitle);
        $title = "<a href=\"modules.php?name=$module_name&amp;file=categories&amp;op=newindex&amp;catid=$catid\"><font class=\"storycat\">$title1</font></a>: $title";
    }
}

(new \Topics\News\NewsView())->renderArticle([
    'aid' => $aaid, 'informant' => $informant, 'time' => $time, 'title' => $title,
    'bodytext' => $bodytext, 'topic' => $topic, 'topicname' => $topicname,
    'topicimage' => $topicimage, 'topictext' => $topictext,
]);

$nukeCompat->cookieDecode($user);

// Comment system and associated topics removed — both were deprecated PHP-Nuke features

PageLayout\PageLayout::footer();
