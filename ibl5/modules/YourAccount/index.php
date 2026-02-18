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
        $stop = _ERRORINVEMAIL;
    }

    if (strrpos($user_email, ' ') > 0) {
        $stop = _ERROREMAILSPACES;
    }

    if ((!$username) || (empty($username)) || (preg_match('/[^a-zA-Z0-9_-]/', $username))) {
        $stop = _ERRORINVNICK;
    }

    if (strlen($username) > 25) {
        $stop = _NICK2LONG;
    }

    if (preg_match('/^((root)|(adm)|(linux)|(webmaster)|(admin)|(god)|(administrator)|(administrador)|(nobody)|(anonymous)|(anonimo)|(an\x{00e1}nimo)|(operator)|(JackFromWales4u2))$/iu', $username)) {
        $stop = _NAMERESERVED;
    }

    if (strrpos($username, ' ') > 0) {
        $stop = _NICKNOSPACES;
    }

    if (\DatabaseConnection::fetchValue("SELECT COUNT(*) FROM nuke_users WHERE username = ?", [$username]) > 0) {
        $stop = _NICKTAKEN;
    }

    if (\DatabaseConnection::fetchValue("SELECT COUNT(*) FROM nuke_users WHERE user_email = ?", [$user_email]) > 0) {
        $stop = _EMAILREGISTERED;
    }

    return $stop;
}

function finishNewUser(): void
{
    global $adminmail, $sitename, $module_name, $nukeurl, $authService, $minpass;

    // CSRF validation
    if (!\Utilities\CsrfGuard::validateSubmittedToken('register')) {
        Header("Location: modules.php?name=$module_name&op=new_user");
        die();
    }

    Nuke\Header::header();
    include "config.php";

    $username = isset($_POST['username']) && is_string($_POST['username']) ? $_POST['username'] : '';
    $user_email = isset($_POST['user_email']) && is_string($_POST['user_email']) ? $_POST['user_email'] : '';
    $user_password = isset($_POST['user_password']) && is_string($_POST['user_password']) ? $_POST['user_password'] : '';
    $user_password2 = isset($_POST['user_password2']) && is_string($_POST['user_password2']) ? $_POST['user_password2'] : '';

    $username = substr(htmlspecialchars(str_replace("\'", "'", trim($username)), ENT_QUOTES, 'UTF-8'), 0, 25);
    $username = rtrim($username, "\\");
    $user_email = filter($user_email, "nohtml", 1);
    $user_password = stripslashes($user_password);
    $user_password2 = stripslashes($user_password2);

    $accountView = new \YourAccount\YourAccountView();

    // Password validation
    if ($user_password === '' && $user_password2 === '') {
        $user_password = substr(bin2hex(random_bytes(5)), 0, 10);
    } elseif ($user_password !== $user_password2) {
        echo $accountView->renderRegistrationErrorPage('The passwords you entered do not match.');
        Nuke\Footer::footer();
        die();
    } elseif (strlen($user_password) < (int) ($minpass ?? 5)) {
        echo $accountView->renderRegistrationErrorPage("Your password must be at least " . (int) ($minpass ?? 5) . " characters long.");
        Nuke\Footer::footer();
        die();
    }

    // Basic username validation (keep as safety net; delight-auth also validates uniqueness)
    if ($username === '' || preg_match('/[^a-zA-Z0-9_-]/', $username) === 1) {
        echo $accountView->renderRegistrationErrorPage('Invalid username. Only letters, numbers, underscores and hyphens are allowed.');
        Nuke\Footer::footer();
        die();
    }

    try {
        // Register via delight-im/auth with email verification callback
        $authService->register($user_email, $user_password, $username, static function (string $selector, string $token) use ($sitename, $adminmail, $nukeurl, $module_name, $user_email, $username): void {
            $finishlink = rtrim($nukeurl, '/') . "/modules.php?name=$module_name&op=confirm_email&selector=" . urlencode($selector) . "&token=" . urlencode($token);
            $message = "" . _WELCOMETO . " $sitename!\n\n" . _YOUUSEDEMAIL . " ($user_email) " . _TOREGISTER . " $sitename.\n\n " . _TOFINISHUSER . "\n\n $finishlink\n\n " . _FOLLOWINGMEM . "\n\n" . _UNICKNAME . " $username";
            $subject = "" . _ACTIVATIONSUB . "";
            \Mail\MailService::fromConfig()->send($user_email, $subject, $message, $adminmail);
        });

        echo $accountView->renderRegistrationCompletePage($sitename);
    } catch (\RuntimeException) {
        $error = $authService->getLastError() ?? _ERROR;
        echo $accountView->renderRegistrationErrorPage((string) $error);
    }

    Nuke\Footer::footer();
}

function activate($username, $check_num)
{
    // Legacy activation route — redirect to confirm_email if selector/token params present
    global $module_name;
    if (isset($_GET['selector']) && isset($_GET['token'])) {
        confirm_email();
        return;
    }

    // Fallback for any remaining legacy activation links
    Nuke\Header::header();
    $accountView = new \YourAccount\YourAccountView();
    echo $accountView->renderActivationErrorPage('expired');
    Nuke\Footer::footer();
    die();
}

function confirm_email()
{
    global $authService, $module_name;
    $selector = isset($_GET['selector']) ? trim($_GET['selector']) : '';
    $token = isset($_GET['token']) ? trim($_GET['token']) : '';

    if ($selector === '' || $token === '') {
        Nuke\Header::header();
        $accountView = new \YourAccount\YourAccountView();
        echo $accountView->renderActivationErrorPage('mismatch');
        Nuke\Footer::footer();
        die();
    }

    try {
        $authService->confirmEmail($selector, $token);
        Nuke\Header::header();
        $accountView = new \YourAccount\YourAccountView();
        echo $accountView->renderActivationSuccessPage('User');
        Nuke\Footer::footer();
    } catch (\RuntimeException) {
        $error = $authService->getLastError() ?? 'expired';
        Nuke\Header::header();
        $accountView = new \YourAccount\YourAccountView();
        echo $accountView->renderActivationErrorPage($error);
        Nuke\Footer::footer();
    }
    die();
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
    global $stop, $module_name, $gfx_chk;
    if (!is_user($user)) {
        Nuke\Header::header();
        mt_srand((double) microtime() * 1000000);
        $maxran = 1000000;
        $random_num = mt_rand(0, $maxran);
        $showCaptcha = extension_loaded("gd") && ($gfx_chk == 2 || $gfx_chk == 4 || $gfx_chk == 5 || $gfx_chk == 7);

        // Check for specific error from session (e.g., email not verified, throttled)
        $errorMessage = $stop ? (string) $stop : null;
        if ($errorMessage === null && isset($_SESSION['login_error']) && is_string($_SESSION['login_error'])) {
            $errorMessage = $_SESSION['login_error'];
            unset($_SESSION['login_error']);
        }

        $accountView = new \YourAccount\YourAccountView();
        echo $accountView->renderLoginPage(
            $errorMessage,
            $random_num,
            $showCaptcha
        );
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        cookiedecode($user);
        redirectLoggedInUser();
    }
}

function new_user()
{
    global $user;
    if (!is_user($user)) {
        Nuke\Header::header();
        $accountView = new \YourAccount\YourAccountView();
        echo $accountView->renderRegisterPage();
        Nuke\Footer::footer();
    } elseif (is_user($user)) {
        cookiedecode($user);
        redirectLoggedInUser();
    }
}

function pass_lost()
{
    global $user;
    if (!is_user($user)) {
        Nuke\Header::header();
        $accountView = new \YourAccount\YourAccountView();
        echo $accountView->renderForgotPasswordPage();
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
    $_SESSION['flash_success'] = 'You have successfully logged out.';
    Header("Location: index.php");
    exit;
}

function reset_password_form()
{
    global $user, $module_name;
    if (is_user($user)) {
        Header("Location: modules.php?name=$module_name");
        die();
    }
    $selector = isset($_GET['selector']) ? htmlspecialchars(trim($_GET['selector'])) : '';
    $token = isset($_GET['token']) ? htmlspecialchars(trim($_GET['token'])) : '';
    if ($selector === '' || $token === '') {
        Header("Location: modules.php?name=$module_name&op=pass_lost");
        die();
    }
    Nuke\Header::header();
    $accountView = new \YourAccount\YourAccountView();
    echo $accountView->renderResetPasswordPage($selector, $token);
    Nuke\Footer::footer();
}

function do_reset_password()
{
    global $authService, $module_name;

    // CSRF validation
    if (!\Utilities\CsrfGuard::validateSubmittedToken('reset_password')) {
        Header("Location: modules.php?name=$module_name&op=pass_lost");
        die();
    }

    $selector = isset($_POST['selector']) ? trim($_POST['selector']) : '';
    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $newPassword2 = isset($_POST['new_password2']) ? $_POST['new_password2'] : '';

    $accountView = new \YourAccount\YourAccountView();

    if ($newPassword !== $newPassword2) {
        Nuke\Header::header();
        echo $accountView->renderPasswordResetErrorPage('The passwords you entered do not match. Please go back and try again.');
        Nuke\Footer::footer();
        return;
    }

    try {
        $authService->resetPassword($selector, $token, $newPassword);
        Nuke\Header::header();
        echo $accountView->renderPasswordResetSuccessPage();
        Nuke\Footer::footer();
    } catch (\RuntimeException) {
        $error = $authService->getLastError() ?? 'An error occurred while resetting your password.';
        Nuke\Header::header();
        echo $accountView->renderPasswordResetErrorPage((string) $error);
        Nuke\Footer::footer();
    }
}

function mail_password()
{
    global $sitename, $adminmail, $nukeurl, $module_name, $authService;

    // CSRF validation
    if (!\Utilities\CsrfGuard::validateSubmittedToken('forgot_password')) {
        Header("Location: modules.php?name=$module_name&op=pass_lost");
        die();
    }

    $user_email = isset($_POST['user_email']) ? filter($_POST['user_email'], "nohtml", 1) : '';
    $accountView = new \YourAccount\YourAccountView();
    if ($user_email === '') {
        Nuke\Header::header();
        echo $accountView->renderPasswordResetErrorPage('Please enter your email address.');
        Nuke\Footer::footer();
        return;
    }

    // Use delight-im/auth's built-in password reset with secure tokens
    $authService->forgotPassword($user_email, static function (string $selector, string $token) use ($sitename, $adminmail, $nukeurl, $module_name, $user_email): void {
        $resetLink = rtrim($nukeurl, '/') . "/ibl5/modules.php?name=$module_name&op=reset_password&selector=" . urlencode($selector) . "&token=" . urlencode($token);
        $message = "A password reset was requested for your account at $sitename.\n\n"
            . "Click the link below to reset your password:\n\n$resetLink\n\n"
            . "This link will expire in 6 hours.\n\n"
            . "If you did not request this, you can safely ignore this email.";
        $subject = "Password Reset - $sitename";
        \Mail\MailService::fromConfig()->send($user_email, $subject, $message, $adminmail);
    });

    // Always show success message (don't reveal if email exists)
    Nuke\Header::header();
    $lastError = $authService->getLastError();
    if ($lastError !== null) {
        echo $accountView->renderPasswordResetErrorPage((string) $lastError);
    } else {
        echo $accountView->renderResetEmailSentPage();
    }
    Nuke\Footer::footer();
}

function login($username, $user_password, $random_num, $gfx_check)
{
    global $authService, $user_prefix, $db, $mysqli_db, $module_name, $pm_login, $prefix;

    // CSRF validation
    if (!\Utilities\CsrfGuard::validateSubmittedToken('login')) {
        Header("Location: modules.php?name=$module_name&stop=1");
        die();
    }

    $user_password = stripslashes($user_password);
    include "config.php";

    // CAPTCHA check
    $datekey = date("F j");
    $rcode = hexdec(md5($_SERVER['HTTP_USER_AGENT'] . $sitekey . $random_num . $datekey));
    $code = substr($rcode, 2, 6);
    if (extension_loaded("gd") and $code != $gfx_check and ($gfx_chk == 2 or $gfx_chk == 4 or $gfx_chk == 5 or $gfx_chk == 7)) {
        Header("Location: modules.php?name=$module_name&stop=1");
        die();
    }

    // Store redirect from nav login form before auth (persists for retry on failure)
    $redirectQuery = $_POST['redirect_query'] ?? '';
    if (is_string($redirectQuery) && $redirectQuery !== '') {
        $_SESSION['redirect_after_login'] = $redirectQuery;
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

        // Redirect to the stored original URL, or the user's team page, or the homepage
        $redirectUrl = buildRedirectUrl();
        if ($redirectUrl !== null) {
            Header("Location: " . $redirectUrl);
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
        // Login failure — preserve specific error from AuthService (e.g., email not verified)
        // Session redirect value persists automatically for retry
        $specificError = $authService->getLastError();
        if ($specificError !== null) {
            $_SESSION['login_error'] = $specificError;
            Header("Location: modules.php?name=$module_name");
        } else {
            Header("Location: modules.php?name=$module_name&stop=1");
        }
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

    case "finish":
        finishNewUser();
        break;

    case "mailpasswd":
        mail_password();
        break;

    case "login":
        login($username, $user_password, $random_num, $gfx_check);
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

    case "confirm_email":
        confirm_email();
        break;

    case "reset_password":
        reset_password_form();
        break;

    case "do_reset_password":
        do_reset_password();
        break;

    default:
        main($user);
        break;

}

?>
