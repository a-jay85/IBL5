<?php

declare(strict_types=1);

namespace Tests\YourAccount;

use PHPUnit\Framework\TestCase;
use YourAccount\YourAccountView;

final class YourAccountViewXssTest extends TestCase
{
    private YourAccountView $view;

    protected function setUp(): void
    {
        $this->view = new YourAccountView();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function testRegistrationErrorPageMessageIsEscaped(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $output = $this->view->renderRegistrationErrorPage($xss);

        $this->assertStringContainsString($escaped, $output);
        $this->assertStringNotContainsString($xss, $output);
    }

    public function testActivationSuccessUsernameIsEscaped(): void
    {
        $xss = '<script>alert(1)</script>';
        $escaped = '&lt;script&gt;';

        $output = $this->view->renderActivationSuccessPage($xss);

        $this->assertStringContainsString($escaped, $output);
        $this->assertStringNotContainsString($xss, $output);
    }
}
