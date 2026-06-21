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

if (!defined('INDEX_FILE')) {
    define('INDEX_FILE', true);
}
$module_name = basename(dirname(__FILE__));
get_lang($module_name);

if (!function_exists('theindex')) :
function theindex($new_topic = "0")
{
    global $db, $storyhome, $topicname, $topicimage, $topictext, $user, $prefix, $multilingual, $currentlang, $articlecomm, $sitename, $user_news, $userinfo, $authService, $mysqli_db;
    if (is_user($user)) {$userinfo = $authService->getUserInfo();}
    $new_topic = intval($new_topic);
    if ($multilingual == 1) {
        $querylang = "AND (alanguage='$currentlang' OR alanguage='')";
    } else {
        $querylang = "";
    }
    PageLayout\PageLayout::header();

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

    $newsService = new \Topics\News\NewsService($mysqli_db);

    if ($new_topic == 0) {
        $home_msg = "";
    } else {
        $topicText = $newsService->getTopicText($new_topic);
        OpenTable();
        if ($topicText === null) {
            echo "<center><font class=\"title\">$sitename</font><br><br>" . _NOINFO4TOPIC . "<br><br>[ <a href=\"modules.php?name=News\">" . _GOTONEWSINDEX . "</a> | <a href=\"modules.php?name=Topics\">" . _SELECTNEWTOPIC . "</a> ]</center>";
        } else {
            $topic_title = \Security\HtmlSanitizer::safeHtmlOutput($topicText);
            $newsService->bumpAllTopics();
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

    $stories = ($new_topic == 0)
        ? $newsService->getHomePageStories((int) $storynum, $querylang)
        : $newsService->getTopicPageStories((int) $new_topic, (int) $storynum, $querylang);
    $viewModels = [];
    foreach ($stories as $row) {
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
        $topicRow = $newsService->getTopicForStory($s_sid);
        $topicid = (int) ($topicRow['topicid'] ?? 0);
        $topicname = \Security\HtmlSanitizer::e($topicRow['topicname'] ?? '');
        $topicimage = \Security\HtmlSanitizer::e($topicRow['topicimage'] ?? '');
        $topictext = \Security\HtmlSanitizer::e($topicRow['topictext'] ?? '');
        $time = $newsService->normalizeStoryTime($row['time']);
        $counts = $newsService->computeByteCounts((string) ($hometext ?? ''), (string) ($bodytext ?? ''));
        $introcount = $counts['intro'];
        $fullcount = $counts['full'];
        $totalcount = $counts['total'];
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
            $catTitle = $newsService->getCategoryTitle($catid);
            $title1 = \Security\HtmlSanitizer::safeHtmlOutput($catTitle ?? '');
            $catLabel = $title1 !== '' ? $title1 : 'Category';
            $title = "<a class='readmore' href=\"modules.php?name=News&amp;file=categories&amp;op=newindex&amp;catid=$catid\" aria-label=\"$catLabel\"><font class=\"storycat\">$title1</font></a>: $title";
        }
        $morelink = implode(' | ', $morelink_parts);
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
endif;

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
