<?php

declare(strict_types=1);

namespace Tests\YourAccount;

use PHPUnit\Framework\TestCase;
use YourAccount\ActivationView;

/**
 * @covers \YourAccount\ActivationView
 */
final class ActivationViewTest extends TestCase
{
    private ActivationView $view;

    protected function setUp(): void
    {
        $this->view = new ActivationView();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function testSuccessPageEscapesUsername(): void
    {
        $payload = '<script>alert(1)</script>';

        $html = $this->view->renderActivationSuccessPage($payload);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testSuccessPageContainsUsername(): void
    {
        $html = $this->view->renderActivationSuccessPage('testuser');

        self::assertStringContainsString('testuser', $html);
        self::assertStringContainsString('activated successfully', $html);
    }

    public function testErrorPageShowsMismatchMessage(): void
    {
        $html = $this->view->renderActivationErrorPage('mismatch');

        self::assertStringContainsString('activation code does not match', $html);
    }

    public function testErrorPageShowsExpiredMessageForOtherErrorType(): void
    {
        $html = $this->view->renderActivationErrorPage('expired');

        self::assertStringContainsString('expired or is invalid', $html);
    }

    public function testSuccessPageAttributeBreakoutPrevented(): void
    {
        $payload = '"onload="alert(1)';

        $html = $this->view->renderActivationSuccessPage($payload);

        self::assertStringNotContainsString('"onload="alert(1)', $html);
    }
}
