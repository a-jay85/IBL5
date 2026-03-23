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


