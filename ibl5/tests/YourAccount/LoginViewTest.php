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

    public function testLoginPageEscapesErrorMessage(): void
    {
        $payload = '<script>alert(1)</script>';

        $html = $this->view->renderLoginPage($payload);

        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringNotContainsStringIgnoringCase($payload, $html);
        self::assertStringContainsString('ibl-alert--error', $html);
    }

    public function testLoginPageOmitsErrorBlockWhenErrorIsNull(): void
    {
        $html = $this->view->renderLoginPage(null);

        self::assertStringNotContainsString('ibl-alert--error', $html);
    }

    public function testLoginPageIncludesCsrfToken(): void
    {
        $html = $this->view->renderLoginPage(null);

        self::assertStringContainsString('name="_csrf_token"', $html);
        self::assertStringContainsString('name="op" value="login"', $html);
    }

    public function testLoginFormContainsUsernameAndPasswordFields(): void
    {
        $html = $this->view->renderLoginPage(null);

        self::assertStringContainsString('name="username"', $html);
        self::assertStringContainsString('name="user_password"', $html);
    }

    public function testLoginPageConvertsErrorNewlinesToBreaks(): void
    {
        $html = $this->view->renderLoginPage("Line one\nLine two");

        self::assertStringContainsString('Line one<br />', $html);
    }
}
