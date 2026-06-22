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

define('INDEX_FILE', true);
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

function theindex($new_topic = "0")
{
    global $storyhome, $topicname, $topicimage, $topictext, $user, $prefix, $multilingual, $currentlang, $articlecomm, $sitename, $user_news, $userinfo, $authService, $mysqli_db;
    if (is_user($user)) {$userinfo = $authService->getUserInfo();}
    $new_topic = intval($new_topic);
    $storyConditions = [];
    $storyTypes = '';
    $storyParams = [];
    if ($multilingual == 1) {
        $storyConditions[] = "(alanguage = ? OR alanguage = '')";
        $storyTypes .= 's';
        $storyParams[] = $currentlang;
    }
    PageLayout\PageLayout::header();

    if (defined('HOME_FILE')) {
        echo '<h1 class="ibl-title">' . \Security\HtmlSanitizer::e((string) $sitename) . '</h1>';
    } else {
        echo '<h1 class="ibl-title">News</h1>';
    }

    if (is_user($user)) {
        $teamRepo = new \Repositories\TeamIdentityRepository($mysqli_db);
        $teamName = $teamRepo->getTeamnameFromUsername($userinfo['username'] ?? null);
        if ($teamName !== null && $teamName !== \League\League::FREE_AGENTS_TEAM_NAME) {
            $tid = $teamRepo->getTidFromTeamname($teamName);
            if ($tid !== null && \League\League::isRealFranchise($tid)) {
                $recapService = new \LastSimRecap\LastSimRecapService(
                    new \LastSimRecap\LastSimRecapRepository($mysqli_db),
                    new \Repositories\PlayerLookupRepository($mysqli_db),
                );
                $slate = $recapService->buildSlateForTeam($tid);
                if ($slate !== null) {
                    echo (new \LastSimRecap\LastSimRecapView())->render($slate);
                }
            }
        }
    }

    if (defined('HOME_FILE')) {
        blocks("Center");
    }

    if (isset($userinfo['storynum']) and $user_news == 1) {
        $storynum = $userinfo['storynum'];
    } else {
        $storynum = $storyhome;
    }
    if ($new_topic == 0) {
        array_unshift($storyConditions, "(ihome='0' OR catid='0')");
        $home_msg = "";
    } else {
        array_unshift($storyConditions, "topic = ?");
        $storyTypes = 'i' . $storyTypes;
        array_unshift($storyParams, $new_topic);
        $stmtTopic = $mysqli_db->prepare("SELECT topictext FROM " . $prefix . "_topics WHERE topicid = ?");
        $stmtTopic->bind_param('i', $new_topic);
        $stmtTopic->execute();
        $result_a = $stmtTopic->get_result();
        $row_a = $result_a->fetch_assoc();
        $numrows_a = $result_a->num_rows;
        $stmtTopic->close();
        $topic_title = \Security\HtmlSanitizer::safeHtmlOutput($row_a['topictext'] ?? '');
        OpenTable();
        if ($numrows_a == 0) {
            echo "<center><font class=\"title\">$sitename</font><br><br>" . _NOINFO4TOPIC . "<br><br>[ <a href=\"modules.php?name=News\">" . _GOTONEWSINDEX . "</a> | <a href=\"modules.php?name=Topics\">" . _SELECTNEWTOPIC . "</a> ]</center>";
        } else {
            $stmtUpdate = $mysqli_db->prepare("UPDATE " . $prefix . "_topics SET counter = counter + 1");
            $stmtUpdate->execute();
            $stmtUpdate->close();
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
    $storyTypes .= 'i';
    $storyParams[] = (int) $storynum;
    $whereSql = $storyConditions !== [] ? 'WHERE ' . implode(' AND ', $storyConditions) : '';
    $stmtStories = $mysqli_db->prepare("SELECT sid, catid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, acomm FROM " . $prefix . "_stories " . $whereSql . " ORDER BY sid DESC LIMIT ?");
    $stmtStories->bind_param($storyTypes, ...$storyParams);
    $stmtStories->execute();
    $result = $stmtStories->get_result();
    while ($row = $result->fetch_assoc()) {
        $s_sid = intval($row['sid']);
        $catid = intval($row['catid']);
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
        if ($catid > 0) {
            $stmtCat2 = $mysqli_db->prepare("SELECT title FROM " . $prefix . "_stories_cat WHERE catid = ?");
            $stmtCat2->bind_param('i', $catid);
            $stmtCat2->execute();
            $row2 = $stmtCat2->get_result()->fetch_assoc();
            $stmtCat2->close();
            $cattitle = \Security\HtmlSanitizer::safeHtmlOutput($row2['title'] ?? '');
        }
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
            $stmtCat3 = $mysqli_db->prepare("SELECT title FROM " . $prefix . "_stories_cat WHERE catid = ?");
            $stmtCat3->bind_param('i', $catid);
            $stmtCat3->execute();
            $row3 = $stmtCat3->get_result()->fetch_assoc();
            $stmtCat3->close();
            $title1 = \Security\HtmlSanitizer::safeHtmlOutput($row3['title'] ?? '');
            $catLabel = $title1 !== '' ? $title1 : 'Category';
            $title = "<a class='readmore' href=\"modules.php?name=News&amp;file=categories&amp;op=newindex&amp;catid=$catid\" aria-label=\"$catLabel\"><font class=\"storycat\">$title1</font></a>: $title";
        }
        $morelink = implode(' | ', $morelink_parts);
        themeindex($aid, $informant, $time, $title, $counter, $topic, $hometext, $notes, $morelink, $topicname, $topicimage, $topictext);
    }
    $stmtStories->close();
    PageLayout\PageLayout::footer();
}

// Legacy globals previously populated by ConfigBootstrap::extractRequestToGlobals().
// PR2 narrowed that extraction to a 2-key allowlist (newlang, redirect), so module
// inputs are now read from $_REQUEST explicitly here.
$op        = is_string($_REQUEST['op']        ?? null) ? $_REQUEST['op']        : '';
$new_topic = is_numeric($_REQUEST['new_topic'] ?? null) ? (int) $_REQUEST['new_topic'] : 0;

switch ($op) {

    default:
        theindex($new_topic);
        break;

}
