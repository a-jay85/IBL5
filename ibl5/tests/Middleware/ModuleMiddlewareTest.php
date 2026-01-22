<?php

declare(strict_types=1);

namespace Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Middleware\ModuleMiddleware;
use Auth\LaravelAuthBridge;
use Auth\User;

class ModuleMiddlewareTest extends TestCase
{
    /** @var \mysqli&\PHPUnit\Framework\MockObject\MockObject */
    private \mysqli $mockDb;

    /** @var LaravelAuthBridge&\PHPUnit\Framework\MockObject\MockObject */
    private LaravelAuthBridge $mockAuthBridge;

    private ModuleMiddleware $middleware;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(\mysqli::class);
        $this->mockAuthBridge = $this->createMock(LaravelAuthBridge::class);
        $this->middleware = new ModuleMiddleware(
            $this->mockDb,
            $this->mockAuthBridge,
            'nuke'
        );
    }

    public function testAuthorizePublicModuleReturnsTrue(): void
    {
        $this->setupModuleQuery('PublicModule', 1, ModuleMiddleware::ACCESS_PUBLIC);

        $result = $this->middleware->authorize('PublicModule');

        $this->assertTrue($result);
        $this->assertNull($this->middleware->getDenialReason());
    }

    public function testAuthorizeInactiveModuleDeniesNonAdmin(): void
    {
        $this->setupModuleQuery('InactiveModule', 0, ModuleMiddleware::ACCESS_PUBLIC);
        $this->mockAuthBridge->method('isAdmin')->willReturn(false);

        $result = $this->middleware->authorize('InactiveModule');

        $this->assertFalse($result);
        $this->assertEquals('Module is not active', $this->middleware->getDenialReason());
    }

    public function testAuthorizeInactiveModuleAllowsAdmin(): void
    {
        $this->setupModuleQuery('InactiveModule', 0, ModuleMiddleware::ACCESS_PUBLIC);
        $this->mockAuthBridge->method('isAdmin')->willReturn(true);

        $result = $this->middleware->authorize('InactiveModule');

        $this->assertTrue($result);
    }

    public function testAuthorizeUserModuleDeniesGuest(): void
    {
        $this->setupModuleQuery('UserModule', 1, ModuleMiddleware::ACCESS_USERS);
        $this->mockAuthBridge->method('isAdmin')->willReturn(false);
        $this->mockAuthBridge->method('isUser')->willReturn(false);

        $result = $this->middleware->authorize('UserModule');

        $this->assertFalse($result);
        $this->assertEquals(
            'This module requires a registered user account',
            $this->middleware->getDenialReason()
        );
    }

    public function testAuthorizeUserModuleAllowsLoggedInUser(): void
    {
        $this->setupModuleQuery('UserModule', 1, ModuleMiddleware::ACCESS_USERS);
        $this->setupGroupQuery(0); // No group restriction
        $this->mockAuthBridge->method('isAdmin')->willReturn(false);
        $this->mockAuthBridge->method('isUser')->willReturn(true);
        $this->mockAuthBridge->method('getUser')->willReturn(new User(['role' => User::ROLE_OWNER]));

        $result = $this->middleware->authorize('UserModule');

        $this->assertTrue($result);
    }

    public function testAuthorizeAdminModuleDeniesRegularUser(): void
    {
        $this->setupModuleQuery('AdminModule', 1, ModuleMiddleware::ACCESS_ADMINS);
        $this->mockAuthBridge->method('isAdmin')->willReturn(false);

        $result = $this->middleware->authorize('AdminModule');

        $this->assertFalse($result);
        $this->assertEquals(
            'This module is for administrators only',
            $this->middleware->getDenialReason()
        );
    }

    public function testAuthorizeAdminModuleAllowsCommissioner(): void
    {
        $this->setupModuleQuery('AdminModule', 1, ModuleMiddleware::ACCESS_ADMINS);
        $this->mockAuthBridge->method('isAdmin')->willReturn(true);

        $result = $this->middleware->authorize('AdminModule');

        $this->assertTrue($result);
    }

    public function testAuthorizeSubscriberModuleDeniesNonSubscriber(): void
    {
        $this->setupModuleQuery('PaidModule', 1, ModuleMiddleware::ACCESS_SUBSCRIBERS);
        $this->mockAuthBridge->method('hasRole')->with(User::ROLE_COMMISSIONER)->willReturn(false);

        $result = $this->middleware->authorize('PaidModule');

        $this->assertFalse($result);
        $this->assertEquals(
            'This module requires a paid subscription',
            $this->middleware->getDenialReason()
        );
    }

    public function testAuthorizeSubscriberModuleAllowsCommissioner(): void
    {
        $this->setupModuleQuery('PaidModule', 1, ModuleMiddleware::ACCESS_SUBSCRIBERS);
        $this->mockAuthBridge->method('hasRole')->with(User::ROLE_COMMISSIONER)->willReturn(true);

        $result = $this->middleware->authorize('PaidModule');

        $this->assertTrue($result);
    }

    public function testGetModuleAccessLevel(): void
    {
        $this->setupModuleQuery('TestModule', 1, ModuleMiddleware::ACCESS_USERS);

        $level = $this->middleware->getModuleAccessLevel('TestModule');

        $this->assertEquals(ModuleMiddleware::ACCESS_USERS, $level);
    }

    public function testIsModuleActive(): void
    {
        $this->setupModuleQuery('ActiveModule', 1, ModuleMiddleware::ACCESS_PUBLIC);

        $this->assertTrue($this->middleware->isModuleActive('ActiveModule'));
    }

    public function testIsModuleInactive(): void
    {
        $this->setupModuleQuery('InactiveModule', 0, ModuleMiddleware::ACCESS_PUBLIC);

        $this->assertFalse($this->middleware->isModuleActive('InactiveModule'));
    }

    public function testClearCacheForcesNewQuery(): void
    {
        $moduleData = ['active' => 1, 'view' => ModuleMiddleware::ACCESS_PUBLIC];

        // First call
        $mockStmt1 = $this->createMock(\mysqli_stmt::class);
        $mockResult1 = $this->createMock(\mysqli_result::class);
        $mockResult1->method('fetch_assoc')->willReturn($moduleData);
        $mockStmt1->method('execute')->willReturn(true);
        $mockStmt1->method('get_result')->willReturn($mockResult1);
        $mockStmt1->method('bind_param')->willReturn(true);

        // Second call after cache clear
        $mockStmt2 = $this->createMock(\mysqli_stmt::class);
        $mockResult2 = $this->createMock(\mysqli_result::class);
        $mockResult2->method('fetch_assoc')->willReturn(['active' => 0, 'view' => 0]);
        $mockStmt2->method('execute')->willReturn(true);
        $mockStmt2->method('get_result')->willReturn($mockResult2);
        $mockStmt2->method('bind_param')->willReturn(true);

        $this->mockDb->method('prepare')
            ->willReturnOnConsecutiveCalls($mockStmt1, $mockStmt2);

        // First check - module is active
        $this->assertTrue($this->middleware->isModuleActive('TestModule'));

        // Clear cache and check again - should get new data
        $this->middleware->clearCache();
        $this->assertFalse($this->middleware->isModuleActive('TestModule'));
    }

    /**
     * Helper to setup module query mock
     */
    private function setupModuleQuery(string $moduleName, int $active, int $view): void
    {
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockResult = $this->createMock(\mysqli_result::class);

        $mockResult->method('fetch_assoc')->willReturn([
            'active' => $active,
            'view' => $view,
        ]);

        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);
        $mockStmt->method('bind_param')->willReturn(true);

        $this->mockDb->method('prepare')
            ->willReturn($mockStmt);
    }

    /**
     * Helper to setup group query mock
     */
    private function setupGroupQuery(int $modGroup): void
    {
        // The group query uses a different prepare call
        // For simplicity, we're returning mod_group = 0 (no restriction)
    }
}
