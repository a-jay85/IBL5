<?php

declare(strict_types=1);

namespace Tests\YourAccount;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the buildRedirectUrl() function in mainfile.php.
 *
 * Verifies session-based redirect URL building with validation,
 * sanitization, and loop prevention.
 */
class BuildRedirectUrlTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['redirect_after_login'], $_SESSION['redirect_after_login_path']);

        // Ensure the function is available (defined in includes/buildRedirectUrl.php)
        if (!function_exists('buildRedirectUrl')) {
            require_once dirname(__DIR__, 2) . '/includes/buildRedirectUrl.php';
        }
    }

    protected function tearDown(): void
    {
        unset($_SESSION['redirect_after_login'], $_SESSION['redirect_after_login_path']);
    }

    public function testReturnsNullWhenSessionNotSet(): void
    {
        $this->assertNull(buildRedirectUrl());
    }

    public function testReturnsNullWhenSessionIsEmpty(): void
    {
        $_SESSION['redirect_after_login'] = '';

        $this->assertNull(buildRedirectUrl());
    }

    public function testReturnsNullWhenNameIsMissing(): void
    {
        $_SESSION['redirect_after_login'] = 'op=offertrade&partner=Sting';

        $this->assertNull(buildRedirectUrl());
    }

    public function testReturnsNullWhenNameIsInvalid(): void
    {
        $_SESSION['redirect_after_login'] = 'name=../etc/passwd';

        $this->assertNull(buildRedirectUrl());
    }

    public function testReturnsNullForYourAccountToPreventLoop(): void
    {
        $_SESSION['redirect_after_login'] = 'name=YourAccount';

        $this->assertNull(buildRedirectUrl());
    }

    public function testBuildsUrlForSimpleModule(): void
    {
        $_SESSION['redirect_after_login'] = 'name=Trading';

        $result = buildRedirectUrl();

        $this->assertSame('modules.php?name=Trading', $result);
    }

    public function testBuildsUrlWithFullQueryString(): void
    {
        $_SESSION['redirect_after_login'] = 'name=Trading&op=offertrade&partner=Sting';

        $result = buildRedirectUrl();

        $this->assertSame('modules.php?name=Trading&op=offertrade&partner=Sting', $result);
    }

    public function testClearsSessionAfterUse(): void
    {
        $_SESSION['redirect_after_login'] = 'name=Trading';

        buildRedirectUrl();

        $this->assertArrayNotHasKey('redirect_after_login', $_SESSION);
    }

    public function testFiltersOutArrayParams(): void
    {
        $_SESSION['redirect_after_login'] = 'name=Trading&foo[]=bar&op=list';

        $result = buildRedirectUrl();

        $this->assertSame('modules.php?name=Trading&op=list', $result);
        $this->assertStringNotContainsString('foo', (string) $result);
    }

    public function testEncodesSpecialCharacters(): void
    {
        $_SESSION['redirect_after_login'] = 'name=Trading&partner=A%20B%26C';

        $result = buildRedirectUrl();

        $this->assertNotNull($result);
        $this->assertStringContainsString('name=Trading', $result);
        // http_build_query re-encodes the decoded value
        $this->assertStringContainsString('partner=', $result);
    }

    public function testReturnsNullWhenSessionIsNotString(): void
    {
        $_SESSION['redirect_after_login'] = 12345;

        $this->assertNull(buildRedirectUrl());
    }

    public function testClearsSessionEvenWhenModuleNameInvalid(): void
    {
        $_SESSION['redirect_after_login'] = 'name=<script>';

        buildRedirectUrl();

        $this->assertArrayNotHasKey('redirect_after_login', $_SESSION);
    }

    public function testAcceptsModuleNameWithUnderscores(): void
    {
        $_SESSION['redirect_after_login'] = 'name=My_Module&op=view';

        $result = buildRedirectUrl();

        $this->assertSame('modules.php?name=My_Module&op=view', $result);
    }

    public function testAcceptsModuleNameWithNumbers(): void
    {
        $_SESSION['redirect_after_login'] = 'name=Module123';

        $result = buildRedirectUrl();

        $this->assertSame('modules.php?name=Module123', $result);
    }

    public function testReturnsStandalonePagePath(): void
    {
        $_SESSION['redirect_after_login_path'] = 'leagueControlPanel.php';

        $result = buildRedirectUrl();

        $this->assertSame('leagueControlPanel.php', $result);
    }

    public function testReturnsStandalonePagePathWithQueryString(): void
    {
        $_SESSION['redirect_after_login_path'] = 'scripts/updateAllTheThings.php?league=olympics';

        $result = buildRedirectUrl();

        $this->assertSame('scripts/updateAllTheThings.php?league=olympics', $result);
    }

    public function testRejectsNonWhitelistedStandalonePath(): void
    {
        $_SESSION['redirect_after_login_path'] = 'malicious.php';

        $result = buildRedirectUrl();

        $this->assertNull($result);
    }

    public function testClearsStandalonePathSessionAfterUse(): void
    {
        $_SESSION['redirect_after_login_path'] = 'leagueControlPanel.php';

        buildRedirectUrl();

        $this->assertArrayNotHasKey('redirect_after_login_path', $_SESSION);
    }

    public function testReturnsIblSchedulePath(): void
    {
        $_SESSION['redirect_after_login_path'] = 'ibl/IBL/Schedule.htm';

        $result = buildRedirectUrl();

        $this->assertSame('ibl/IBL/Schedule.htm', $result);
    }

    public function testReturnsIblStandingsPath(): void
    {
        $_SESSION['redirect_after_login_path'] = 'ibl/IBL/Standings.htm';

        $result = buildRedirectUrl();

        $this->assertSame('ibl/IBL/Standings.htm', $result);
    }

    public function testStandalonePathTakesPriorityOverModuleRedirect(): void
    {
        $_SESSION['redirect_after_login_path'] = 'leagueControlPanel.php';
        $_SESSION['redirect_after_login'] = 'name=Trading';

        $result = buildRedirectUrl();

        $this->assertSame('leagueControlPanel.php', $result);
        $this->assertArrayHasKey('redirect_after_login', $_SESSION);
    }
}
