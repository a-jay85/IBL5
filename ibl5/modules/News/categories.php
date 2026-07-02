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
    global $storyhome, $topicname, $topicimage, $topictext, $datetime, $user, $cookie, $nukeurl, $prefix, $multilingual, $currentlang, $articlecomm, $module_name, $userinfo, $authService, $mysqli_db;
    if (is_user($user)) {$userinfo = $authService->getUserInfo();}
    $storyConditions = [];
    $storyTypes = '';
    $storyParams = [];
    if ($multilingual == 1) {
        $storyConditions[] = "(alanguage = ? OR alanguage = '')";
        $storyTypes .= 's';
        $storyParams[] = $currentlang;
    }
    PageLayout\PageLayout::header();
    echo '<h1 class="ibl-title">News Categories</h1>';
    if (isset($userinfo['storynum'])) {
        $storynum = $userinfo['storynum'];
    } else {
        $storynum = $storyhome;
    }
    $catid = intval($catid);
    $stmtCatUpdate = $mysqli_db->prepare("UPDATE " . $prefix . "_stories_cat SET counter = counter + 1 WHERE catid = ?");
    $stmtCatUpdate->bind_param('i', $catid);
    $stmtCatUpdate->execute();
    $stmtCatUpdate->close();
    array_unshift($storyConditions, "catid = ?");
    $storyTypes = 'i' . $storyTypes;
    array_unshift($storyParams, $catid);
    $storyTypes .= 'i';
    $storyParams[] = (int) $storynum;
    $stmtStories = $mysqli_db->prepare("SELECT sid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, acomm FROM " . $prefix . "_stories WHERE " . implode(' AND ', $storyConditions) . " ORDER BY sid DESC LIMIT ?");
    $stmtStories->bind_param($storyTypes, ...$storyParams);
    $stmtStories->execute();
    $result = $stmtStories->get_result();
    while ($row = $result->fetch_assoc()) {
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
        $stmtTopics = $mysqli_db->prepare(
            "SELECT t.topicid, t.topicname, t.topicimage, t.topictext
             FROM {$prefix}_stories s
             LEFT JOIN {$prefix}_topics t ON t.topicid = s.topic
             WHERE s.sid = ?"
        );
        $stmtTopics->bind_param('i', $s_sid);
        $stmtTopics->execute();
        $topicRow = $stmtTopics->get_result()->fetch_assoc();
        $stmtTopics->close();
        $topicid = (int) ($topicRow['topicid'] ?? 0);
        $topicname = \Security\HtmlSanitizer::e($topicRow['topicname'] ?? '');
        $topicimage = \Security\HtmlSanitizer::e($topicRow['topicimage'] ?? '');
        $topictext = \Security\HtmlSanitizer::e($topicRow['topictext'] ?? '');
        if (!is_numeric($time)) {
            preg_match('/(\d{4})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})/', $time, $dtParts);
            $time = gmmktime((int) $dtParts[4], (int) $dtParts[5], (int) $dtParts[6], (int) $dtParts[2], (int) $dtParts[3], (int) $dtParts[1]);
        }
        $time -= date("Z");
        $datetime = ucfirst(date(_DATESTRING, $time));
        $introcount = strlen($hometext ?? '');
        $fullcount = strlen($bodytext ?? '');
        $totalcount = $introcount + $fullcount;
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
        $stmtCat2 = $mysqli_db->prepare("SELECT title FROM " . $prefix . "_stories_cat WHERE catid = ?");
        $stmtCat2->bind_param('i', $catid);
        $stmtCat2->execute();
        $row2 = $stmtCat2->get_result()->fetch_assoc();
        $stmtCat2->close();
        $title1 = \Security\HtmlSanitizer::safeHtmlOutput($row2['title'] ?? '');
        $title = "$title1: $title";
        themeindex($aid, $informant, $time, $title, $counter, $topic, $hometext, $notes, $morelink, $topicname, $topicimage, $topictext);
    }
    $stmtStories->close();
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
