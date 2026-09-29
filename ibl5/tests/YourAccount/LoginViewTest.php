<?php

declare(strict_types=1);

namespace Tests\YourAccount;

use PHPUnit\Framework\TestCase;
use YourAccount\LoginView;

/**
 * @covers \YourAccount\LoginView
 */
final class LoginViewTest extends TestCase
{
    private LoginView $view;

    protected function setUp(): void
    {
        $this->view = new LoginView();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function testLoginErrorIsEscaped(): void
    {
        $payload = '<script>alert(1)</script>';

        $html = $this->view->renderLoginPage($payload);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testNullErrorOmitsAlertBlock(): void
    {
        $html = $this->view->renderLoginPage(null);

        self::assertStringNotContainsString('ibl-alert--error', $html);
    }

    public function testCsrfTokenPresentInForm(): void
    {
        $html = $this->view->renderLoginPage(null);

        // CsrfGuard::generateToken() renders a hidden input for the CSRF token
        self::assertStringContainsString('type="hidden"', $html);
        self::assertStringContainsString('_csrf_token', $html);
    }

    public function testLoginFormContainsUsernameAndPasswordFields(): void
    {
        $html = $this->view->renderLoginPage(null);

        self::assertStringContainsString('name="username"', $html);
        self::assertStringContainsString('name="user_password"', $html);
    }

    public function testLoginErrorAppliesNl2br(): void
    {
        $html = $this->view->renderLoginPage("line one\nline two");

        self::assertStringContainsString('<br />', $html);
    }
}
