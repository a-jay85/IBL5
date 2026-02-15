<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use YourAccount\YourAccountView;

/**
 * Tests for YourAccountView rendering methods.
 *
 * Verifies HTML structure, XSS safety, correct form fields,
 * and cross-navigation links for all auth pages.
 */
class YourAccountViewTest extends TestCase
{
    private YourAccountView $view;

    protected function setUp(): void
    {
        $this->view = new YourAccountView();
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
        $result = $this->view->renderRegisterPage(123456, false);

        $this->assertStringContainsString('auth-page', $result);
        $this->assertStringContainsString('Create Account', $result);
        $this->assertStringContainsString('Join the IBL community', $result);
    }

    public function testRenderRegisterPageContainsFormFields(): void
    {
        $result = $this->view->renderRegisterPage(123456, false);

        $this->assertStringContainsString('name="username"', $result);
        $this->assertStringContainsString('name="user_email"', $result);
        $this->assertStringContainsString('name="user_password"', $result);
        $this->assertStringContainsString('name="user_password2"', $result);
        $this->assertStringContainsString('name="op" value="new user"', $result);
    }

    public function testRenderRegisterPageContainsCrossNavLinks(): void
    {
        $result = $this->view->renderRegisterPage(123456, false);

        $this->assertStringContainsString('Already have an account?', $result);
        $this->assertStringContainsString('modules.php?name=YourAccount"', $result);
    }

    public function testRenderRegisterPageShowsCaptchaWhenEnabled(): void
    {
        $result = $this->view->renderRegisterPage(888, true);

        $this->assertStringContainsString('name="gfx_check"', $result);
        $this->assertStringContainsString('name="random_num" value="888"', $result);
    }

    public function testRenderRegisterPageContainsEmailActivationNotice(): void
    {
        $result = $this->view->renderRegisterPage(123456, false);

        $this->assertStringContainsString('activation link', $result);
    }

    // =========================================================================
    // Registration Confirm Page
    // =========================================================================

    public function testRenderRegistrationConfirmPageShowsUserData(): void
    {
        $result = $this->view->renderRegistrationConfirmPage('TestUser', 'test@example.com', 'secret', 123, 'abc');

        $this->assertStringContainsString('Confirm Registration', $result);
        $this->assertStringContainsString('TestUser', $result);
        $this->assertStringContainsString('test@example.com', $result);
        $this->assertStringContainsString('name="op" value="finish"', $result);
    }

    public function testRenderRegistrationConfirmPageEscapesXss(): void
    {
        $result = $this->view->renderRegistrationConfirmPage('<script>alert(1)</script>', 'test@test.com', 'pw', 1, 'x');

        $this->assertStringNotContainsString('<script>alert(1)</script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
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
    // Forgot Password Page
    // =========================================================================

    public function testRenderForgotPasswordPageReturnsHtml(): void
    {
        $result = $this->view->renderForgotPasswordPage();

        $this->assertStringContainsString('auth-page', $result);
        $this->assertStringContainsString('Reset Password', $result);
        $this->assertStringContainsString('name="username"', $result);
        $this->assertStringContainsString('name="code"', $result);
        $this->assertStringContainsString('name="op" value="mailpasswd"', $result);
    }

    public function testRenderForgotPasswordPageContainsCrossNavLinks(): void
    {
        $result = $this->view->renderForgotPasswordPage();

        $this->assertStringContainsString('Remember your password?', $result);
        $this->assertStringContainsString('Need an account?', $result);
    }

    // =========================================================================
    // Code Mailed Page
    // =========================================================================

    public function testRenderCodeMailedPageShowsInfo(): void
    {
        $result = $this->view->renderCodeMailedPage('TestUser');

        $this->assertStringContainsString('auth-status__icon--info', $result);
        $this->assertStringContainsString('Code Sent', $result);
        $this->assertStringContainsString('TestUser', $result);
        $this->assertStringContainsString('Enter Reset Code', $result);
    }

    public function testRenderCodeMailedPageEscapesXss(): void
    {
        $result = $this->view->renderCodeMailedPage('<img src=x onerror=alert(1)>');

        $this->assertStringNotContainsString('<img src=x', $result);
        $this->assertStringContainsString('&lt;img', $result);
    }

    // =========================================================================
    // Password Mailed Page
    // =========================================================================

    public function testRenderPasswordMailedPageShowsSuccess(): void
    {
        $result = $this->view->renderPasswordMailedPage('TestUser');

        $this->assertStringContainsString('auth-status__icon--success', $result);
        $this->assertStringContainsString('Password Reset', $result);
        $this->assertStringContainsString('TestUser', $result);
        $this->assertStringContainsString('Sign In', $result);
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
