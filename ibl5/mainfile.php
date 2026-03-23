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

$ua = $_SERVER['HTTP_USER_AGENT'];
if (preg_match('/facebookexternalhit/si',$ua)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden';
    die();
}

// End the transaction
if (!defined('END_TRANSACTION')) {
    define('END_TRANSACTION', 2);
}

// SECURITY: Safe include function with path traversal protection
// Note: This function is legacy. New code should use explicit includes with verified paths.
function include_secure($file_name)
{
    // Remove any path traversal attempts
    $file_name = preg_replace("/\.[\.\/]*\//", "", $file_name);

    // Additional protection: use basename to strip directory components
    // and verify the file exists in expected location
    $base_name = basename($file_name);

    // Only allow alphanumeric, underscore, dash, and .php extension
    if (!preg_match('/^[a-zA-Z0-9_\-]+\.php$/', $base_name)) {
        return;
    }

    // Reconstruct with just the directory and safe basename
    $dir = dirname($file_name);
    if ($dir === '.' || $dir === '') {
        $safe_path = $base_name;
    } else {
        // Validate directory doesn't contain traversal
        $dir = str_replace(['..', "\0"], '', $dir);
        $safe_path = $dir . '/' . $base_name;
    }

    if (file_exists($safe_path)) {
        include_once $safe_path;
    }
}

// Check if this file isn't being accessed directly

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    header("Location: index.php");
    exit();
}

if (isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'], 'compatible')) {
    if (extension_loaded('zlib')) {
        @ob_end_clean();
        ob_start('ob_gzhandler');
    }
} elseif (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && $_SERVER['HTTP_ACCEPT_ENCODING'] !== '') {
    if (strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
        if (extension_loaded('zlib')) {
            $do_gzip_compress = true;
            ob_start('ob_gzhandler', 5);
            ob_implicit_flush(0);
            if (str_contains($_SERVER['HTTP_USER_AGENT'], 'MSIE')) {
                header('Content-Encoding: gzip');
            }
        }
    }
}

// Load Composer autoloader for IBL5 classes and third-party packages
require_once __DIR__ . '/vendor/autoload.php';

// In git worktrees, vendor/ is symlinked to the main repo. Composer resolves __DIR__
// through the symlink, so it loads classes from the main repo instead of the worktree.
// Prepend the worktree's classes/ directory so modified files are used at runtime.
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

// SECURITY: Configure secure session cookie parameters before session_start()
if (session_status() === PHP_SESSION_NONE) {
    // Detect HTTPS
    $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === 443);

    // Set secure session cookie options
    session_set_cookie_params([
        'lifetime' => 15552000,  // 6 months (180 days)
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,          // Only HTTPS
        'httponly' => true,            // Prevent JavaScript access
        'samesite' => 'Lax',           // CSRF protection (Lax for login redirects)
    ]);

    // Match server-side session lifetime to cookie lifetime (6 months)
    ini_set('session.gc_maxlifetime', '15552000');

    session_start();
}

// SECURITY: Set HTTP security headers (only if headers haven't been sent)
if (!headers_sent()) {
    // Prevent MIME-sniffing attacks
    header('X-Content-Type-Options: nosniff');

    // Prevent clickjacking by disallowing framing
    header('X-Frame-Options: SAMEORIGIN');

    // Control referrer information leakage
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Basic Content Security Policy (allows inline scripts/styles for legacy compatibility)
    // Note: A stricter CSP with nonces would require refactoring all inline scripts
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.google.com https://www.gstatic.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; frame-src https://www.google.com; connect-src 'self'");

    // Enable HTTPS-only in production (HSTS)
    $isProduction = ($_SERVER['SERVER_NAME'] ?? '') !== 'localhost';
    if ($isProduction && isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Hydrate session from cookie if not set
if (!isset($_SESSION['current_league']) && isset($_COOKIE[\League\LeagueContext::COOKIE_NAME])) {
    $cookieLeague = $_COOKIE[\League\LeagueContext::COOKIE_NAME];
    if (in_array($cookieLeague, [\League\LeagueContext::LEAGUE_IBL, \League\LeagueContext::LEAGUE_OLYMPICS], true)) {
        $_SESSION['current_league'] = $cookieLeague;
    }
}

// Initialize global LeagueContext instance for application-wide use
$leagueContext = new \League\LeagueContext();

// Persist league selection when user switches leagues via URL parameter
if (isset($_GET['league']) && in_array($_GET['league'], [\League\LeagueContext::LEAGUE_IBL, \League\LeagueContext::LEAGUE_OLYMPICS], true)) {
    $leagueContext->setLeague($_GET['league']);
}

// SECURITY: Denylist of critical globals that MUST NEVER be overwritten via $_REQUEST
// These include database credentials, connection objects, and authentication variables
$_protected_globals = [
    // Database credentials (from config.php)
    'dbhost', 'dbuname', 'dbpass', 'dbname', 'prefix', 'user_prefix',
    // Database connection objects
    'db', 'mysqli_db',
    // Authentication state
    'user', 'cookie', 'userinfo',
    // PHP-Nuke core configuration
    'nukeurl', 'sitename', 'adminmail',
    // Session/superglobals
    '_SESSION', '_COOKIE', '_SERVER', '_ENV', '_FILES', '_GET', '_POST', '_REQUEST',
    // Internal PHP
    'GLOBALS', 'this',
    // League context
    'leagueContext',
    // Authentication service
    'authService',
];

$sanitize_rules = array("newlang" => "/[a-z][a-z]/i", "redirect" => "/[a-z0-9]*/i");
foreach ($_REQUEST as $key => $value) {
    // Skip protected globals entirely
    if (in_array($key, $_protected_globals, true)) {
        continue;
    }
    if (!isset($sanitize_rules[$key]) || preg_match($sanitize_rules[$key], $value)) {
        $GLOBALS[$key] = $value;
    }
}

// Auth is now session-based via AuthService — legacy admin/user cookies are ignored

// Include the required files - single config for all leagues
// Olympics uses the same database with different table names (handled by LeagueContext)
require_once __DIR__ . '/config.php';

if (!$dbname) {
    die("<br><br><center><img src=images/logo.gif><br><br><b>There seems that PHP-Nuke isn't installed yet.<br>(The values in config.php file are the default ones)<br><br>You can proceed with the <a href='./install/index.php'>web installation</a> now.</center></b>");
}

require_once __DIR__ . "/db/db.php";

// Initialize structured logging (Monolog with daily rotation + JSON output)
\Logging\LoggerFactory::fromConfig();

// Initialize session-based AuthService for user authentication
$authService = new \Auth\AuthService($mysqli_db);

// Attempt to restore session from "remember me" cookie for returning users
$authService->tryRememberMe();

// Populate legacy $user global so modules.php and other code that calls
// base64_decode($user) continues to work during the migration period.
$user = '';
if ($authService->isAuthenticated()) {
    $cookieArray = $authService->getCookieArray();
    if ($cookieArray !== null) {
        $user = base64_encode(implode(':', $cookieArray));
    }
}

// Demo mode: block all state-mutating requests with a user-friendly page
// Uses 200 instead of 403 because Chrome replaces 403 response bodies
// with its own "Access Denied" error page, hiding our explanation.
// Must flush/end output buffers since ob_start('ob_gzhandler') was called earlier.
if (($_SESSION['demo_mode'] ?? false) === true && $_SERVER['REQUEST_METHOD'] === 'POST') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    require_once __DIR__ . '/includes/demo-403.php';
    exit;
}

require_once __DIR__ . "/includes/ipban.php";
if (file_exists(__DIR__ . "/includes/custom_files/custom_mainfile.php")) {
    @include_once __DIR__ . "/includes/custom_files/custom_mainfile.php";
}

define('NUKE_FILE', true);
$row = $db->sql_fetchrow($db->sql_query("SELECT * FROM " . $prefix . "_config"));
$sitename = filter($row['sitename'], "nohtml");
$nukeurl = filter($row['nukeurl'], "nohtml");
$site_logo = filter($row['site_logo'], "nohtml");
$slogan = filter($row['slogan'], "nohtml");
$startdate = filter($row['startdate'], "nohtml");
$adminmail = filter($row['adminmail'], "nohtml");
$anonpost = intval($row['anonpost']);
$Default_Theme = filter($row['Default_Theme'], "nohtml");
$foot1 = filter($row['foot1']);
$foot2 = filter($row['foot2']);
$foot3 = filter($row['foot3']);
$commentlimit = intval($row['commentlimit']);
$anonymous = filter($row['anonymous'], "nohtml");
$minpass = intval($row['minpass']);
$pollcomm = intval($row['pollcomm']);
$articlecomm = intval($row['articlecomm']);
$broadcast_msg = intval($row['broadcast_msg']);
$my_headlines = intval($row['my_headlines']);
$top = intval($row['top']);
$storyhome = intval($row['storyhome']);
$user_news = intval($row['user_news']);
$oldnum = intval($row['oldnum']);
$ultramode = intval($row['ultramode']);
$banners = intval($row['banners']);
$backend_title = filter($row['backend_title'], "nohtml");
$backend_language = filter($row['backend_language'], "nohtml");
$language = filter($row['language'], "nohtml");
$locale = filter($row['locale'], "nohtml");
$multilingual = intval($row['multilingual']);
$useflags = intval($row['useflags']);
$notify = intval($row['notify']);
$notify_email = filter($row['notify_email'], "nohtml");
$notify_subject = filter($row['notify_subject'], "nohtml");
$notify_message = filter($row['notify_message'], "nohtml");
$notify_from = filter($row['notify_from'], "nohtml");
$moderate = intval($row['moderate']);
$admingraphic = intval($row['admingraphic']);
$CensorMode = intval($row['CensorMode']);
$CensorReplace = filter($row['CensorReplace'], "nohtml");
$copyright = filter($row['copyright']);
$Version_Num = floatval($row['Version_Num']);
$domain = str_replace("http://", "", $nukeurl);
$display_errors = filter($row['display_errors']);
$nuke_editor = intval($row['nuke_editor']);
$mtime = microtime();
$mtime = explode(" ", $mtime);
$mtime = $mtime[1] + $mtime[0];
$start_time = $mtime;
$pagetitle = "";

// Error reporting, to be set in config.php
error_reporting(E_ERROR);
if ($display_errors == 1) {
    @ini_set('display_errors', 1);
} else {
    @ini_set('display_errors', 0);
}

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





