<?php

declare(strict_types=1);

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

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

// English UI strings defined in this file.
if (!defined('_READMORE')) {
    define('_READMORE', 'Read More...');
}
if (!defined('_BYTESMORE')) {
    define('_BYTESMORE', 'bytes more');
}
if (!defined('_COMMENTSQ')) {
    define('_COMMENTSQ', 'comments?');
}
if (!defined('_COMMENT')) {
    define('_COMMENT', 'comment');
}
if (!defined('_COMMENTS')) {
    define('_COMMENTS', 'comments');
}
if (!defined('_DATESTRING')) {
    define('_DATESTRING', 'l, F d @ H:i:s T');
}

$module_name = basename(dirname(__FILE__));

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$op    = is_string($_REQUEST['op']    ?? null) ? $_REQUEST['op']    : '';
$catid = is_numeric($_REQUEST['catid'] ?? null) ? (int) $_REQUEST['catid'] : 0;

define('INDEX_FILE', true);
$categories = 1;
$cat = $catid;

function theindex(int $catid): void
{
    global $storyhome, $topicname, $topicimage, $topictext, $datetime, $user, $nukeurl, $prefix, $multilingual, $currentlang, $db, $articlecomm, $module_name, $userinfo, $authService, $mysqli_db;
    assert($mysqli_db instanceof \mysqli);
    assert($authService instanceof \Auth\Contracts\AuthServiceInterface);
    $nukeCompat = new \Utilities\NukeCompat();
    if ($nukeCompat->isUser($user)) {$userinfo = $authService->getUserInfo();}
    // Language filter: the value is bound by NewsRepository; null means no clause.
    $language = (is_numeric($multilingual) && (int) $multilingual === 1 && is_string($currentlang)) ? $currentlang : null;
    PageLayout\PageLayout::header();
    echo '<h1 class="ibl-title">News Categories</h1>';
    $ui = is_array($userinfo) ? $userinfo : [];
    $storynum = isset($ui['storynum']) && is_numeric($ui['storynum']) ? (int) $ui['storynum'] : (is_numeric($storyhome) ? (int) $storyhome : 0);
    $newsService = new \Topics\News\NewsService($mysqli_db);
    $newsService->bumpCategory($catid);
    $stories = $newsService->getCategoryPageStories($catid, $storynum, $language);
    $viewModels = [];
    $articlecommOn = is_numeric($articlecomm) ? (int) $articlecomm : 0;
    foreach ($stories as $row) {
        $s_sid = is_scalar($row['sid']) ? (int) $row['sid'] : 0;
        /** @var string $aid nuke_stories.aid is NOT NULL varchar */
        $aid = $row['aid'];
        $title = \Security\HtmlSanitizer::safeHtmlOutput($row['title']);
        $time = is_int($row['time']) || is_string($row['time']) ? $row['time'] : '';
        $hometext = is_string($row['hometext']) ? $row['hometext'] : '';
        $bodytext = is_string($row['bodytext']) ? $row['bodytext'] : '';
        $comments = is_scalar($row['comments']) ? (int) $row['comments'] : 0;
        $counter = is_scalar($row['counter']) ? (int) $row['counter'] : 0;
        $topic = is_scalar($row['topic']) ? (int) $row['topic'] : 0;
        /** @var string $informant nuke_stories.informant is NOT NULL varchar */
        $informant = $row['informant'];
        $notes = \Security\HtmlSanitizer::safeHtmlOutput($row['notes']);
        $acomm = is_scalar($row['acomm']) ? (int) $row['acomm'] : 0;
        $topicRow = $newsService->getTopicForStory($s_sid);
        $topicname = \Security\HtmlSanitizer::e($topicRow['topicname'] ?? '');
        $topicimage = \Security\HtmlSanitizer::e($topicRow['topicimage'] ?? '');
        $topictext = \Security\HtmlSanitizer::e($topicRow['topictext'] ?? '');
        $time = $newsService->normalizeStoryTime($time);
        $datetime = ucfirst(date(_DATESTRING, $time));
        $counts = $newsService->computeByteCounts($hometext, $bodytext);
        $fullcount = $counts['full'];
        $totalcount = $counts['total'];
        $c_count = $comments;
        $r_options = "";
        if (isset($ui['umode']) && is_scalar($ui['umode'])) {$r_options .= "&amp;mode=" . (string) $ui['umode'];}
        if (isset($ui['uorder']) && is_scalar($ui['uorder'])) {$r_options .= "&amp;order=" . (string) $ui['uorder'];}
        if (isset($ui['thold']) && is_scalar($ui['thold'])) {$r_options .= "&amp;thold=" . (string) $ui['thold'];}
        $story_link = "<a class='readmore' href=\"modules.php?name=News&amp;file=article&amp;sid=$s_sid$r_options\">";
        $morelink = " ";
        if ($fullcount > 0 or $c_count > 0 or $articlecommOn === 0 or $acomm === 1) {
            $morelink .= "$story_link<b>" . _READMORE . "</b></a> | ";
        } else {
            $morelink .= "";
        }
        if ($fullcount > 0) {$morelink .= "$totalcount " . _BYTESMORE . " | ";}
        if ($articlecommOn === 1 and $acomm === 0) {
            if ($c_count === 0) {$morelink .= "$story_link" . _COMMENTSQ . "</a>";} elseif ($c_count === 1) {$morelink .= "$story_link$c_count " . _COMMENT . "</a>";} elseif ($c_count > 1) {$morelink .= "$story_link$c_count " . _COMMENTS . "</a>";}
        }
        $morelink .= " ";
        $morelink = str_replace(" |  | ", " | ", $morelink);
        $catTitle = $newsService->getCategoryTitle($catid);
        $title1 = \Security\HtmlSanitizer::safeHtmlOutput($catTitle ?? '');
        $title = "$title1: $title";
        $viewModels[] = [
            'aid' => $aid, 'informant' => $informant, 'time' => $time, 'title' => $title,
            'counter' => $counter, 'topic' => $topic, 'hometext' => $hometext,
            'notes' => $notes, 'morelink' => $morelink, 'topicname' => $topicname,
            'topicimage' => $topicimage, 'topictext' => $topictext,
        ];
    }
    (new \Topics\News\NewsView())->renderStories($viewModels);
    PageLayout\PageLayout::footer();
}

switch ($op) {

    case "newindex":
        if ($catid === 0) {
            header("Location: modules.php?name=$module_name");
        }
        theindex($catid);
        break;

    default:
        header("Location: modules.php?name=$module_name");

}
