<?php

/**
 * Legacy global functions extracted from mainfile.php.
 *
 * These functions are called by PHP-Nuke modules as bare functions
 * (e.g., is_admin(), filter(), etc.). They remain as global functions,
 * NOT class methods, because modules rely on calling them without a namespace.
 *
 * This file is required once after the bootstrap pipeline populates $GLOBALS.
 * It is intentionally NOT a class and does NOT have declare(strict_types=1)
 * because the legacy function signatures rely on PHP's loose type coercion.
 */

// Guard: only define these functions once (prevents double-include issues)
if (function_exists('include_secure')) {
    return;
}

// SECURITY: Safe include function with path traversal protection
function include_secure($file_name)
{
    \Bootstrap\SecurityBootstrap::includeSafe($file_name);
}

function get_lang($module)
{
    global $currentlang, $language;
    if ($module == "admin" and $module != "Forums") {
        if (file_exists("admin/language/lang-" . $currentlang . ".php")) {
            include_secure("admin/language/lang-" . $currentlang . ".php");
        } elseif (file_exists("admin/language/lang-" . $language . ".php")) {
            include_secure("admin/language/lang-" . $language . ".php");
        }
    } else {
        if (file_exists("modules/$module/language/lang-" . $currentlang . ".php")) {
            include_secure("modules/$module/language/lang-" . $currentlang . ".php");
        } elseif (file_exists("modules/$module/language/lang-" . $language . ".php")) {
            include_secure("modules/$module/language/lang-" . $language . ".php");
        }
    }
}

function is_admin($admin = null)
{
    global $authService;
    static $adminSave;
    if (isset($adminSave)) {
        return $adminSave;
    }
    return $adminSave = $authService->isAdmin() ? 1 : 0;
}

function is_user($user)
{
    global $authService;
    static $userSave;
    if (isset($userSave)) {
        return $userSave;
    }
    return $userSave = $authService->isAuthenticated() ? 1 : 0;
}

function title($text)
{
    OpenTable();
    echo "<center><span class=\"title\"><strong>$text</strong></span></center>";
    CloseTable();
    echo "<br>";
}

function is_active($module)
{
    global $prefix, $db;
    static $save;
    if (is_array($save)) {
        if (isset($save[$module])) {
            return ($save[$module]);
        }
        return 0;
    }
    $sql = "SELECT title FROM " . $prefix . "_modules WHERE active=1";
    $result = $db->sql_query($sql);
    while ($row = $db->sql_fetchrow($result)) {
        $save[$row[0]] = 1;
    }
    $db->sql_freeresult($result);
    if (isset($save[$module])) {
        return ($save[$module]);
    }
    return 0;
}

function render_blocks($side, $blockfile, $title, $content, $bid, $url)
{
    if (!defined('BLOCK_FILE')) {
        define('BLOCK_FILE', true);
    }
    if (empty($blockfile)) {
        themecenterbox($title, $content);
    } else {
        blockfileinc($title, $blockfile, 1);
    }
}

function blocks($side)
{
    global $storynum, $prefix, $multilingual, $currentlang, $db, $user;
    if ($multilingual == 1) {
        $querylang = "AND (blanguage='" . $db->db_connect_id->real_escape_string($currentlang) . "' OR blanguage='')";
    } else {
        $querylang = "";
    }
    if (strtolower($side[0]) == "l") {
        $pos = "l";
    } elseif (strtolower($side[0]) == "r") {
        $pos = "r";
    } elseif (strtolower($side[0]) == "c") {
        $pos = "c";
    } elseif (strtolower($side[0]) == "d") {
        $pos = "d";
    }
    $side = $pos;
    $sql = "SELECT bid, bkey, title, content, url, blockfile, view, expire, action, subscription FROM " . $prefix . "_blocks WHERE bposition='$pos' AND active='1' $querylang ORDER BY weight ASC";
    $result = $db->sql_query($sql);
    while ($row = $db->sql_fetchrow($result)) {
        $bid = intval($row['bid']);
        $title = (!isset($row['title'])) ?: filter($row['title'], "nohtml");
        $content = (!isset($row['content'])) ?: stripslashes($row['content']);
        if ($row['url'] != NULL) {
            $url = filter($row['url'], "nohtml");
        } else {
            $url = $row['url'];
        }
        if ($row['blockfile']  != NULL) {
            $blockfile = filter($row['blockfile'], "nohtml");
        } else {
            $blockfile = $row['blockfile'];
        }
        $view = intval($row['view']);
        $expire = intval($row['expire']);
        $action = filter($row['action'], "nohtml");
        $action = substr($action, 0, 1);
        $now = time();
        $sub = intval($row['subscription']);
        if ($sub == 0 or $sub == 1) {
            if ($expire != 0 and $expire <= $now) {
                if ($action == "d") {
                    $db->sql_query("UPDATE " . $prefix . "_blocks SET active='0', expire='0' WHERE bid='$bid'");
                    return;
                } elseif ($action == "r") {
                    $db->sql_query("DELETE FROM " . $prefix . "_blocks WHERE bid='$bid'");
                    return;
                }
            }
            if (empty($row['bkey'])) {
                if ($view == 0) {
                    render_blocks($side, $blockfile, $title, $content, $bid, $url);
                } elseif ($view == 1 and is_user($user) || is_admin()) {
                    render_blocks($side, $blockfile, $title, $content, $bid, $url);
                } elseif ($view == 2 and is_admin()) {
                    render_blocks($side, $blockfile, $title, $content, $bid, $url);
                } elseif ($view == 3 and !is_user($user) || is_admin()) {
                    render_blocks($side, $blockfile, $title, $content, $bid, $url);
                }
            }
        }
    }
    $db->sql_freeresult($result);
}

function online()
{
    global $user, $cookie, $prefix, $db;
    $ip = $_SERVER['REMOTE_ADDR'];
    if (is_user($user)) {
        cookiedecode($user);
        $uname = $cookie[1];
        $guest = 0;
    } else {
        $uname = $ip;
        $guest = 1;
    }
    if (mt_rand(1, 100) === 1) {
        $past = time() - 3600;
        $sql = "DELETE FROM " . $prefix . "_session WHERE time < '$past'";
        $db->sql_query($sql);
    }
    $sql = "SELECT time FROM " . $prefix . "_session WHERE uname='" . $db->db_connect_id->real_escape_string($uname) . "'";
    $result = $db->sql_query($sql);
    $ctime = time();
    if (!empty($uname)) {
        $uname = substr($uname, 0, 25);
        $row = $db->sql_fetchrow($result);
        if ($row) {
            $db->sql_query("UPDATE " . $prefix . "_session SET uname='" . $db->db_connect_id->real_escape_string($uname) . "', time='$ctime', host_addr='" . $db->db_connect_id->real_escape_string($ip) . "', guest='$guest' WHERE uname='" . $db->db_connect_id->real_escape_string($uname) . "'");
        } else {
            $db->sql_query("INSERT INTO " . $prefix . "_session (uname, time, host_addr, guest) VALUES ('" . $db->db_connect_id->real_escape_string($uname) . "', '$ctime', '" . $db->db_connect_id->real_escape_string($ip) . "', '$guest')");
        }
    }
    $db->sql_freeresult($result);
}

function blockfileinc($title, $blockfile, $side = 0)
{
    $blockfiletitle = $title;
    $file = file_exists("blocks/" . $blockfile . "");
    if (!$file) {
        $content = _BLOCKPROBLEM;
    } else {
        include "blocks/" . $blockfile . "";
    }
    if (empty($content)) {
        $content = _BLOCKPROBLEM2;
    }
    themecenterbox($blockfiletitle, $content);
}

function cookiedecode($user)
{
    global $cookie, $authService;
    $cookieArray = $authService->getCookieArray();
    if ($cookieArray !== null) {
        $cookie = $cookieArray;
        return $cookie;
    }
    return null;
}

function getusrinfo($user)
{
    global $userinfo, $authService;
    $info = $authService->getUserInfo();
    if ($info !== null) {
        $userinfo = $info;
        return $userinfo;
    }
    unset($userinfo);
    return null;
}

function FixQuotes($what = "")
{
    while (stristr($what, "\\\\'")) {
        $what = str_replace("\\\\'", "'", $what);
    }
    return $what;
}

function check_words($Message)
{
    global $CensorMode, $CensorReplace, $EditedMessage, $CensorList;
    $EditedMessage = $Message;
    if ($CensorMode != 0) {
        if (is_array($CensorList)) {
            $Replace = $CensorReplace;
            if ($CensorMode == 1) {
                for ($i = 0; $i < count($CensorList); $i++) {
                    $EditedMessage = preg_replace('/' . $CensorList[$i] . '([^a-zA-Z0-9])/i', $Replace . '\\1', $EditedMessage);
                }
            } elseif ($CensorMode == 2) {
                for ($i = 0; $i < count($CensorList); $i++) {
                    $EditedMessage = preg_replace('/(^|[^a-zA-Z0-9])' . $CensorList[$i] . '/i', '\\1' . $Replace, $EditedMessage);
                }
            } elseif ($CensorMode == 3) {
                for ($i = 0; $i < count($CensorList); $i++) {
                    $EditedMessage = preg_replace('/' . $CensorList[$i] . '/i', $Replace, $EditedMessage);
                }
            }
        }
    }
    return ($EditedMessage);
}

function delQuotes($string)
{
    $tmp = "";
    $result = "";
    $i = 0;
    $attrib = -1;
    $quote = 0;
    $len = strlen($string);
    while ($i < $len) {
        switch ($string[$i]) {
        case "\"":
            if ($quote == 0) {
                $quote = 1;
            } else {
                $quote = 0;
                if (($attrib > 0) && ($tmp != "")) {$result .= "=\"$tmp\"";}
                $tmp = "";
                $attrib = -1;
            }
                break;
            case "=":
                if ($quote == 0) {
                $attrib = 1;
                    if ($tmp != "") {
                        $result .= " $tmp";
                    }
                    $tmp = "";
                } else {
                    $tmp .= '=';
                }
                break;
            case " ":
                if ($attrib > 0) {
                $tmp .= $string[$i];
                }
                break;
            default:
                if ($attrib < 0) {
                    $attrib = 0;
                }
                $tmp .= $string[$i];
                break;
        }
        $i++;
    }
    if (($quote != 0) && ($tmp != "")) {
        if ($attrib == 1) {
            $result .= "=";
        }
        $result .= "\"$tmp\"";
    }
    return $result;
}

function check_html($str, $strip = "")
{
    global $AllowableHTML;
    if ($strip == "nohtml") {
        $AllowableHTML = array('');
    }

    $str = stripslashes($str);
    $str = preg_replace('/<\s*([^>]*)\s*>/i', '<\\1>', $str);
    $str = preg_replace('/<a[^>]*href\s*=\s*"?\s*([^" >]*)\s*"?[^>]*>/i', '<a href="\\1">', $str);
    $str = preg_replace('/<\s* img\s*([^>]*)\s*>/i', '', $str);
    $str = preg_replace('/<a[^>]*href\s*=\s*"?javascript[[:punct:]]*"?[^>]*>/i', '', $str);
    $tmp = "";
    while (preg_match('/<(\/?[a-zA-Z]*)\s*([^>]*)>/', $str, $reg)) {
        $i = strpos($str, $reg[0]);
        $l = strlen($reg[0]);
        if ($reg[1][0] == "/") {
            $tag = strtolower(substr($reg[1], 1));
        } else {
            $tag = strtolower($reg[1]);
        }

        if ($a = $AllowableHTML[$tag] ?? 0) {
            if ($reg[1][0] == "/") {
                $tag = "</$tag>";
            } elseif (($a == 1) || ($reg[2] == "")) {
                $tag = "<$tag>";
            } else {
                $attrb_list = delQuotes($reg[2]);
                $tag = "<$tag" . $attrb_list . ">";
            }
        } else {
            $tag = "";
        }

        $tmp .= substr($str, 0, $i) . $tag;
        $str = substr($str, $i + $l);
    }
    $str = $tmp . $str;
    return $str;
}

function filter($what, $strip = "", $save = "", $type = "")
{
    if ($strip == "nohtml") {
        $what = check_html($what, $strip);
        $what = htmlentities(trim($what), ENT_QUOTES);
        if ($type != "preview" and $save != 1) {
            $what = html_entity_decode($what, ENT_QUOTES);
        }
    }
    if ($save == 1) {
        $what = check_words($what);
        $what = check_html($what, $strip);
        $what = addslashes($what);
    } else {
        $what = stripslashes(FixQuotes($what));
        $what = check_words($what);
        $what = check_html($what, $strip);
    }
    return ($what);
}

function formatTimestamp($time)
{
    global $datetime, $locale;
    setlocale(LC_TIME, $locale);
    if (!is_numeric($time)) {
        preg_match('/([0-9]{4})-([0-9]{1,2})-([0-9]{1,2}) ([0-9]{1,2}):([0-9]{1,2}):([0-9]{1,2})/', $time, $datetime);
        $time = gmmktime($datetime[4], $datetime[5], $datetime[6], $datetime[2], $datetime[3], $datetime[1]);
    }
    $time -= date("Z");
    $datetime = date(_DATESTRING, $time);
    $datetime = ucfirst($datetime);
    return $datetime;
}

function get_author($aid)
{
    global $prefix, $db;
    static $users;
    if (isset($users[$aid]) and is_array($users[$aid])) {
        $row = $users[$aid];
    } else {
        $sql = "SELECT url, email FROM " . $prefix . "_authors WHERE aid='" . $db->db_connect_id->real_escape_string($aid) . "'";
        $result = $db->sql_query($sql);
        $row = $db->sql_fetchrow($result);
        $users[$aid] = $row;
        $db->sql_freeresult($result);
    }
    $aidurl = filter($row['url'], "nohtml");
    $aidmail = filter($row['email'], "nohtml");
    if (isset($aidurl) && $aidurl != "http://") {
        $aid = "<a href=\"" . $aidurl . "\">$aid</a>";
    } elseif (isset($aidmail)) {
        $aid = "<a href=\"mailto:" . $aidmail . "\">$aid</a>";
    } else {
        $aid = $aid;
    }
    return $aid;
}

function formatAidHeader($aid)
{
    $AidHeader = get_author($aid);
    echo $AidHeader;
}


function loginbox(): void
{
    global $user, $authService;
    if (!$authService->isAuthenticated()) {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        if (is_string($queryString) && $queryString !== '') {
            $_SESSION['redirect_after_login'] = $queryString;
        }
        $url = 'modules.php?name=YourAccount';
        $jsUrl = addslashes($url);
        echo '<script>window.location.href="' . $jsUrl . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $jsUrl . '"></noscript>';
        die();
    }
}

function getTopics($s_sid)
{
    global $topicid, $topicname, $topicimage, $topictext, $prefix, $db;
    $sid = intval($s_sid);
    $result = $db->sql_query("SELECT t.topicid, t.topicname, t.topicimage, t.topictext FROM " . $prefix . "_stories s LEFT JOIN " . $prefix . "_topics t ON t.topicid = s.topic WHERE s.sid = " . $sid);
    $row = $db->sql_fetchrow($result);
    $db->sql_freeresult($result);
    $topicid = intval($row['topicid']);
    $topicname = filter($row['topicname'], "nohtml");
    $topicimage = filter($row['topicimage'], "nohtml");
    $topictext = filter($row['topictext'], "nohtml");
}


