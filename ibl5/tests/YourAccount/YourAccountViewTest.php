<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use YourAccount\YourAccountView;

/**
 * Tests for YourAccountView rendering methods.
 *
 * Verifies HTML structure, XSS safety, correct form fields,
 * CSRF tokens, and cross-navigation links for all auth pages.
 */
class YourAccountViewTest extends TestCase
{
    private YourAccountView $view;

    protected function setUp(): void
    {
        $this->view = new YourAccountView();
        // Ensure session is available for CSRF token generation
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // =========================================================================
    // Login Page
    // =========================================================================

    public function testRenderLoginPageReturnsHtml(): void
    {
        $result = $this->view->renderLoginPage(null, null, 123456, false);

        $this->assertStringContainsString('auth-page', $result);
        $this->assertStringContainsString('auth-card', $result);
        $this->assertStringContainsString('ibl-card', $result);
        $this->assertStringContainsString('Sign In', $result);
    }

    public function testRenderLoginPageContainsFormFields(): void
    {
        $result = $this->view->renderLoginPage(null, null, 123456, false);

        $this->assertStringContainsString('name="username"', $result);
        $this->assertStringContainsString('name="user_password"', $result);
        $this->assertStringContainsString('name="op" value="login"', $result);
        $this->assertStringContainsString('ibl-btn--primary', $result);
        $this->assertStringContainsString('ibl-btn--block', $result);
    }

    public function testRenderLoginPageContainsCsrfToken(): void
    {
        $result = $this->view->renderLoginPage(null, null, 123456, false);

        $this->assertStringContainsString('name="_csrf_token"', $result);
    }

    public function testRenderLoginPageContainsRememberMeCheckbox(): void
    {
        $result = $this->view->renderLoginPage(null, null, 123456, false);

        $this->assertStringContainsString('name="remember_me"', $result);
        $this->assertStringContainsString('Remember me', $result);
    }

    public function testRenderLoginPageContainsHiddenFieldsForForumRedirect(): void
    {
        $result = $this->view->renderLoginPage(null, null, 123456, false, 'reply', '5', '10');

        $this->assertStringContainsString('name="mode" value="reply"', $result);
        $this->assertStringContainsString('name="f" value="5"', $result);
        $this->assertStringContainsString('name="t" value="10"', $result);
    }

    public function testRenderLoginPageShowsErrorWhenProvided(): void
    {
        $result = $this->view->renderLoginPage('Login failed', null, 123456, false);

        $this->assertStringContainsString('ibl-alert--error', $result);
        $this->assertStringContainsString('Login was incorrect', $result);
    }

    public function testRenderLoginPageHidesErrorWhenNull(): void
    {
        $result = $this->view->renderLoginPage(null, null, 123456, false);

        $this->assertStringNotContainsString('ibl-alert--error', $result);
    }

    public function testRenderLoginPageIncludesRedirect(): void
    {
        $result = $this->view->renderLoginPage(null, 'Trading', 123456, false);

        $this->assertStringContainsString('name="redirect" value="Trading"', $result);
    }

    public function testRenderLoginPageEscapesRedirect(): void
    {
        $result = $this->view->renderLoginPage(null, '"><script>xss</script>', 123456, false);

        $this->assertStringNotContainsString('<script>xss</script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testRenderLoginPageShowsCaptchaWhenEnabled(): void
    {
        $result = $this->view->renderLoginPage(null, null, 999, true);

        $this->assertStringContainsString('name="gfx_check"', $result);
        $this->assertStringContainsString('name="random_num" value="999"', $result);
        $this->assertStringContainsString('Security Code', $result);
    }

    public function testRenderLoginPageHidesCaptchaWhenDisabled(): void
    {
        $result = $this->view->renderLoginPage(null, null, 999, false);

        $this->assertStringNotContainsString('name="gfx_check"', $result);
    }

    public function testRenderLoginPageContainsCrossNavLinks(): void
    {
        $result = $this->view->renderLoginPage(null, null, 123456, false);

        $this->assertStringContainsString('Forgot password?', $result);
        $this->assertStringContainsString('Create an account', $result);
        $this->assertStringContainsString('op=pass_lost', $result);
        $this->assertStringContainsString('op=new_user', $result);
    }

    public function testRenderLoginPageContainsLogo(): void
    {
        $result = $this->view->renderLoginPage(null, null, 123456, false);

        $this->assertStringContainsString('auth-logo', $result);
        $this->assertStringContainsString('auth-logo__icon', $result);
    }

    // =========================================================================
    // Register Page
    // =========================================================================

    public function testRenderRegisterPageReturnsHtml(): void
    {
        $result = $this->view->renderRegisterPage();

        $this->assertStringContainsString('auth-page', $result);
        $this->assertStringContainsString('Create Account', $result);
        $this->assertStringContainsString('Join the IBL community', $result);
    }

    public function testRenderRegisterPageContainsFormFields(): void
    {
        $result = $this->view->renderRegisterPage();

        $this->assertStringContainsString('name="username"', $result);
        $this->assertStringContainsString('name="user_email"', $result);
        $this->assertStringContainsString('name="user_password"', $result);
        $this->assertStringContainsString('name="user_password2"', $result);
        $this->assertStringContainsString('name="op" value="finish"', $result);
    }

    public function testRenderRegisterPageContainsCsrfToken(): void
    {
        $result = $this->view->renderRegisterPage();

        $this->assertStringContainsString('name="_csrf_token"', $result);
    }

    public function testRenderRegisterPageContainsCrossNavLinks(): void
    {
        $result = $this->view->renderRegisterPage();

        $this->assertStringContainsString('Already have an account?', $result);
        $this->assertStringContainsString('modules.php?name=YourAccount"', $result);
    }

    public function testRenderRegisterPageContainsEmailActivationNotice(): void
    {
        $result = $this->view->renderRegisterPage();

        $this->assertStringContainsString('activation link', $result);
    }

    // =========================================================================
    // Registration Complete Page
    // =========================================================================

    public function testRenderRegistrationCompletePageShowsSuccess(): void
    {
        $result = $this->view->renderRegistrationCompletePage('IBL Hoops');

        $this->assertStringContainsString('auth-status__icon--success', $result);
        $this->assertStringContainsString('Account Created', $result);
        $this->assertStringContainsString('IBL Hoops', $result);
        $this->assertStringContainsString('Sign In', $result);
    }

    // =========================================================================
    // Registration Error Page
    // =========================================================================

    public function testRenderRegistrationErrorPageShowsError(): void
    {
        $result = $this->view->renderRegistrationErrorPage('Username is taken');

        $this->assertStringContainsString('ibl-alert--error', $result);
        $this->assertStringContainsString('Username is taken', $result);
        $this->assertStringContainsString('Try again', $result);
    }

    public function testRenderRegistrationErrorPageEscapesXss(): void
    {
        $result = $this->view->renderRegistrationErrorPage('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    // =========================================================================
    // Forgot Password Page (email-based, delight-auth)
    // =========================================================================

    public function testRenderForgotPasswordPageReturnsHtml(): void
    {
        $result = $this->view->renderForgotPasswordPage();

        $this->assertStringContainsString('auth-page', $result);
        $this->assertStringContainsString('Reset Password', $result);
        $this->assertStringContainsString('name="user_email"', $result);
        $this->assertStringContainsString('type="email"', $result);
        $this->assertStringContainsString('name="op" value="mailpasswd"', $result);
    }

    public function testRenderForgotPasswordPageContainsCsrfToken(): void
    {
        $result = $this->view->renderForgotPasswordPage();

        $this->assertStringContainsString('name="_csrf_token"', $result);
    }

    public function testRenderForgotPasswordPageDoesNotContainUsernameField(): void
    {
        $result = $this->view->renderForgotPasswordPage();

        $this->assertStringNotContainsString('name="username"', $result);
        $this->assertStringNotContainsString('name="code"', $result);
    }

    public function testRenderForgotPasswordPageContainsCrossNavLinks(): void
    {
        $result = $this->view->renderForgotPasswordPage();

        $this->assertStringContainsString('Remember your password?', $result);
        $this->assertStringContainsString('Need an account?', $result);
    }

    // =========================================================================
    // Reset Email Sent Page
    // =========================================================================

    public function testRenderResetEmailSentPageShowsInfo(): void
    {
        $result = $this->view->renderResetEmailSentPage();

        $this->assertStringContainsString('auth-status__icon--info', $result);
        $this->assertStringContainsString('Check Your Email', $result);
        $this->assertStringContainsString('Back to Sign In', $result);
    }

    public function testRenderResetEmailSentPageDoesNotRevealEmailExistence(): void
    {
        $result = $this->view->renderResetEmailSentPage();

        $this->assertStringContainsString('If an account exists', $result);
    }

    // =========================================================================
    // Reset Password Page (selector/token form)
    // =========================================================================

    public function testRenderResetPasswordPageReturnsHtml(): void
    {
        $result = $this->view->renderResetPasswordPage('abc123', 'token456');

        $this->assertStringContainsString('auth-page', $result);
        $this->assertStringContainsString('Reset Password', $result);
        $this->assertStringContainsString('Enter your new password', $result);
    }

    public function testRenderResetPasswordPageContainsFormFields(): void
    {
        $result = $this->view->renderResetPasswordPage('abc123', 'token456');

        $this->assertStringContainsString('name="new_password"', $result);
        $this->assertStringContainsString('name="new_password2"', $result);
        $this->assertStringContainsString('name="selector" value="abc123"', $result);
        $this->assertStringContainsString('name="token" value="token456"', $result);
        $this->assertStringContainsString('name="op" value="do_reset_password"', $result);
    }

    public function testRenderResetPasswordPageContainsCsrfToken(): void
    {
        $result = $this->view->renderResetPasswordPage('abc123', 'token456');

        $this->assertStringContainsString('name="_csrf_token"', $result);
    }

    public function testRenderResetPasswordPageEscapesSelectorAndToken(): void
    {
        $result = $this->view->renderResetPasswordPage('"><script>xss</script>', 'normal');

        $this->assertStringNotContainsString('<script>xss</script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testRenderResetPasswordPageContainsSignInLink(): void
    {
        $result = $this->view->renderResetPasswordPage('abc', 'def');

        $this->assertStringContainsString('Back to Sign In', $result);
    }

    // =========================================================================
    // Password Reset Success Page
    // =========================================================================

    public function testRenderPasswordResetSuccessPageShowsSuccess(): void
    {
        $result = $this->view->renderPasswordResetSuccessPage();

        $this->assertStringContainsString('auth-status__icon--success', $result);
        $this->assertStringContainsString('Password Changed', $result);
        $this->assertStringContainsString('Sign In', $result);
    }

    // =========================================================================
    // Password Reset Error Page
    // =========================================================================

    public function testRenderPasswordResetErrorPageShowsError(): void
    {
        $result = $this->view->renderPasswordResetErrorPage('Token has expired');

        $this->assertStringContainsString('auth-status__icon--error', $result);
        $this->assertStringContainsString('Reset Error', $result);
        $this->assertStringContainsString('Token has expired', $result);
        $this->assertStringContainsString('Try Again', $result);
    }

    public function testRenderPasswordResetErrorPageEscapesXss(): void
    {
        $result = $this->view->renderPasswordResetErrorPage('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testRenderPasswordResetErrorPageLinksToForgotPassword(): void
    {
        $result = $this->view->renderPasswordResetErrorPage('Error');

        $this->assertStringContainsString('op=pass_lost', $result);
    }

    // =========================================================================
    // Activation Success Page
    // =========================================================================

    public function testRenderActivationSuccessPageShowsSuccess(): void
    {
        $result = $this->view->renderActivationSuccessPage('NewPlayer');

        $this->assertStringContainsString('auth-status__icon--success', $result);
        $this->assertStringContainsString('Account Activated', $result);
        $this->assertStringContainsString('NewPlayer', $result);
        $this->assertStringContainsString('Sign In', $result);
    }

    public function testRenderActivationSuccessPageEscapesXss(): void
    {
        $result = $this->view->renderActivationSuccessPage('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    // =========================================================================
    // Activation Error Page
    // =========================================================================

    public function testRenderActivationErrorPageShowsMismatchError(): void
    {
        $result = $this->view->renderActivationErrorPage('mismatch');

        $this->assertStringContainsString('auth-status__icon--error', $result);
        $this->assertStringContainsString('Activation Error', $result);
        $this->assertStringContainsString('does not match', $result);
    }

    public function testRenderActivationErrorPageShowsExpiredError(): void
    {
        $result = $this->view->renderActivationErrorPage('expired');

        $this->assertStringContainsString('auth-status__icon--error', $result);
        $this->assertStringContainsString('expired', $result);
        $this->assertStringContainsString('Register Again', $result);
    }

    // =========================================================================
    // Logout Page
    // =========================================================================

    public function testRenderLogoutPageShowsMessage(): void
    {
        $result = $this->view->renderLogoutPage(null);

        $this->assertStringContainsString('auth-status__icon--info', $result);
        $this->assertStringContainsString('Logged Out', $result);
        $this->assertStringContainsString('meta http-equiv="refresh"', $result);
        $this->assertStringContainsString('index.php', $result);
    }

    public function testRenderLogoutPageUsesRedirect(): void
    {
        $result = $this->view->renderLogoutPage('Trading');

        $this->assertStringContainsString('modules.php?name=Trading', $result);
    }

    public function testRenderLogoutPageUsesDefaultRedirectForEmpty(): void
    {
        $result = $this->view->renderLogoutPage('');

        $this->assertStringContainsString('index.php', $result);
    }

    // =========================================================================
    // User Not Found Page
    // =========================================================================

    public function testRenderUserNotFoundPageShowsError(): void
    {
        $result = $this->view->renderUserNotFoundPage();

        $this->assertStringContainsString('auth-status__icon--error', $result);
        $this->assertStringContainsString('User Not Found', $result);
        $this->assertStringContainsString('Try Again', $result);
        $this->assertStringContainsString('op=pass_lost', $result);
    }
}
