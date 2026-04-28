<?php

declare(strict_types=1);

namespace Tests\Debug;

use Debug\DebugSession;
use PHPUnit\Framework\TestCase;

class DebugSessionTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    public function testIsDebugAdminReturnsTrueForAJayOnLocalhost(): void
    {
        $session = new DebugSession('A-Jay', 'main.localhost');
        $this->assertTrue($session->isDebugAdmin());
    }

    public function testIsDebugAdminReturnsTrueForPlainLocalhost(): void
    {
        $session = new DebugSession('A-Jay', 'localhost');
        $this->assertTrue($session->isDebugAdmin());
    }

    public function testIsDebugAdminReturnsFalseForNonAJayUser(): void
    {
        $session = new DebugSession('SomeUser', 'main.localhost');
        $this->assertFalse($session->isDebugAdmin());
    }

    public function testIsDebugAdminReturnsFalseForNonLocalhost(): void
    {
        $session = new DebugSession('A-Jay', 'iblhoops.net');
        $this->assertFalse($session->isDebugAdmin());
    }

    public function testIsDebugAdminReturnsFalseForNullUsername(): void
    {
        $session = new DebugSession(null, 'main.localhost');
        $this->assertFalse($session->isDebugAdmin());
    }

    public function testIsDebugAdminReturnsFalseForNullServerName(): void
    {
        $session = new DebugSession('A-Jay', null);
        $this->assertFalse($session->isDebugAdmin());
    }

    public function testIsDebugAdminReturnsFalseWhenSessionHasKeyButUserIsWrong(): void
    {
        $_SESSION['debug_view_all_extensions'] = true;
        $session = new DebugSession('OtherUser', 'main.localhost');
        $this->assertFalse($session->isDebugAdmin());
        $this->assertFalse($session->isViewAllExtensionsEnabled());
    }

    public function testViewAllExtensionsDefaultsToFalse(): void
    {
        $session = new DebugSession('A-Jay', 'main.localhost');
        $this->assertFalse($session->isViewAllExtensionsEnabled());
    }

    public function testToggleEnablesViewAllExtensions(): void
    {
        $session = new DebugSession('A-Jay', 'main.localhost');
        $this->assertFalse($session->isViewAllExtensionsEnabled());

        @$session->toggleViewAllExtensions();
        $this->assertTrue($session->isViewAllExtensionsEnabled());
    }

    public function testToggleDisablesViewAllExtensions(): void
    {
        $_SESSION['debug_view_all_extensions'] = true;
        $session = new DebugSession('A-Jay', 'main.localhost');
        $this->assertTrue($session->isViewAllExtensionsEnabled());

        @$session->toggleViewAllExtensions();
        $this->assertFalse($session->isViewAllExtensionsEnabled());
    }

    public function testToggleDoesNothingForNonAdmin(): void
    {
        $session = new DebugSession('SomeUser', 'main.localhost');
        @$session->toggleViewAllExtensions();
        $this->assertFalse($session->isViewAllExtensionsEnabled());
        $this->assertArrayNotHasKey('debug_view_all_extensions', $_SESSION);
    }

    public function testHydratesSessionFromCookieValue(): void
    {
        $session = new DebugSession('A-Jay', 'main.localhost', '1');
        $this->assertTrue($session->isViewAllExtensionsEnabled());
    }

    public function testDoesNotHydrateFromCookieIfAlreadyInSession(): void
    {
        $_SESSION['debug_view_all_extensions'] = true;
        $session = new DebugSession('A-Jay', 'main.localhost', '0');
        $this->assertTrue($session->isViewAllExtensionsEnabled());
    }

    public function testDoesNotHydrateFromCookieForNonAdmin(): void
    {
        $session = new DebugSession('Other', 'main.localhost', '1');
        $this->assertFalse($session->isViewAllExtensionsEnabled());
    }

    public function testViewAllExtensionsReturnsFalseOnProductionEvenWithSession(): void
    {
        $_SESSION['debug_view_all_extensions'] = true;
        $session = new DebugSession('A-Jay', 'iblhoops.net');
        $this->assertFalse($session->isViewAllExtensionsEnabled());
    }
}
