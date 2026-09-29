<?php

declare(strict_types=1);

namespace Tests\YourAccount;

use PHPUnit\Framework\TestCase;
use YourAccount\PasswordResetView;

/**
 * @covers \YourAccount\PasswordResetView
 */
final class PasswordResetViewTest extends TestCase
{
    private PasswordResetView $view;

    protected function setUp(): void
    {
        $this->view = new PasswordResetView();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function testResetPasswordPageEscapesSelectorAttribute(): void
    {
        $payload = '"onload="alert(1)';

        $html = $this->view->renderResetPasswordPage($payload, 'token123');

        // The " chars in the payload must be entity-encoded so the attribute cannot break out
        self::assertStringNotContainsString('"onload="alert(1)', $html);
        self::assertStringContainsString('&quot;', $html);
    }

    public function testResetPasswordPageEscapesTokenAttribute(): void
    {
        $payload = '"onload="alert(1)';

        $html = $this->view->renderResetPasswordPage('selector123', $payload);

        self::assertStringNotContainsString('"onload="alert(1)', $html);
        self::assertStringContainsString('&quot;', $html);
    }

    public function testResetPasswordPageEscapesSelectorScriptPayload(): void
    {
        $payload = '<script>alert(1)</script>';

        $html = $this->view->renderResetPasswordPage($payload, 'token');

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testResetPasswordPageContainsCsrfToken(): void
    {
        $html = $this->view->renderResetPasswordPage('sel', 'tok');

        self::assertStringContainsString('_csrf_token', $html);
    }

    public function testResetPasswordPageContainsSelectorAndTokenFields(): void
    {
        $html = $this->view->renderResetPasswordPage('myselector', 'mytoken');

        self::assertStringContainsString('name="selector"', $html);
        self::assertStringContainsString('name="token"', $html);
        // Values are present (escaped but recognizable for safe inputs)
        self::assertStringContainsString('myselector', $html);
        self::assertStringContainsString('mytoken', $html);
    }
}
