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

define('INDEX_FILE', true);
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

function theindex($new_topic = "0")
{
    global $db, $storyhome, $topicname, $topicimage, $topictext, $user, $prefix, $multilingual, $currentlang, $articlecomm, $sitename, $user_news, $userinfo;
    if (is_user($user)) {getusrinfo($user);}
    $new_topic = intval($new_topic);
    if ($multilingual == 1) {
        $querylang = "AND (alanguage='$currentlang' OR alanguage='')";
    } else {
        $querylang = "";
    }
    PageLayout\PageLayout::header();
    automated_news();
    if (isset($userinfo['storynum']) and $user_news == 1) {
        $storynum = $userinfo['storynum'];
    } else {
        $storynum = $storyhome;
    }
    if ($new_topic == 0) {
        $qdb = "WHERE (ihome='0' OR catid='0')";
        $home_msg = "";
    } else {
        $qdb = "WHERE topic='$new_topic'";
        $result_a = $db->sql_query("SELECT topictext FROM " . $prefix . "_topics WHERE topicid='$new_topic'");
        $row_a = $db->sql_fetchrow($result_a);
        $numrows_a = $db->sql_numrows($result_a);
        /** @var string $topic_title */
        $topic_title = \Utilities\HtmlSanitizer::safeHtmlOutput($row_a['topictext']);
        OpenTable();
        if ($numrows_a == 0) {
            echo "<center><font class=\"title\">$sitename</font><br><br>" . _NOINFO4TOPIC . "<br><br>[ <a href=\"modules.php?name=News\">" . _GOTONEWSINDEX . "</a> | <a href=\"modules.php?name=Topics\">" . _SELECTNEWTOPIC . "</a> ]</center>";
        } else {
            $db->sql_query("UPDATE " . $prefix . "_topics SET counter=counter+1");
            echo "<center><font class=\"title\">$sitename: $topic_title</font><br><br>"
                . "<form action=\"modules.php?name=Search\" method=\"post\">"
                . "<input type=\"hidden\" name=\"topic\" value=\"$new_topic\">"
                . "" . _SEARCHONTOPIC . ": <input type=\"name\" name=\"query\" size=\"30\">&nbsp;&nbsp;"
                . "<input type=\"submit\" value=\"" . _SEARCH . "\">"
                . "</form>"
                . "[ <a href=\"index.php\">" . _GOTOHOME . "</a> | <a href=\"modules.php?name=Topics\">" . _SELECTNEWTOPIC . "</a> ]</center>";
        }
        CloseTable();
        echo "<br>";
    }
    $result = $db->sql_query("SELECT sid, catid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, acomm FROM " . $prefix . "_stories $qdb $querylang ORDER BY sid DESC limit $storynum");
    while ($row = $db->sql_fetchrow($result)) {
        $s_sid = intval($row['sid']);
        $catid = intval($row['catid']);
        $aid = $row['aid'];
        /** @var string $title */
        $title = \Utilities\HtmlSanitizer::safeHtmlOutput($row['title']);
        $time = $row['time'];
        $hometext = $row['hometext'];
        $bodytext = $row['bodytext'];
        $comments = intval($row['comments']);
        $counter = intval($row['counter']);
        $topic = intval($row['topic']);
        $informant = $row['informant'];
        /** @var string $notes */
        $notes = \Utilities\HtmlSanitizer::safeHtmlOutput($row['notes']);
        $acomm = intval($row['acomm']);
        if ($catid > 0) {
            $row2 = $db->sql_fetchrow($db->sql_query("SELECT title FROM " . $prefix . "_stories_cat WHERE catid='$catid'"));
            /** @var string $cattitle */
            $cattitle = \Utilities\HtmlSanitizer::safeHtmlOutput($row2['title']);
        }
        getTopics($s_sid);
        formatTimestamp($time);
        $introcount = strlen($hometext);
        $fullcount = strlen($bodytext);
        $totalcount = $introcount + $fullcount;
        $c_count = $comments;
        $r_options = "";
        if (isset($userinfo['umode'])) {$r_options .= "&amp;mode=" . $userinfo['umode'];}
        if (isset($userinfo['uorder'])) {$r_options .= "&amp;order=" . $userinfo['uorder'];}
        if (isset($userinfo['thold'])) {$r_options .= "&amp;thold=" . $userinfo['thold'];}
        $story_url = "modules.php?name=News&amp;file=article&amp;sid=$s_sid$r_options";
        $morelink_parts = [];
        if ($fullcount > 0 or $c_count > 0 or $articlecomm == 0 or $acomm == 1) {
            $morelink_parts[] = "<a class=\"news-article__link\" href=\"$story_url\">" . _READMORE . "</a>";
        }
        if ($fullcount > 0) {
            $morelink_parts[] = "<span class=\"news-article__meta-item\">$totalcount " . _BYTESMORE . "</span>";
        }
        if ($articlecomm == 1 and $acomm == 0) {
            if ($c_count == 0) {
                $morelink_parts[] = "<a class=\"news-article__link\" href=\"$story_url\">" . _COMMENTSQ . "</a>";
            } elseif ($c_count == 1) {
                $morelink_parts[] = "<a class=\"news-article__link\" href=\"$story_url\">$c_count " . _COMMENT . "</a>";
            } elseif ($c_count > 1) {
                $morelink_parts[] = "<a class=\"news-article__link\" href=\"$story_url\">$c_count " . _COMMENTS . "</a>";
            }
        }
        $sid = intval($s_sid);
        if ($catid != 0) {
            $row3 = $db->sql_fetchrow($db->sql_query("SELECT title FROM " . $prefix . "_stories_cat WHERE catid='$catid'"));
            /** @var string $title1 */
            $title1 = \Utilities\HtmlSanitizer::safeHtmlOutput($row3['title']);
            $title = "<a class='readmore' href=\"modules.php?name=News&amp;file=categories&amp;op=newindex&amp;catid=$catid\"><font class=\"storycat\">$title1</font></a>: $title";
        }
        $morelink = implode(' | ', $morelink_parts);
        themeindex($aid, $informant, $time, $title, $counter, $topic, $hometext, $notes, $morelink, $topicname, $topicimage, $topictext);
    }
    PageLayout\PageLayout::footer();
}

if (!(isset($new_topic))) {$new_topic = 0;}
if (!(isset($op))) {$op = "";}

switch ($op) {

    default:
        theindex($new_topic);
        break;

}
