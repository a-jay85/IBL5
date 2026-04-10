<?php

declare(strict_types=1);

if (!defined('MODULE_FILE')) {
    die("You can't access this file directly...");
}

$module_name = basename(dirname(__FILE__));
get_lang($module_name);

if (isset($username) && is_string($username) && preg_match('/[^a-zA-Z0-9_-]/', $username) === 1) {
    die("Illegal username...");
}

// Wire dependencies
$commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
$service = new \YourAccount\YourAccountService(
    $authService,
    $commonRepository,
    \Mail\MailService::fromConfig(),
    (string) ($nukeurl ?? ''),
    (string) ($sitename ?? ''),
    (string) ($adminmail ?? ''),
    (int) ($minpass ?? 5),
);
$accountView = new \YourAccount\YourAccountView();

if (!isset($op) || !is_string($op)) {
    $op = '';
}

switch ($op) {
    case 'logout':
        $service->logout();
        // Clear legacy cookie
        setcookie('user', '', [
            'expires' => 1,
            'path' => '/',
            'secure' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        $user = '';
        $cookie = '';
        $_SESSION['flash_success'] = 'You have successfully logged out.';
        header('Location: index.php');
        exit;

    case 'login':
        if (!\Utilities\CsrfGuard::validateSubmittedToken('login')) {
            header("Location: modules.php?name={$module_name}&stop=1");
            die();
        }
        $loginUsername = isset($username) && is_string($username) ? $username : '';
        $loginPassword = isset($user_password) && is_string($user_password) ? stripslashes($user_password) : '';
        $redirectQuery = $_POST['redirect_query'] ?? '';
        if (is_string($redirectQuery) && $redirectQuery !== '') {
            $_SESSION['redirect_after_login'] = $redirectQuery;
        }
        $rememberMe = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';
        $result = $service->attemptLogin($loginUsername, $loginPassword, $rememberMe, $_SERVER['REMOTE_ADDR']);
        if ($result['success']) {
            $redirectUrl = buildRedirectUrl() ?? $service->getTeamRedirectUrl($loginUsername) ?? 'index.php';
            header('Location: ' . $redirectUrl);
        } else {
            if ($result['error'] !== null) {
                $_SESSION['login_error'] = $result['error'];
                header("Location: modules.php?name={$module_name}");
            } else {
                header("Location: modules.php?name={$module_name}&stop=1");
            }
        }
        exit;

    case 'finish':
        if (!\Utilities\CsrfGuard::validateSubmittedToken('register')) {
            header("Location: modules.php?name={$module_name}&op=new_user");
            die();
        }
        $regUsername = isset($_POST['username']) && is_string($_POST['username']) ? $_POST['username'] : '';
        $regUsername = substr(htmlspecialchars(str_replace("\'", "'", trim($regUsername)), ENT_QUOTES, 'UTF-8'), 0, 25);
        $regUsername = rtrim($regUsername, '\\');
        $regEmail = isset($_POST['user_email']) && is_string($_POST['user_email']) ? trim($_POST['user_email']) : '';
        $regPw1 = isset($_POST['user_password']) && is_string($_POST['user_password']) ? stripslashes($_POST['user_password']) : '';
        $regPw2 = isset($_POST['user_password2']) && is_string($_POST['user_password2']) ? stripslashes($_POST['user_password2']) : '';
        $result = $service->registerUser($regUsername, $regEmail, $regPw1, $regPw2);
        PageLayout\PageLayout::header();
        if ($result['success']) {
            echo $accountView->renderRegistrationCompletePage((string) ($sitename ?? ''));
        } else {
            echo $accountView->renderRegistrationErrorPage((string) $result['error']);
        }
        PageLayout\PageLayout::footer();
        break;

    case 'mailpasswd':
        if (!\Utilities\CsrfGuard::validateSubmittedToken('forgot_password')) {
            header("Location: modules.php?name={$module_name}&op=pass_lost");
            die();
        }
        $resetEmail = isset($_POST['user_email']) && is_string($_POST['user_email']) ? trim($_POST['user_email']) : '';
        $result = $service->requestPasswordReset($resetEmail);
        PageLayout\PageLayout::header();
        if ($result['success']) {
            echo $accountView->renderResetEmailSentPage();
        } else {
            echo $accountView->renderPasswordResetErrorPage((string) $result['error']);
        }
        PageLayout\PageLayout::footer();
        break;

    case 'do_reset_password':
        if (!\Utilities\CsrfGuard::validateSubmittedToken('reset_password')) {
            header("Location: modules.php?name={$module_name}&op=pass_lost");
            die();
        }
        $rpSelector = isset($_POST['selector']) && is_string($_POST['selector']) ? trim($_POST['selector']) : '';
        $rpToken = isset($_POST['token']) && is_string($_POST['token']) ? trim($_POST['token']) : '';
        $rpPw1 = isset($_POST['new_password']) && is_string($_POST['new_password']) ? $_POST['new_password'] : '';
        $rpPw2 = isset($_POST['new_password2']) && is_string($_POST['new_password2']) ? $_POST['new_password2'] : '';
        $result = $service->resetPassword($rpSelector, $rpToken, $rpPw1, $rpPw2);
        PageLayout\PageLayout::header();
        if ($result['success']) {
            echo $accountView->renderPasswordResetSuccessPage();
        } else {
            echo $accountView->renderPasswordResetErrorPage((string) $result['error']);
        }
        PageLayout\PageLayout::footer();
        break;

    case 'pass_lost':
        if (is_user($user)) {
            $redirectUrl = $service->getTeamRedirectUrl((string) ($cookie[1] ?? ''));
            header('Location: ' . ($redirectUrl ?? 'index.php'));
            exit;
        }
        PageLayout\PageLayout::header();
        echo $accountView->renderForgotPasswordPage();
        PageLayout\PageLayout::footer();
        break;

    case 'new_user':
        if (is_user($user)) {
            $redirectUrl = $service->getTeamRedirectUrl((string) ($cookie[1] ?? ''));
            header('Location: ' . ($redirectUrl ?? 'index.php'));
            exit;
        }
        PageLayout\PageLayout::header();
        echo $accountView->renderRegisterPage();
        PageLayout\PageLayout::footer();
        break;

    case 'activate':
        // Legacy activation route — redirect to confirm_email if selector/token present
        if (isset($_GET['selector']) && isset($_GET['token'])) {
            $ceSelector = is_string($_GET['selector']) ? trim($_GET['selector']) : '';
            $ceToken = is_string($_GET['token']) ? trim($_GET['token']) : '';
            $result = $service->confirmEmail($ceSelector, $ceToken);
            PageLayout\PageLayout::header();
            if ($result['success']) {
                echo $accountView->renderActivationSuccessPage((string) $result['username']);
            } else {
                echo $accountView->renderActivationErrorPage((string) $result['error']);
            }
            PageLayout\PageLayout::footer();
            die();
        }
        // Fallback for remaining legacy activation links
        PageLayout\PageLayout::header();
        echo $accountView->renderActivationErrorPage('expired');
        PageLayout\PageLayout::footer();
        break;

    case 'confirm_email':
        $ceSelector = isset($_GET['selector']) && is_string($_GET['selector']) ? trim($_GET['selector']) : '';
        $ceToken = isset($_GET['token']) && is_string($_GET['token']) ? trim($_GET['token']) : '';
        $result = $service->confirmEmail($ceSelector, $ceToken);
        PageLayout\PageLayout::header();
        if ($result['success']) {
            echo $accountView->renderActivationSuccessPage((string) $result['username']);
        } else {
            echo $accountView->renderActivationErrorPage((string) $result['error']);
        }
        PageLayout\PageLayout::footer();
        break;

    case 'reset_password':
        if (is_user($user)) {
            header("Location: modules.php?name={$module_name}");
            die();
        }
        $rpSelector = isset($_GET['selector']) && is_string($_GET['selector']) ? trim($_GET['selector']) : '';
        $rpToken = isset($_GET['token']) && is_string($_GET['token']) ? trim($_GET['token']) : '';
        if ($rpSelector === '' || $rpToken === '') {
            header("Location: modules.php?name={$module_name}&op=pass_lost");
            die();
        }
        PageLayout\PageLayout::header();
        echo $accountView->renderResetPasswordPage($rpSelector, $rpToken);
        PageLayout\PageLayout::footer();
        break;

    default:
        if (!is_user($user)) {
            PageLayout\PageLayout::header();
            $errorMessage = null;
            if (isset($_SESSION['login_error']) && is_string($_SESSION['login_error'])) {
                $errorMessage = $_SESSION['login_error'];
                unset($_SESSION['login_error']);
            } elseif (isset($stop) && $stop !== '' && $stop !== false && $stop !== 0) {
                $errorMessage = 'Login was incorrect. Please try again.';
            }
            echo $accountView->renderLoginPage($errorMessage);
            PageLayout\PageLayout::footer();
        } else {
            cookiedecode($user);
            $redirectUrl = $service->getTeamRedirectUrl((string) ($cookie[1] ?? ''));
            header('Location: ' . ($redirectUrl ?? 'index.php'));
            exit;
        }
        break;
}
