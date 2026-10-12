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

    public function testErrorPageMismatchShowsMismatchMessage(): void
    {
        $html = $this->view->renderActivationErrorPage('mismatch');

        self::assertStringContainsString('The activation code does not match.', $html);
        self::assertStringNotContainsString('has expired or is invalid', $html);
    }

    public function testErrorPageUnknownTypeShowsExpiredMessageWithoutEchoingInput(): void
    {
        $payload = '<script>alert(1)</script>';

        $html = $this->view->renderActivationErrorPage($payload);

        self::assertStringContainsString('has expired or is invalid', $html);
        self::assertStringNotContainsStringIgnoringCase($payload, $html);
        self::assertStringNotContainsString('&lt;script&gt;', $html);
    }

    public function testSuccessPageAttributeBreakoutPrevented(): void
    {
        $payload = '"onload="alert(1)';

        $html = $this->view->renderActivationSuccessPage($payload);

        self::assertStringNotContainsString('"onload="alert(1)', $html);
    }
}
