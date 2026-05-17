<?php

/************************************************************************/
/* PHP-NUKE: Advanced Content Management System                         */
/* ============================================                         */
/*                                                                      */
/* Copyright (c) 2007 by Francisco Burzi                                */
/* http://phpnuke.org                                                   */
/*                                                                      */
/* This program is free software. You can redistribute it and/or modify */
/* it under the terms of the GNU General Public License as published by */
/* the Free Software Foundation; either version 2 of the License.       */
/************************************************************************/

// Check if this file isn't being accessed directly
if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    header("Location: index.php");
    exit();
}

// --- Bootstrap: Application pipeline replaces procedural blocks ---
// SecurityBootstrap handles: FB bot early-exit, END_TRANSACTION constant, gzip ob_start
// SessionBootstrap handles: session cookie params + session_start
// HeadersBootstrap handles: X-Frame-Options, CSP, HSTS
// LeagueBootstrap handles: league context hydration from cookie/URL
// ConfigBootstrap handles: protected globals, config.php, db.php, LoggerFactory, nuke_config query, error reporting
// AuthBootstrap handles: AuthService init, remember-me, dev auto-login, legacy $user global
// DemoModeBootstrap handles: demo mode POST blocking

require_once __DIR__ . '/vendor/autoload.php';

// In git worktrees, vendor/ is symlinked to the main repo. Prepend the worktree's
// classes/ directory so modified files are used at runtime.
if (is_link(__DIR__ . '/vendor')) {
    $worktreeClasses = realpath(__DIR__ . '/classes');
    if ($worktreeClasses !== false) {
        spl_autoload_register(static function (string $class) use ($worktreeClasses): void {
            $file = $worktreeClasses . '/' . str_replace('\\', '/', $class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }, true, true);
    }
}

$bootApp = \Bootstrap\WebApplicationFactory::build(__DIR__);
$bootApp->boot();

// --- Legacy function definitions ---
// These remain as global functions for PHP-Nuke module compatibility.
// Plan C (LegacyFunctions reconciliation) will consolidate them.

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
    // Session-based auth via AuthService — the $admin parameter is kept for signature compat
    global $authService;
    static $adminSave;
    if (isset($adminSave)) {
        return $adminSave;
    }
    return $adminSave = $authService->isAdmin() ? 1 : 0;
}

function is_user($user)
{
    // Session-based auth via AuthService — the $user parameter is kept for signature compat
    global $authService;
    static $userSave;
    if (isset($userSave)) {
        return $userSave;
    }
    return $userSave = $authService->isAuthenticated() ? 1 : 0;
}


function blocks($side)
{
    global $storynum, $prefix, $multilingual, $currentlang, $db, $user;
    if ($multilingual == 1) {
        $querylang = "AND (blanguage='$currentlang' OR blanguage='')";
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
                $shouldRender = ($view == 0)
                    || ($view == 1 and is_user($user) || is_admin())
                    || ($view == 2 and is_admin())
                    || ($view == 3 and !is_user($user) || is_admin());
                if ($shouldRender) {
                    if (!defined('BLOCK_FILE')) {
                        define('BLOCK_FILE', true);
                    }
                    if (empty($blockfile)) {
                        themecenterbox($title, $content);
                    } else {
                        $blockfiletitle = $title;
                        if (!file_exists("blocks/" . $blockfile)) {
                            $content = _BLOCKPROBLEM;
                        } else {
                            include "blocks/" . $blockfile;
                        }
                        if (empty($content)) {
                            $content = _BLOCKPROBLEM2;
                        }
                        themecenterbox($blockfiletitle, $content);
                    }
                }
            }
        }
    }
    $db->sql_freeresult($result);
}



function cookiedecode($user)
{
    // Session-based auth — populate global $cookie from AuthService
    global $cookie, $authService;
    $cookieArray = $authService->getCookieArray();
    if ($cookieArray !== null) {
        $cookie = $cookieArray;
        return $cookie;
    }
    return null;
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
    /* no recursive function to add quote to an HTML tag if needed */
    /* and delete duplicate spaces between attribs. */
    $tmp = ""; # string buffer
    $result = ""; # result string
    $i = 0;
    $attrib = -1; # Are us in an HTML attrib ?   -1: no attrib   0: name of the attrib   1: value of the atrib
    $quote = 0; # Is a string quote delimited opened ? 0=no, 1=yes
    $len = strlen($string);
    while ($i < $len) {
        switch ($string[$i]) { # What car is it in the buffer ?
        case "\"": #"       # a quote.
            if ($quote == 0) {
                $quote = 1;
            } else {
                $quote = 0;
                if (($attrib > 0) && ($tmp != "")) {$result .= "=\"$tmp\"";}
                $tmp = "";
                $attrib = -1;
            }
                break;
            case "=": # an equal - attrib delimiter
                if ($quote == 0) { # Is it found in a string ?
                $attrib = 1;
                    if ($tmp != "") {
                        $result .= " $tmp";
                    }

                    $tmp = "";
                } else {
                    $tmp .= '=';
                }

                break;
            case " ": # a blank ?
                if ($attrib > 0) { # add it to the string, if one opened.
                $tmp .= $string[$i];
                }
                break;
            default: # Other
                if ($attrib < 0) # If we weren't in an attrib, set attrib to 0
                {
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

        /* If it is the value of an atrib, add the '=' */
        $result .= "\"$tmp\""; /* Add quote if needed (the reason of the function ;-) */
    }
    return $result;
}

function check_html($str, $strip = "")
{
    /* The core of this code has been lifted from phpslash */
    /* which is licenced under the GPL. */
    global $AllowableHTML;
    if ($strip == "nohtml") {
        $AllowableHTML = array('');
    }

    $str = stripslashes($str);
    $str = preg_replace('/<\s*([^>]*)\s*>/i', '<\\1>', $str);
    // Delete all spaces from html tags .
    $str = preg_replace('/<a[^>]*href\s*=\s*"?\s*([^" >]*)\s*"?[^>]*>/i', '<a href="\\1">', $str);
    // Delete all attribs from Anchor, except an href, double quoted.
    $str = preg_replace('/<\s* img\s*([^>]*)\s*>/i', '', $str);
    // Delete all img tags
    $str = preg_replace('/<a[^>]*href\s*=\s*"?javascript[[:punct:]]*"?[^>]*>/i', '', $str);
    // Delete javascript code from a href tags -- Zhen-Xjell @ http://nukecops.com
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
                # Place here the double quote fix function.
                $attrb_list = delQuotes($reg[2]);
                // A VER
                //$attrb_list = preg_replace("/&/","&amp;",$attrb_list);
                $tag = "<$tag" . $attrb_list . ">";
            }
        }
        # Attribs in tag allowed
        else {
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
        // If the variable $what doesn't comes from a preview screen should be converted
        if ($type != "preview" and $save != 1) {
            $what = html_entity_decode($what, ENT_QUOTES);
        }
    }
    if ($save == 1) {
        $what = check_words($what);
        $what = check_html($what, $strip);
        $what = addslashes($what);
    } else {
        $fixedWhat = $what;
        while (stristr($fixedWhat, "\\\\'")) {
            $fixedWhat = str_replace("\\\\'", "'", $fixedWhat);
        }
        $what = stripslashes($fixedWhat);
        $what = check_words($what);
        $what = check_html($what, $strip);
    }
    return ($what);
}


// --- Post-bootstrap runtime setup ---

if (!defined('FORUM_ADMIN')) {
    if ((isset($newlang)) and (stristr($newlang, "."))) {
        if (file_exists("language/lang-" . $newlang . ".php")) {
            setcookie("lang", $newlang, time() + 31536000);
            include_secure("language/lang-" . $newlang . ".php");
            $currentlang = $newlang;
        } else {
            setcookie("lang", $language, time() + 31536000);
            include_secure("language/lang-" . $language . ".php");
            $currentlang = $language;
        }
    } elseif (isset($lang)) {
        include_secure("language/lang-" . $lang . ".php");
        $currentlang = $lang;
    } else {
        setcookie("lang", $language, time() + 31536000);
        include_secure("language/lang-" . $language . ".php");
        $currentlang = $language;
    }
}

if (!defined('FORUM_ADMIN')) {
    $ThemeSel = 'IBL';
    include_once "themes/$ThemeSel/theme.php";
}


function loginbox(): void
{
    global $user, $authService;
    if (!$authService->isAuthenticated()) {
        // Store the full original query string in session so login() can redirect back
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        if (is_string($queryString) && $queryString !== '') {
            $_SESSION['redirect_after_login'] = $queryString;
        }
        $url = 'modules.php?name=YourAccount';
        // Use JS redirect — callers have already sent output via PageLayout::header()
        $jsUrl = addslashes($url);
        echo '<script>window.location.href="' . $jsUrl . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . $jsUrl . '"></noscript>';
        die();
    }
}

require_once __DIR__ . '/includes/buildRedirectUrl.php';
