<?php

declare(strict_types=1);

namespace Tests\Navigation\Views;

use Navigation\Views\LoginFormView;
use PHPUnit\Framework\TestCase;

class LoginFormViewTest extends TestCase
{
    private LoginFormView $view;

    protected function setUp(): void
    {
        $this->view = new LoginFormView();
    }

    public function testDesktopVariantHasDesktopSizingClasses(): void
    {
        $html = $this->view->render('desktop', '/ibl5/index.php');

        $this->assertStringContainsString('id="nav-username"', $html);
        $this->assertStringContainsString('id="nav-password"', $html);
        $this->assertStringContainsString('rounded-lg', $html);
        $this->assertStringContainsString('w-4 h-4', $html);
    }

    public function testMobileVariantHasMobileSizingClasses(): void
    {
        $html = $this->view->render('mobile', '/ibl5/index.php');

        $this->assertStringContainsString('id="mobile-nav-username"', $html);
        $this->assertStringContainsString('id="mobile-nav-password"', $html);
        $this->assertStringContainsString('rounded-xl', $html);
        $this->assertStringContainsString('w-5 h-5', $html);
    }

    public function testCsrfTokenPresent(): void
    {
        $html = $this->view->render('desktop', '/ibl5/index.php');

        $this->assertStringContainsString('csrf', $html);
    }

    public function testRedirectQueryFieldPresent(): void
    {
        $html = $this->view->render('desktop', '/ibl5/index.php?name=Standings');

        $this->assertStringContainsString('name="redirect_query"', $html);
        $this->assertStringContainsString('name=Standings', $html);
    }
}
