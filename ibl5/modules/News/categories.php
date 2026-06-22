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

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$op    = is_string($_REQUEST['op']    ?? null) ? $_REQUEST['op']    : '';
$catid = is_numeric($_REQUEST['catid'] ?? null) ? (int) $_REQUEST['catid'] : 0;

define('INDEX_FILE', true);
$categories = 1;
$cat = $catid;

function theindex($catid)
{
    global $storyhome, $topicname, $topicimage, $topictext, $datetime, $user, $cookie, $nukeurl, $prefix, $multilingual, $currentlang, $db, $articlecomm, $module_name, $userinfo, $authService, $mysqli_db;
    if (is_user($user)) {$userinfo = $authService->getUserInfo();}
    if ($multilingual == 1) {
        $querylang = "AND (alanguage='$currentlang' OR alanguage='')"; /* the OR is needed to display stories who are posted to ALL languages */
    } else {
        $querylang = "";
    }
    PageLayout\PageLayout::header();
    echo '<h1 class="ibl-title">News Categories</h1>';
    if (isset($userinfo['storynum'])) {
        $storynum = $userinfo['storynum'];
    } else {
        $storynum = $storyhome;
    }
    $catid = intval($catid);
    $newsService = new \Topics\News\NewsService($mysqli_db);
    $newsService->bumpCategory($catid);
    $stories = $newsService->getCategoryPageStories($catid, (int) $storynum, $querylang);
    $viewModels = [];
    foreach ($stories as $row) {
        $s_sid = intval($row['sid']);
        $aid = $row['aid'];
        $title = \Security\HtmlSanitizer::safeHtmlOutput($row['title']);
        $time = $row['time'];
        $hometext = $row['hometext'];
        $bodytext = $row['bodytext'];
        $comments = intval($row['comments']);
        $counter = intval($row['counter']);
        $topic = intval($row['topic']);
        $informant = $row['informant'];
        $notes = \Security\HtmlSanitizer::safeHtmlOutput($row['notes']);
        $acomm = intval($row['acomm']);
        $topicRow = $newsService->getTopicForStory($s_sid);
        $topicid = (int) ($topicRow['topicid'] ?? 0);
        $topicname = \Security\HtmlSanitizer::e($topicRow['topicname'] ?? '');
        $topicimage = \Security\HtmlSanitizer::e($topicRow['topicimage'] ?? '');
        $topictext = \Security\HtmlSanitizer::e($topicRow['topictext'] ?? '');
        $time = $newsService->normalizeStoryTime($time);
        $datetime = ucfirst(date(_DATESTRING, $time));
        $counts = $newsService->computeByteCounts((string) ($hometext ?? ''), (string) ($bodytext ?? ''));
        $introcount = $counts['intro'];
        $fullcount = $counts['full'];
        $totalcount = $counts['total'];
        $c_count = $comments;
        $r_options = "";
        if (isset($userinfo['umode'])) {$r_options .= "&amp;mode=" . $userinfo['umode'];}
        if (isset($userinfo['uorder'])) {$r_options .= "&amp;order=" . $userinfo['uorder'];}
        if (isset($userinfo['thold'])) {$r_options .= "&amp;thold=" . $userinfo['thold'];}
        $story_link = "<a class='readmore' href=\"modules.php?name=News&amp;file=article&amp;sid=$s_sid$r_options\">";
        $morelink = " ";
        if ($fullcount > 0 or $c_count > 0 or $articlecomm == 0 or $acomm == 1) {
            $morelink .= "$story_link<b>" . _READMORE . "</b></a> | ";
        } else {
            $morelink .= "";
        }
        if ($fullcount > 0) {$morelink .= "$totalcount " . _BYTESMORE . " | ";}
        if ($articlecomm == 1 and $acomm == 0) {
            if ($c_count == 0) {$morelink .= "$story_link" . _COMMENTSQ . "</a>";} elseif ($c_count == 1) {$morelink .= "$story_link$c_count " . _COMMENT . "</a>";} elseif ($c_count > 1) {$morelink .= "$story_link$c_count " . _COMMENTS . "</a>";}
        }
        $morelink .= " ";
        $morelink = str_replace(" |  | ", " | ", $morelink);
        $sid = intval($s_sid);
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
        if ($catid == 0 or $catid == "") {
            Header("Location: modules.php?name=$module_name");
        }
        theindex($catid);
        break;

    default:
        Header("Location: modules.php?name=$module_name");

}
