<?php

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
    if (isset($userinfo['storynum'])) {
        $storynum = $userinfo['storynum'];
    } else {
        $storynum = $storyhome;
    }
    $catid = intval($catid);
    $db->sql_query("update " . $prefix . "_stories_cat set counter=counter+1 where catid='$catid'");
    $result = $db->sql_query("SELECT sid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, acomm FROM " . $prefix . "_stories where catid='$catid' $querylang ORDER BY sid DESC limit $storynum");
    while ($row = $db->sql_fetchrow($result)) {
        $s_sid = intval($row['sid']);
        $aid = $row['aid'];
        $title = \Utilities\HtmlSanitizer::safeHtmlOutput($row['title']);
        $time = $row['time'];
        $hometext = $row['hometext'];
        $bodytext = $row['bodytext'];
        $comments = intval($row['comments']);
        $counter = intval($row['counter']);
        $topic = intval($row['topic']);
        $informant = $row['informant'];
        $notes = \Utilities\HtmlSanitizer::safeHtmlOutput($row['notes']);
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
        $topicname = \Utilities\HtmlSanitizer::e($topicRow['topicname'] ?? '');
        $topicimage = \Utilities\HtmlSanitizer::e($topicRow['topicimage'] ?? '');
        $topictext = \Utilities\HtmlSanitizer::e($topicRow['topictext'] ?? '');
        if (!is_numeric($time)) {
            preg_match('/(\d{4})-(\d{1,2})-(\d{1,2}) (\d{1,2}):(\d{1,2}):(\d{1,2})/', $time, $dtParts);
            $time = gmmktime($dtParts[4], $dtParts[5], $dtParts[6], $dtParts[2], $dtParts[3], $dtParts[1]);
        }
        $time -= date("Z");
        $datetime = ucfirst(date(_DATESTRING, $time));
        $introcount = strlen($hometext);
        $fullcount = strlen($bodytext);
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
        $row2 = $db->sql_fetchrow($db->sql_query("select title from " . $prefix . "_stories_cat where catid='$catid'"));
        $title1 = \Utilities\HtmlSanitizer::safeHtmlOutput($row2['title']);
        $title = "$title1: $title";
        themeindex($aid, $informant, $time, $title, $counter, $topic, $hometext, $notes, $morelink, $topicname, $topicimage, $topictext);
    }
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
