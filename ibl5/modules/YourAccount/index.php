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
$userpage = 1;

if (isset($username) && (preg_match('/[^a-zA-Z0-9_-]/', $username))) {
    die("Illegal username...");
}

function userCheck($username, $user_email)
{
    $username = filter($username, "nohtml", 1);
    $user_email = filter($user_email, "nohtml", 1);
    global $stop, $user_prefix, $db;
    if ((!$user_email) || (empty($user_email)) || (filter_var($user_email, FILTER_VALIDATE_EMAIL) === false)) {
        $stop = "<center>" . _ERRORINVEMAIL . "</center><br>";
    }

    if (strrpos($user_email, ' ') > 0) {
        $stop = "<center>" . _ERROREMAILSPACES . "</center>";
    }

    if ((!$username) || (empty($username)) || (preg_match('/[^a-zA-Z0-9_-]/', $username))) {
        $stop = "<center>" . _ERRORINVNICK . "</center><br>";
    }

    if (strlen($username) > 25) {
        $stop = "<center>" . _NICK2LONG . "</center>";
    }

    if (preg_match('/^((root)|(adm)|(linux)|(webmaster)|(admin)|(god)|(administrator)|(administrador)|(nobody)|(anonymous)|(anonimo)|(an\x{00e1}nimo)|(operator)|(JackFromWales4u2))$/iu', $username)) {
        $stop = "<center>" . _NAMERESERVED . "</center>";
    }

    if (strrpos($username, ' ') > 0) {
        $stop = "<center>" . _NICKNOSPACES . "</center>";
    }

    if (\DatabaseConnection::fetchValue("SELECT COUNT(*) FROM nuke_users WHERE username = ?", [$username]) > 0) {
        $stop = "<center>" . _NICKTAKEN . "</center><br>";
    }

    if (\DatabaseConnection::fetchValue("SELECT COUNT(*) FROM nuke_users_temp WHERE username = ?", [$username]) > 0) {
        $stop = "<center>" . _NICKTAKEN . "</center><br>";
    }

    if (\DatabaseConnection::fetchValue("SELECT COUNT(*) FROM nuke_users WHERE user_email = ?", [$user_email]) > 0) {
        $stop = "<center>" . _EMAILREGISTERED . "</center><br>";
    }

    if (\DatabaseConnection::fetchValue("SELECT COUNT(*) FROM nuke_users_temp WHERE user_email = ?", [$user_email]) > 0) {
        $stop = "<center>" . _EMAILREGISTERED . "</center><br>";
    }

    return $stop;
}

function confirmNewUser($username, $user_email, $user_password, $user_password2, $random_num, $gfx_check)
{
    global $stop, $EditedMessage, $sitename, $module_name, $minpass;
    Nuke\Header::header();
    include "config.php";
    $username = substr(htmlspecialchars(str_replace("\'", "'", trim($username))), 0, 25);
    $username = rtrim($username, "\\");
    $username = str_replace("'", "\'", $username);
    $user_email = filter($user_email, "nohtml");
    $user_viewemail = "0";
    userCheck($username, $user_email);
    $user_email = validate_mail($user_email);
    $user_password = stripslashes($user_password);
    $user_password2 = stripslashes($user_password2);
    if (!$stop) {
        $datekey = date("F j");
        $rcode = hexdec(md5($_SERVER['HTTP_USER_AGENT'] . $sitekey . $_POST['random_num'] . $datekey));
        $code = substr($rcode, 2, 6);
        if (extension_loaded("gd") and $code != $gfx_check and ($gfx_chk == 3 or $gfx_chk == 4 or $gfx_chk == 6 or $gfx_chk == 7)) {
            title("" . _NEWUSERERROR . "");
            OpenTable();
            echo "<center><b>" . _SECCODEINCOR . "</b><br><br>"
                . "" . _GOBACK . "</center>";
            CloseTable();
            Nuke\Footer::footer();
            die();
        }
        if (empty($user_password) and empty($user_password2)) {
            $user_password = makepass();
        } elseif ($user_password != $user_password2) {
            title("" . _NEWUSERERROR . "");
            OpenTable();
            echo "<center><b>" . _PASSDIFFERENT . "</b><br><br>" . _GOBACK . "</center>";
            CloseTable();
            Nuke\Footer::footer();
            die();
        } elseif ($user_password == $user_password2 and strlen($user_password) < $minpass) {
            title("" . _NEWUSERERROR . "");
            OpenTable();
            echo "<center>" . _YOUPASSMUSTBE . " <b>$minpass</b> " . _CHARLONG . "<br><br>" . _GOBACK . "</center>";
            CloseTable();
            Nuke\Footer::footer();
            die();
        }
        title("$sitename: " . _USERREGLOGIN . "");
        OpenTable();
        echo "<center><b>" . _USERFINALSTEP . "</b><br><br>$username, " . _USERCHECKDATA . "</center><br><br>"
            . "<table align='center' border='0'>"
            . "<tr><td><b>" . _UUSERNAME . ":</b> $username<br></td></tr>"
            . "<tr><td><b>" . _EMAIL . ":</b> $user_email</td></tr></table><br><br>"
            . "<center><b>" . _NOTE . "</b> " . _YOUWILLRECEIVE . "";
        echo "<form action=\"modules.php?name=$module_name\" method=\"post\">"
            . "<input type=\"hidden\" name=\"random_num\" value=\"$random_num\">"
            . "<input type=\"hidden\" name=\"gfx_check\" value=\"$gfx_check\">"
            . "<input type=\"hidden\" name=\"username\" value=\"$username\">"
            . "<input type=\"hidden\" name=\"user_email\" value=\"$user_email\">"
            . "<input type=\"hidden\" name=\"user_password\" value=\"$user_password\">"
            . "<input type=\"hidden\" name=\"op\" value=\"finish\"><br><br>"
            . "<input type=\"submit\" value=\"" . _FINISH . "\"> &nbsp;&nbsp;" . _GOBACK . "</form></center>";
        CloseTable();
    } else {
        OpenTable();
        echo "<center><font class=\"title\"><b>Registration Error!</b></font><br><br>";
        echo "<font class=\"content\">$stop<br>" . _GOBACK . "</font></center>";
        CloseTable();
    }
    Nuke\Footer::footer();
}

function finishNewUser($username, $user_email, $user_password, $random_num, $gfx_check)
{
    global $stop, $EditedMessage, $adminmail, $sitename, $Default_Theme, $user_prefix, $db, $storyhome, $module_name, $nukeurl, $authService;
    Nuke\Header::header();
    include "config.php";
    userCheck($username, $user_email);
    $user_email = validate_mail($user_email);
    $user_regdate = date("M d, Y");
    $user_password = stripslashes($user_password);
    if (!isset($stop)) {
        $datekey = date("F j");
        $rcode = hexdec(md5($_SERVER['HTTP_USER_AGENT'] . $sitekey . $random_num . $datekey));
        $code = substr($rcode, 2, 6);
        if (extension_loaded("gd") and $code != $gfx_check and ($gfx_chk == 3 or $gfx_chk == 4 or $gfx_chk == 6 or $gfx_chk == 7)) {
            Header("Location: modules.php?name=$module_name");
            die();
        }
        mt_srand((double) microtime() * 1000000);
        $maxran = 1000000;
        $check_num = mt_rand(0, $maxran);
        $check_num = md5($check_num);
        $time = time();
        $finishlink = "$nukeurl/modules.php?name=$module_name&op=activate&username=$username&check_num=$check_num";
        $new_password = $authService->hashPassword($user_password);
        $username = substr(htmlspecialchars(str_replace("\'", "'", trim($username))), 0, 25);
        $username = rtrim($username, "\\");
        $username = str_replace("'", "\'", $username);
        $user_email = filter($user_email, "nohtml", 1);
        $result = \DatabaseConnection::query("INSERT INTO nuke_users_temp (user_id, username, user_email, user_password, user_regdate, check_num, time) VALUES (NULL, ?, ?, ?, ?, ?, ?)", [$username, $user_email, $new_password, $user_regdate, $check_num, $time]);
        if (!$result) {
            echo "" . _ERROR . "<br>";
        } else {
            $message = "" . _WELCOMETO . " $sitename!\n\n" . _YOUUSEDEMAIL . " ($user_email) " . _TOREGISTER . " $sitename.\n\n " . _TOFINISHUSER . "\n\n $finishlink\n\n " . _FOLLOWINGMEM . "\n\n" . _UNICKNAME . " $username\n" . _UPASSWORD . " $user_password";
            $subject = "" . _ACTIVATIONSUB . "";
            $from = "$adminmail";
            mail($user_email, $subject, $message, "From: $from\nX-Mailer: PHP/" . phpversion());
            title("$sitename: " . _USERREGLOGIN . "");
            OpenTable();
            echo "<center><b>" . _ACCOUNTCREATED . "</b><br><br>";
            echo "" . _YOUAREREGISTERED . ""
                . "<br><br>"
                . "" . _FINISHUSERCONF . "<br><br>"
                . "" . _THANKSUSER . " $sitename!</center>";
            CloseTable();
        }
    } else {
        echo "$stop";
    }
    Nuke\Footer::footer();
}

function activate($username, $check_num)
{
    global $db, $user_prefix, $module_name, $language, $prefix;
    $username = substr(htmlspecialchars(str_replace("\'", "'", trim($username))), 0, 25);
    $username = rtrim($username, "\\");
    $username = str_replace("'", "\'", $username);
    $past = time() - 86400;
    \DatabaseConnection::query("DELETE FROM nuke_users_temp WHERE time < ?", [$past]);
    $row = \DatabaseConnection::fetchRow("SELECT * FROM nuke_users_temp WHERE username = ? AND check_num = ?", [$username, $check_num]);
    if ($row !== null) {
        $user_password = htmlspecialchars(stripslashes($row['user_password']));
        if ($check_num == $row['check_num']) {
            \DatabaseConnection::query("INSERT INTO nuke_users (user_id, username, user_email, user_password, user_avatar, user_avatar_type, user_regdate, user_lang) VALUES (NULL, ?, ?, ?, '', '3', ?, ?)", [$row['username'], $row['user_email'], $user_password, $row['user_regdate'], $language]);
            \DatabaseConnection::query("DELETE FROM nuke_users_temp WHERE username = ? AND check_num = ?", [$username, $check_num]);
            Nuke\Header::header();
            title("" . _ACTIVATIONYES . "");
            OpenTable();
            echo "<center><b>" . htmlspecialchars($row['username']) . ":</b> " . _ACTMSG . "</center>";
            CloseTable();
            Nuke\Footer::footer();
            die();
        } else {
            Nuke\Header::header();
            title("" . _ACTIVATIONERROR . "");
            OpenTable();
            echo "<center>" . _ACTERROR1 . "</center>";
            CloseTable();
            Nuke\Footer::footer();
            die();
        }
    } else {
        Nuke\Header::header();
        title("" . _ACTIVATIONERROR . "");
        OpenTable();
        echo "<center>" . _ACTERROR2 . "</center>";
        CloseTable();
        Nuke\Footer::footer();
        die();
    }

}

/**
 * Redirect a logged-in user to their team page or the homepage.
 */
function redirectLoggedInUser()
{
    global $mysqli_db, $cookie;
    $username = strval($cookie[1] ?? '');
    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    $teamName = $commonRepository->getTeamnameFromUsername($username);
    if ($teamName !== null && $teamName !== '' && $teamName !== 'Free Agents') {
        $tid = $commonRepository->getTidFromTeamname($teamName);
        if ($tid !== null && $tid > 0) {
            header('Location: modules.php?name=Team&op=team&teamID=' . $tid);
            exit;
        }
    }
    header('Location: index.php');
    exit;
}

function main($user)
{
    global $stop, $module_name, $redirect, $mode, $t, $f, $gfx_chk;
    if (!is_user($user)) {
        Nuke\Header::header();
        if ($stop) {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _LOGININCOR . "</b></font></center>\n";
            CloseTable();
            echo "<br>\n";
        } else {
            OpenTable();
            echo "<center><font class=\"title\"><b>" . _USERREGLOGIN . "</b></font></center>\n";
            CloseTable();
            echo "<br>\n";
        }
        if (!is_user($user)) {
            OpenTable();
            mt_srand((double) microtime() * 1000000);
            $maxran = 1000000;
            $random_num = mt_rand(0, $maxran);
            echo "<form action=\"modules.php?name=$module_name\" method=\"post\">\n"
                . "<b>" . _USERLOGIN . "</b><br><br>\n"
                . "<table border=\"0\"><tr><td>\n"
                . "" . _NICKNAME . ":</td><td><input type=\"text\" name=\"username\" size=\"15\" maxlength=\"25\"></td></tr>\n"
                . "<tr><td>" . _PASSWORD . ":</td><td><input type=\"password\" name=\"user_password\" size=\"15\" maxlength=\"20\"></td></tr>\n";
            if (extension_loaded("gd") and ($gfx_chk == 2 or $gfx_chk == 4 or $gfx_chk == 5 or $gfx_chk == 7)) {
                echo "<tr><td colspan='2'>" . _SECURITYCODE . ": <img src='?gfx=gfx&random_num=$random_num' border='1' alt='" . _SECURITYCODE . "' title='" . _SECURITYCODE . "'></td></tr>\n"
                    . "<tr><td colspan='2'>" . _TYPESECCODE . ": <input type=\"text\" NAME=\"gfx_check\" SIZE=\"7\" MAXLENGTH=\"6\"></td></tr>\n"
                    . "<input type=\"hidden\" name=\"random_num\" value=\"$random_num\">\n";
            }
            echo "</table><input type=\"hidden\" name=\"redirect\" value=\"" . htmlspecialchars(is_string($redirect) ? $redirect : '', ENT_QUOTES, 'UTF-8') . "\">\n"
                . "<input type=\"hidden\" name=\"mode\" value=$mode>\n"
                . "<input type=\"hidden\" name=\"f\" value=$f>\n"
                . "<input type=\"hidden\" name=\"t\" value=$t>\n"
                . "<input type=\"hidden\" name=\"op\" value=\"login\">\n"
                . "<input type=\"submit\" value=\"" . _LOGIN . "\"></form><br>\n\n"
                . "<center><font class=\"content\">[ <a href=\"modules.php?name=$module_name&amp;op=pass_lost\">" . _PASSWORDLOST . "</a> | <a href=\"modules.php?name=$module_name&amp;op=new_user\">" . _REGNEWUSER . "</a> ]</font></center>\n";
            CloseTable();
        }
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        cookiedecode($user);
        redirectLoggedInUser();
    }
}

function new_user()
{
    global $my_headlines, $module_name, $db, $gfx_chk, $user, $prefix;
    if (!is_user($user)) {
        mt_srand((double) microtime() * 1000000);
        $maxran = 1000000;
        $random_num = mt_rand(0, $maxran);
        Nuke\Header::header();
        OpenTable();
        echo "<center><font class=\"title\"><b>" . _USERREGLOGIN . "</b></font></center>\n";
        CloseTable();
        echo "<br>\n";
        OpenTable();
        echo "<form action=\"modules.php?name=$module_name\" method=\"post\">\n"
            . "<b>" . _REGNEWUSER . "</b> (" . _ALLREQUIRED . ")<br><br>\n"
            . "<table cellpadding=\"0\" cellspacing=\"10\" border=\"0\">\n"
            . "<tr><td>" . _NICKNAME . ":</td><td><input type=\"text\" name=\"username\" size=\"30\" maxlength=\"25\"></td></tr>\n"
            . "<tr><td>" . _EMAIL . ":</td><td><input type=\"text\" name=\"user_email\" size=\"30\" maxlength=\"255\"></td></tr>\n"
            . "<tr><td>" . _PASSWORD . ":</td><td><input type=\"password\" name=\"user_password\" size=\"11\" maxlength=\"40\"></td></tr>\n"
            . "<tr><td>" . _RETYPEPASSWORD . ":</td><td><input type=\"password\" name=\"user_password2\" size=\"11\" maxlength=\"40\"><br><font class=\"tiny\">(" . _BLANKFORAUTO . ")</font></td></tr>\n";
        if (extension_loaded("gd") and ($gfx_chk == 3 or $gfx_chk == 4 or $gfx_chk == 6 or $gfx_chk == 7)) {
            echo "<tr><td>" . _SECURITYCODE . ":</td><td><img src='?gfx=gfx&random_num=$random_num' border='1' alt='" . _SECURITYCODE . "' title='" . _SECURITYCODE . "'></td></tr>\n"
                . "<tr><td>" . _TYPESECCODE . ":</td><td><input type=\"text\" NAME=\"gfx_check\" SIZE=\"7\" MAXLENGTH=\"6\"></td></tr>\n"
                . "<input type=\"hidden\" name=\"random_num\" value=\"$random_num\">\n";
        }
        echo "<tr><td colspan='2'>\n"
            . "<input type=\"hidden\" name=\"op\" value=\"new user\">\n"
            . "<input type=\"submit\" value=\"" . _NEWUSER . "\">\n"
            . "</td></tr></table>\n"
            . "</form>\n"
            . "<br>\n"
            . "" . _YOUWILLRECEIVE . "<br><br>\n"
            . "" . _COOKIEWARNING . "<br>\n"
            . "" . _ASREGUSER . "<br>\n"
            . "<ul>\n"
            . "<li>" . _ASREG1 . "\n"
            . "<li>" . _ASREG2 . "\n"
            . "<li>" . _ASREG3 . "\n"
            . "<li>" . _ASREG4 . "\n"
            . "<li>" . _ASREG5 . "\n";
        $handle = opendir('themes');
        $thmcount = 0;
        while ($file = readdir($handle)) {
            if ((!str_contains($file, '.') and file_exists("themes/$file/theme.php"))) {
                $thmcount++;
            }
        }
        closedir($handle);
        if ($thmcount > 1) {
            echo "<li>" . _ASREG6 . "\n";
        }
        $sql = "SELECT custom_title FROM " . $prefix . "_modules WHERE active='1' AND view='1' AND inmenu='1'";
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result)) {
            $custom_title = filter($row['custom_title'], "nohtml");
            if (!empty($custom_title)) {
                echo "<li>" . _ACCESSTO . " $custom_title\n";
            }
        }
        $sql = "SELECT title FROM " . $prefix . "_blocks WHERE active='1' AND view='1'";
        $result = $db->sql_query($sql);
        while ($row = $db->sql_fetchrow($result)) {
            $b_title = filter($row['title'], "nohtml");
            if (!empty($b_title)) {
                echo "<li>" . _ACCESSTO . " $b_title\n";
            }
        }

        if ($my_headlines == 1) {
            echo "<li>" . _READHEADLINES . "\n";
        }
        echo "<li>" . _ASREG7 . "\n"
            . "</ul>\n"
            . "" . _REGISTERNOW . "<br>\n"
            . "" . _WEDONTGIVE . "<br><br>\n"
            . "<center><font class=\"content\">[ <a href=\"modules.php?name=$module_name\">" . _USERLOGIN . "</a> | <a href=\"modules.php?name=$module_name&amp;op=pass_lost\">" . _PASSWORDLOST . "</a> ]</font></center>\n";
        CloseTable();
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        cookiedecode($user);
        redirectLoggedInUser();
    }
}

function pass_lost()
{
    global $user, $module_name;
    if (!is_user($user)) {
        Nuke\Header::header();
        OpenTable();
        echo "<center><font class=\"title\"><b>" . _USERREGLOGIN . "</b></font></center>\n";
        CloseTable();
        echo "<br>\n";
        OpenTable();
        echo "<b>" . _PASSWORDLOST . "</b><br><br>\n"
            . "" . _NOPROBLEM . "<br><br>\n"
            . "<form action=\"modules.php?name=$module_name\" method=\"post\">\n"
            . "<table border=\"0\"><tr><td>\n"
            . "" . _NICKNAME . ":</td><td><input type=\"text\" name=\"username\" size=\"15\" maxlength=\"25\"></td></tr>\n"
            . "<tr><td>" . _CONFIRMATIONCODE . ":</td><td><input type=\"text\" name=\"code\" size=\"11\" maxlength=\"10\"></td></tr></table><br>\n"
            . "<input type=\"hidden\" name=\"op\" value=\"mailpasswd\">\n"
            . "<input type=\"submit\" value=\"" . _SENDPASSWORD . "\"></form><br>\n"
            . "<center><font class=\"content\">[ <a href=\"modules.php?name=$module_name\">" . _USERLOGIN . "</a> | <a href=\"modules.php?name=$module_name&amp;op=new_user\">" . _REGNEWUSER . "</a> ]</font></center>\n";
        CloseTable();
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        cookiedecode($user);
        redirectLoggedInUser();
    }
}

function logout()
{
    global $prefix, $db, $user, $cookie, $redirect, $authService, $mysqli_db;
    $r_username = $authService->getUsername();
    // Clear session auth
    $authService->logout();
    // Clear legacy cookie (in case browser still has one)
    setcookie("user", "", [
        'expires' => 1,
        'path' => '/',
        'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    // Remove session record
    if ($r_username !== null) {
        $stmt = $mysqli_db->prepare("DELETE FROM " . $prefix . "_session WHERE uname = ?");
        $stmt->bind_param('s', $r_username);
        $stmt->execute();
        $stmt->close();
    }
    $user = "";
    $cookie = "";
    Nuke\Header::header();
    OpenTable();
    if (is_string($redirect) && preg_match('/^[A-Za-z0-9_]+$/', $redirect) === 1) {
        echo "<META HTTP-EQUIV=\"refresh\" content=\"3;URL=modules.php?name=" . htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') . "\">";
    } else {
        echo "<META HTTP-EQUIV=\"refresh\" content=\"3;URL=index.php\">";
    }
    echo "<center><font class=\"option\"><b>" . _YOUARELOGGEDOUT . "</b></font></center>";
    CloseTable();
    Nuke\Footer::footer();
}

function mail_password($username, $code)
{
    global $sitename, $adminmail, $nukeurl, $user_prefix, $db, $module_name, $authService, $mysqli_db;
    $username = substr(htmlspecialchars(str_replace("\'", "'", trim($username))), 0, 25);
    $username = rtrim($username, "\\");
    $username = str_replace("'", "\'", $username);
    $sql = "SELECT user_email, user_actkey FROM " . $user_prefix . "_users WHERE username='$username'";
    $result = $db->sql_query($sql);
    if ($db->sql_numrows($result) == 0) {
        Nuke\Header::header();
        OpenTable();
        echo "<center>" . _SORRYNOUSERINFO . "</center>";
        CloseTable();
        Nuke\Footer::footer();
    } else {
        $host_name = $_SERVER['REMOTE_ADDR'];
        $row = $db->sql_fetchrow($result);
        $user_email = filter($row['user_email'], "nohtml");
        // Use user_actkey as reset verification code (no longer derived from password hash)
        $storedCode = $row['user_actkey'] ?? '';
        if ($storedCode !== '' && $storedCode === $code) {
            $newpass = makepass();
            $message = "" . _USERACCOUNT . " '$username' " . _AT . " $sitename " . _HASTHISEMAIL . "  " . _AWEBUSERFROM . " $host_name " . _HASREQUESTED . "\n\n" . _YOURNEWPASSWORD . " $newpass\n\n " . _YOUCANCHANGE . " $nukeurl/modules.php?name=$module_name\n\n" . _IFYOUDIDNOTASK . "";
            $subject = "" . _USERPASSWORD4 . " $username";
            mail($user_email, $subject, $message, "From: $adminmail\nX-Mailer: PHP/" . phpversion());
            /* Update password and clear the reset code */
            $cryptpass = $authService->hashPassword($newpass);
            $stmtReset = $mysqli_db->prepare("UPDATE " . $user_prefix . "_users SET user_password = ?, user_actkey = NULL WHERE username = ?");
            $stmtReset->bind_param('ss', $cryptpass, $username);
            $stmtReset->execute();
            $stmtReset->close();
            Nuke\Header::header();
            OpenTable();
            echo "<center>" . _PASSWORD4 . " $username " . _MAILED . "<br><br>" . _GOBACK . "</center>";
            CloseTable();
            Nuke\Footer::footer();
        } else {
            /* Generate a new reset code and email it */
            $resetCode = substr(bin2hex(random_bytes(5)), 0, 10);
            $stmtCode = $mysqli_db->prepare("UPDATE " . $user_prefix . "_users SET user_actkey = ? WHERE username = ?");
            $stmtCode->bind_param('ss', $resetCode, $username);
            $stmtCode->execute();
            $stmtCode->close();
            $message = "" . _USERACCOUNT . " '$username' " . _AT . " $sitename " . _HASTHISEMAIL . " " . _AWEBUSERFROM . " $host_name " . _CODEREQUESTED . "\n\n" . _YOURCODEIS . " $resetCode \n\n" . _WITHTHISCODE . " $nukeurl/modules.php?name=$module_name&op=pass_lost\n" . _IFYOUDIDNOTASK2 . "";
            $subject = "" . _CODEFOR . " $username";
            mail($user_email, $subject, $message, "From: $adminmail\nX-Mailer: PHP/" . phpversion());
            Nuke\Header::header();
            OpenTable();
            echo "<center>" . _CODEFOR . " $username " . _MAILED . "<br><br>" . _GOBACK . "</center>";
            CloseTable();
            Nuke\Footer::footer();
        }
    }
}

function login($username, $user_password, $redirect, $mode, $f, $t, $random_num, $gfx_check)
{
    global $authService, $user_prefix, $db, $mysqli_db, $module_name, $pm_login, $prefix;
    $user_password = stripslashes($user_password);
    include "config.php";

    // CAPTCHA check
    $datekey = date("F j");
    $rcode = hexdec(md5($_SERVER['HTTP_USER_AGENT'] . $sitekey . $random_num . $datekey));
    $code = substr($rcode, 2, 6);
    if (extension_loaded("gd") and $code != $gfx_check and ($gfx_chk == 2 or $gfx_chk == 4 or $gfx_chk == 5 or $gfx_chk == 7)) {
        $redirectParam = '';
        if (is_string($redirect) && preg_match('/^[A-Za-z0-9_]+$/', $redirect)) {
            $redirectParam = '&redirect=' . rawurlencode($redirect);
        }
        Header("Location: modules.php?name=$module_name&stop=1" . $redirectParam);
        die();
    }

    // Authenticate via AuthService (handles bcrypt + MD5 transitional upgrade)
    if ($authService->attempt($username, $user_password)) {
        $uname = $_SERVER['REMOTE_ADDR'];

        // Clean up guest session for this IP
        $stmtDelSession = $mysqli_db->prepare("DELETE FROM " . $prefix . "_session WHERE uname = ? AND guest = '1'");
        $stmtDelSession->bind_param('s', $uname);
        $stmtDelSession->execute();
        $stmtDelSession->close();

        // Record last IP
        $stmtUpdateIp = $mysqli_db->prepare("UPDATE " . $prefix . "_users SET last_ip = ? WHERE username = ?");
        $stmtUpdateIp->bind_param('ss', $uname, $username);
        $stmtUpdateIp->execute();
        $stmtUpdateIp->close();

        // Redirect to the requested module, or the user's team page, or the homepage
        if (is_string($redirect) && preg_match('/^[A-Za-z0-9_]+$/', $redirect)) {
            Header("Location: modules.php?name=" . rawurlencode($redirect));
        } else {
            // Look up user's team and redirect there
            $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
            $teamName = $commonRepository->getTeamnameFromUsername($username);
            if ($teamName !== null && $teamName !== '' && $teamName !== 'Free Agents') {
                $tid = $commonRepository->getTidFromTeamname($teamName);
                if ($tid !== null && $tid > 0) {
                    Header("Location: modules.php?name=Team&op=team&teamID=" . $tid);
                } else {
                    Header("Location: index.php");
                }
            } else {
                Header("Location: index.php");
            }
        }
        exit;
    } else {
        $redirectParam = '';
        if (is_string($redirect) && preg_match('/^[A-Za-z0-9_]+$/', $redirect)) {
            $redirectParam = '&redirect=' . rawurlencode($redirect);
        }
        Header("Location: modules.php?name=$module_name&stop=1" . $redirectParam);
        exit;
    }
}

if (!isset($hid)) {$hid = "";}
if (!isset($url)) {$url = "";}
if (!isset($bypass)) {$bypass = "";}
if (!isset($op)) {$op = "";}

switch ($op) {

    case "logout":
        logout();
        break;

    case "new user":
        confirmNewUser($username, $user_email, $user_password, $user_password2, $random_num, $gfx_check);
        break;

    case "finish":
        finishNewUser($username, $user_email, $user_password, $random_num, $gfx_check);
        break;

    case "mailpasswd":
        mail_password($username, $code);
        break;

    case "login":
        login($username, $user_password, $redirect, $mode, $f, $t, $random_num, $gfx_check);
        break;

    case "pass_lost":
        pass_lost();
        break;

    case "new_user":
        new_user();
        break;

    case "gfx":
        gfx($random_num);
        break;

    case "activate":
        activate($username, $check_num);
        break;

    default:
        main($user);
        break;

}

?>
