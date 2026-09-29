<?php

declare(strict_types=1);

namespace Tests\YourAccount;

use PHPUnit\Framework\TestCase;
use YourAccount\RegistrationView;

/**
 * @covers \YourAccount\RegistrationView
 */
final class RegistrationViewTest extends TestCase
{
    private RegistrationView $view;

    protected function setUp(): void
    {
        $this->view = new RegistrationView();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function testRegistrationCompletePageEscapesSiteName(): void
    {
        $payload = '<script>alert(1)</script>';

        $html = $this->view->renderRegistrationCompletePage($payload);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testRegistrationCompletePageContainsSiteName(): void
    {
        $html = $this->view->renderRegistrationCompletePage('MyLeague');

        self::assertStringContainsString('MyLeague', $html);
        self::assertStringContainsString('Account Created', $html);
    }

    public function testRegistrationErrorPageEscapesError(): void
    {
        $payload = '<script>alert(1)</script>';

        $html = $this->view->renderRegistrationErrorPage($payload);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testRegistrationErrorPageAttributeBreakoutPrevented(): void
    {
        $payload = '"onload="alert(1)';

        $html = $this->view->renderRegistrationErrorPage($payload);

        self::assertStringNotContainsString('"onload="alert(1)', $html);
    }

    public function testRegisterPageContainsFormFields(): void
    {
        $html = $this->view->renderRegisterPage();

        self::assertStringContainsString('name="username"', $html);
        self::assertStringContainsString('name="user_email"', $html);
    }
}
