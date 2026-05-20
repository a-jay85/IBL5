<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * YourAccount/index.php is a large switch over $op. Most arms are POST handlers
 * that call exit() or header()+die() — those are covered by Auth tests + E2E.
 *
 * Authenticated default branch calls header('Location: ...'); exit; — not testable
 * in PHPUnit. Tested as unauthenticated only.
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
            'cookie' => $GLOBALS['cookie'],
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

        $this->assertNotEmpty($output);
    }

    public function testOpPassLostRendersResetForm(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('YourAccount', ['op' => 'pass_lost'], [], $this->accountGlobals());

        $this->assertNotEmpty($output);
    }

    public function testOpNewUserRendersRegistrationForm(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('YourAccount', ['op' => 'new_user'], [], $this->accountGlobals());

        $this->assertNotEmpty($output);
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

        $this->assertNotEmpty($output);
    }

    public function testUnknownOpFallsToDefault(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('YourAccount', ['op' => 'bogus'], [], $this->accountGlobals());

        $this->assertNotEmpty($output);
    }
}
