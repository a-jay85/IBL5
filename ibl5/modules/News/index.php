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
    NukeHeader::header();
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
        $topic_title = filter($row_a['topictext'], "nohtml");
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
    $result = $db->sql_query("SELECT sid, catid, aid, title, time, hometext, bodytext, comments, counter, topic, informant, notes, acomm, score, ratings FROM " . $prefix . "_stories $qdb $querylang ORDER BY sid DESC limit $storynum");
    while ($row = $db->sql_fetchrow($result)) {
        $s_sid = intval($row['sid']);
        $catid = intval($row['catid']);
        $aid = filter($row['aid'], "nohtml");
        $title = filter($row['title'], "nohtml");
        $time = $row['time'];
        $hometext = filter($row['hometext']);
        $bodytext = filter($row['bodytext']);
        $comments = intval($row['comments']);
        $counter = intval($row['counter']);
        $topic = intval($row['topic']);
        $informant = filter($row['informant'], "nohtml");
        $notes = filter($row['notes']);
        $acomm = intval($row['acomm']);
        $score = intval($row['score']);
        $ratings = intval($row['ratings']);
        if ($catid > 0) {
            $row2 = $db->sql_fetchrow($db->sql_query("SELECT title FROM " . $prefix . "_stories_cat WHERE catid='$catid'"));
            $cattitle = stripslashes(check_html($row2['title'], "nohtml"));
        }
        getTopics($s_sid);
        formatTimestamp($time);
        $subject = filter($subject, "nohtml");
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
        $sid = intval($s_sid);
        if ($catid != 0) {
            $row3 = $db->sql_fetchrow($db->sql_query("SELECT title FROM " . $prefix . "_stories_cat WHERE catid='$catid'"));
            $title1 = filter($row3['title'], "nohtml");
            $title = "<a  class='readmore' href=\"modules.php?name=News&amp;file=categories&amp;op=newindex&amp;catid=$catid\"><font class=\"storycat\">$title1</font></a>: $title";
            $morelink .= " | <a href=\"modules.php?name=News&amp;file=categories&amp;op=newindex&amp;catid=$catid\">$title1</a>";
        }
        if ($score != 0) {
            $rated = substr($score / $ratings, 0, 4);
        } else {
            $rated = 0;
        }
        $morelink .= " | " . _SCORE . " $rated";
        $morelink .= " ";
        $morelink = str_replace(" |  | ", " | ", $morelink);
        themeindex($aid, $informant, $time, $title, $counter, $topic, $hometext, $notes, $morelink, $topicname, $topicimage, $topictext);
    }
    NukeFooter::footer();
}

function rate_article($sid, $score, $gfx_check, $random_num = "0")
{
    global $prefix, $db, $ratecookie, $sitename, $r_options, $sitekey, $gfx_chk, $module_name;
    if (isset($random_num)) {
        $datekey = date("F j");
        $rcode = hexdec(md5($_SERVER['HTTP_USER_AGENT'] . $sitekey . $random_num . $datekey));
        $code = substr($rcode, 2, 3);
        if (extension_loaded("gd") and $code != $gfx_check and $gfx_chk != 0) {
            mt_srand((double) microtime() * 1000000);
            $maxran = 1000000;
            $random_num = mt_rand(0, $maxran);
            NukeHeader::header();
            title("$sitename: " . _ARTICLERATING . "");
            OpenTable();
            $row = $db->sql_fetchrow($db->sql_query("SELECT title FROM " . $prefix . "_stories WHERE sid='$sid'"));
            $row['title'] = filter($row['title'], "nohtml");
            echo "<center><a href=\"modules.php?name=$module_name&file=article&sid=$sid$r_options\"><b>" . $row['title'] . "</b></a><br>" . _ARTICLERATING . ": <img src=\"images/articles/stars-$score.gif\" border=\"0\" alt=\"$score/5\" title=\"$score/5\"> ($score/5)<br><br>";
            echo "" . _TOFINISHRATINGERROR . "<br><br>";
            echo "<form action=\"modules.php?name=$module_name\" method=\"post\">";
            echo "" . _SECURITYCODE . ":<br><img src='?gfx=gfx_little&random_num=$random_num' border='1' alt='" . _SECURITYCODE . "' title='" . _SECURITYCODE . "'><br><br>\n";
            echo "" . _TYPESECCODE . ":<br><input type=\"text\" NAME=\"gfx_check\" SIZE=\"3\" MAXLENGTH=\"3\"><br>\n";
            echo "<input type=\"hidden\" name=\"random_num\" value=\"$random_num\"><br>\n";
            echo "<input type=\"hidden\" name=\"score\" value=\"$score\"><br>\n";
            echo "<input type=\"hidden\" name=\"sid\" value=\"$sid\">\n";
            echo "<input type=\"hidden\" name=\"op\" value=\"rate_article\">";
            echo "<input type=\"submit\" value=\"" . _CASTMYVOTE . "\"></font></center></form>";
            CloseTable();
            NukeFooter::footer();
            die();
        } else {
            $score = intval($score);
            $sid = intval($sid);
            if ($score) {
                if ($score > 5) {$score = 5;}
                if ($score < 1) {$score = 1;}
                if ($score != 1 and $score != 2 and $score != 3 and $score != 4 and $score != 5) {
                    Header("Location: index.php");
                    die();
                }
                $ip = $_SERVER['REMOTE_ADDR'];
                $num = $db->sql_numrows($db->sql_query("SELECT * FROM " . $prefix . "_stories WHERE sid='$sid' AND rating_ip='$ip'"));
                if ($num != 0) {
                    Header("Location: modules.php?name=News&op=rate_complete&sid=$sid&rated=1");
                    die();
                }
                if (isset($ratecookie)) {
                    $rcookie = base64_decode($ratecookie);
                    $rcookie = addslashes($rcookie);
                    $r_cookie = explode(":", $rcookie);
                }
                for ($i = 0; $i < sizeof($r_cookie); $i++) {
                    if ($r_cookie[$i] == $sid) {
                        $a = 1;
                    }
                }
                if ($a == 1) {
                    Header("Location: modules.php?name=News&op=rate_complete&sid=$sid&rated=1");
                    die();
                } else {
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $result = $db->sql_query("update " . $prefix . "_stories set score=score+$score, ratings=ratings+1, rating_ip='$ip' where sid='$sid'");
                    $info = base64_encode("$rcookie$sid:");
                    setcookie("ratecookie", "$info", time() + 86400);
                    update_points(7);
                    Header("Location: modules.php?name=News&op=rate_complete&sid=$sid&score=$score");
                }
            } else {
                NukeHeader::header();
                title("$sitename: " . _ARTICLERATING . "");
                OpenTable();
                echo "<center>" . _DIDNTRATE . "<br><br>"
                    . "" . _GOBACK . "</center>";
                CloseTable();
                NukeFooter::footer();
            }
        }
    } else {
        mt_srand((double) microtime() * 1000000);
        $maxran = 1000000;
        $random_num = mt_rand(0, $maxran);
        if (extension_loaded("gd") and $gfx_chk != 0) {
            NukeHeader::header();
            title("$sitename: " . _ARTICLERATING . "");
            OpenTable();
            $row = $db->sql_fetchrow($db->sql_query("SELECT title FROM " . $prefix . "_stories WHERE sid='$sid'"));
            echo "<center><a href=\"modules.php?name=$module_name&file=article&sid=$sid$r_options\"><b>" . $row['title'] . "</b></a><br>" . _ARTICLERATING . ": <img src=\"images/articles/stars-$score.gif\" border=\"0\" alt=\"$score/5\" title=\"$score/5\"> ($score/5)<br><br>";
            echo "" . _TOFINISHRATING . "<br><br>";
            echo "<form action=\"modules.php?name=$module_name\" method=\"post\">";
            echo "" . _SECURITYCODE . ":<br><img src='?gfx=gfx_little&random_num=$random_num' border='1' alt='" . _SECURITYCODE . "' title='" . _SECURITYCODE . "'><br><br>\n";
            echo "" . _TYPESECCODE . ":<br><input type=\"text\" NAME=\"gfx_check\" SIZE=\"3\" MAXLENGTH=\"3\"><br>\n";
            echo "<input type=\"hidden\" name=\"random_num\" value=\"$random_num\"><br>\n";
            echo "<input type=\"hidden\" name=\"score\" value=\"$score\"><br>\n";
            echo "<input type=\"hidden\" name=\"sid\" value=\"$sid\">\n";
            echo "<input type=\"hidden\" name=\"op\" value=\"rate_article\">";
            echo "<input type=\"submit\" value=\"" . _CASTMYVOTE . "\"></font></center></form>";
            CloseTable();
            NukeFooter::footer();
        } else {
            $random_num = "$random_num";
            $gfx_check = "$code";
            Header("Location: modules.php?name=$module_name&op=rate_article&sid=$sid&score=$score&random_num=$random_num");
        }
    }
}

function rate_complete($sid, $score, $rated = 0)
{
    global $sitename, $user, $cookie, $module_name, $userinfo;
    $r_options = "";
    if (is_user($user)) {
        getusrinfo($user);
        if (isset($userinfo['umode'])) {$r_options .= "&amp;mode=" . $userinfo['umode'];}
        if (isset($userinfo['uorder'])) {$r_options .= "&amp;order=" . $userinfo['uorder'];}
        if (isset($userinfo['thold'])) {$r_options .= "&amp;thold=" . $userinfo['thold'];}
    }
    NukeHeader::header();
    title("$sitename: " . _ARTICLERATING . "");
    OpenTable();
    if ($rated == 0) {
        $row = $db->sql_fetchrow($db->sql_query("SELECT title FROM " . $prefix . "_stories WHERE sid='$sid'"));
        $row['title'] = filter($row['title'], "nohtml");
        echo "<center><a href=\"modules.php?name=$module_name&file=article&sid=$sid$r_options\"><b>" . $row['title'] . "</b></a><br>" . _YOURATEDARTICLE . ": <img src=\"images/articles/stars-$score.gif\" border=\"0\" alt=\"$score/5\" title=\"$score/5\"> ($score/5)<br><br>";
        echo "<center>" . _THANKSVOTEARTICLE . "<br><br>"
            . "[ <a href=\"modules.php?name=$module_name&amp;file=article&amp;sid=$sid$r_options\">" . _BACKTOARTICLEPAGE . "</a> ]</center>";
    } elseif ($rated == 1) {
        echo "<center>" . _ALREADYVOTEDARTICLE . "<br><br>"
            . "[ <a href=\"modules.php?name=$module_name&amp;file=article&amp;sid=$sid$r_options\">" . _BACKTOARTICLEPAGE . "</a> ]</center>";
    }
    CloseTable();
    NukeFooter::footer();
}

if (!(isset($new_topic))) {$new_topic = 0;}
if (!(isset($op))) {$op = "";}

switch ($op) {

    default:
        theindex($new_topic);
        break;

    case "rate_article":
        rate_article($sid, $score, $gfx_check, $random_num);
        break;

    case "rate_complete":
        rate_complete($sid, $score, $rated);
        break;

}
