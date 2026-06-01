<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * ApiKeys/index.php calls loginbox() (→ die()) for unauthenticated users.
 * Unauthenticated path covered by E2E — same pattern as FreeAgency.
 *
 * The module also calls $authService->getUserId() and getUsername(),
 * which the base authenticateAs() doesn't stub. Tests override the
 * auth stub locally.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ApiKeysEntryPointTest extends ModuleEntryPointTestCase
{
    private function authenticateWithUserId(string $username, int $userId): void
    {
        $this->authenticateAs($username);

        $authStub = self::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $authStub->method('isAuthenticated')->willReturn(true);
        $authStub->method('isAdmin')->willReturn(false);
        $authStub->method('getCookieArray')->willReturn([$username, $username, '']);
        $authStub->method('getUserId')->willReturn($userId);
        $authStub->method('getUsername')->willReturn($username);
        $GLOBALS['authService'] = $authStub;
    }

    public function testMainOpRendersApiKeyListForAuthenticatedUser(): void
    {
        $this->authenticateWithUserId('testgm', 1);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('ApiKeys', [], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
        ]));

        $this->assertNotEmpty($output);
    }

    public function testGenerateOpRequiresPostMethod(): void
    {
        $this->authenticateWithUserId('testgm', 1);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->mockDb->setMockData([]);

        $output = $this->runModule('ApiKeys', ['op' => 'generate'], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
        ]));

        // GET to generate redirects to main (header('Location: ...'); return;)
        $this->assertEmpty($output);
    }

    public function testRevokeOpRequiresPostMethod(): void
    {
        $this->authenticateWithUserId('testgm', 1);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->mockDb->setMockData([]);

        $output = $this->runModule('ApiKeys', ['op' => 'revoke'], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
        ]));

        // GET to revoke redirects to main (header('Location: ...'); return;)
        $this->assertEmpty($output);
    }
}
