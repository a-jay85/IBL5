<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * YourAccount/index.php is a large switch over $op. Most arms are POST handlers
 * that call exit() or header()+die() — those are covered by Auth tests + E2E.
 *
 * Authenticated branches call header('Location: ...'); exit; — the sentinel auth
 * stub (authenticateWithSentinel) pins them by throwing from the first auth call
 * after the gate. The other ops are tested as unauthenticated.
 *
 * Uses RunTestsInSeparateProcesses because is_user() has a static cache and
 * multiple ops manipulate session/cookie state.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class YourAccountEntryPointTest extends ModuleEntryPointTestCase
{
    /** @return array<string, mixed> */
    private function accountGlobals(): array
    {
        return array_merge($this->dbGlobals(), [
            'authService' => $GLOBALS['authService'],
            'user' => $GLOBALS['user'],
            'cookie' => $GLOBALS['cookie'] ?? null,
            'nukeurl' => 'http://localhost',
            'sitename' => 'IBL Test',
            'adminmail' => 'admin@test.com',
            'minpass' => 5,
        ]);
    }

    public function testDefaultOpRendersLoginPageForUnauthenticated(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('YourAccount', [], [], $this->accountGlobals());

        $this->assertNotSame('', $output);
    }

    public function testOpPassLostRendersResetForm(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('YourAccount', ['op' => 'pass_lost'], [], $this->accountGlobals());

        $this->assertNotSame('', $output);
    }

    public function testOpNewUserRendersRegistrationForm(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('YourAccount', ['op' => 'new_user'], [], $this->accountGlobals());

        $this->assertNotSame('', $output);
    }

    public function testOpConfirmEmailWithTokenRendersStatus(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule(
            'YourAccount',
            ['selector' => 'abc123', 'token' => 'xyz789', 'op' => 'confirm_email'],
            [],
            $this->accountGlobals(),
        );

        $this->assertNotSame('', $output);
    }

    public function testUnknownOpFallsToDefault(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('YourAccount', ['op' => 'bogus'], [], $this->accountGlobals());

        $this->assertNotSame('', $output);
    }

    public function testDefaultOpAuthenticatedDecodesCookieBeforeRedirect(): void
    {
        $this->authenticateWithSentinel('testgm', true, 'getCookieArray');
        $this->mockDb->setMockData([]);
        $this->assertSentinelThrown('getCookieArray', function (): void {
            $this->runModule('YourAccount', [], [], $this->accountGlobals());
        });
    }

    public function testPassLostAuthenticatedTakesRedirectBranch(): void
    {
        $this->authenticateWithSentinel('testgm', true, 'getUsername');
        $this->mockDb->setMockData([]);
        $this->assertSentinelThrown('getUsername', function (): void {
            $this->runModule('YourAccount', ['op' => 'pass_lost'], [], $this->accountGlobals());
        });
    }

    public function testNewUserAuthenticatedTakesRedirectBranch(): void
    {
        $this->authenticateWithSentinel('testgm', true, 'getUsername');
        $this->mockDb->setMockData([]);
        $this->assertSentinelThrown('getUsername', function (): void {
            $this->runModule('YourAccount', ['op' => 'new_user'], [], $this->accountGlobals());
        });
    }

    public function testResetPasswordUnauthenticatedWithTokenRendersForm(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule(
            'YourAccount',
            ['op' => 'reset_password', 'selector' => 'abc', 'token' => 'def'],
            [],
            $this->accountGlobals(),
        );

        $this->assertNotSame('', $output);
        $this->assertStringContainsString('abc', $output);
    }
}
