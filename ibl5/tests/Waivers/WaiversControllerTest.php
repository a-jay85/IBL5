<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Waivers\Contracts\WaiversProcessorInterface;
use Waivers\Contracts\WaiversServiceInterface;
use Waivers\Contracts\WaiversViewInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Repositories\Contracts\SalaryCapRepositoryInterface;
use Auth\Contracts\AuthServiceInterface;
use Waivers\WaiversController;

class WaiversControllerTest extends TestCase
{
    private WaiversController $controller;

    protected function setUp(): void
    {
        $serviceStub = self::createStub(WaiversServiceInterface::class);
        $processorStub = self::createStub(WaiversProcessorInterface::class);
        $viewStub = self::createStub(WaiversViewInterface::class);
        $teamIdentityRepoStub = self::createStub(TeamIdentityRepositoryInterface::class);
        $salaryCapRepoStub = self::createStub(SalaryCapRepositoryInterface::class);
        $nukeCompatStub = self::createStub(\Utilities\NukeCompat::class);
        $dbStub = self::createStub(\mysqli::class);
        $authServiceStub = self::createStub(AuthServiceInterface::class);

        $this->controller = new WaiversController(
            $serviceStub,
            $processorStub,
            $viewStub,
            $teamIdentityRepoStub,
            $salaryCapRepoStub,
            $nukeCompatStub,
            $dbStub,
            $authServiceStub,
        );
    }

    public function testControllerConstructsWithAllDependencies(): void
    {
        // Verify the public API is structurally present
        $reflection = new \ReflectionClass($this->controller);
        $this->assertTrue($reflection->hasMethod('handleWaiverRequest'));
    }

    public function testWaiverPoolMovesCategoryIdIsPositive(): void
    {
        $this->assertGreaterThan(0, WaiversController::WAIVER_POOL_MOVES_CATEGORY_ID);
    }

    public function testEmptyUsernameFromNullAuthServiceCallsLoginBox(): void
    {
        $nukeCompatMock = $this->createMock(\Utilities\NukeCompat::class);
        $nukeCompatMock->expects($this->once())->method('loginBox');

        $teamIdentityRepoStub = self::createStub(TeamIdentityRepositoryInterface::class);
        $teamIdentityRepoStub->method('getUserByUsername')->willReturn(null);

        $controller = new WaiversController(
            self::createStub(WaiversServiceInterface::class),
            self::createStub(WaiversProcessorInterface::class),
            self::createStub(WaiversViewInterface::class),
            $teamIdentityRepoStub,
            self::createStub(SalaryCapRepositoryInterface::class),
            $nukeCompatMock,
            self::createStub(\mysqli::class),
            self::createStub(AuthServiceInterface::class), // getUsername() returns null by default
        );

        $controller->executeWaiverOperation('', 'waivePlayer');
    }

    public function testAuthenticatedUsernameDoesNotCallLoginBoxImmediately(): void
    {
        $nukeCompatMock = $this->createMock(\Utilities\NukeCompat::class);
        $nukeCompatMock->expects($this->never())->method('loginBox');

        $teamIdentityRepoStub = self::createStub(TeamIdentityRepositoryInterface::class);
        $teamIdentityRepoStub->method('getUserByUsername')->willReturn(['username' => 'gm', 'tid' => 1]);

        $authServiceStub = self::createStub(AuthServiceInterface::class);
        $authServiceStub->method('getUsername')->willReturn('gm');

        $controller = new WaiversController(
            self::createStub(WaiversServiceInterface::class),
            self::createStub(WaiversProcessorInterface::class),
            self::createStub(WaiversViewInterface::class),
            $teamIdentityRepoStub,
            self::createStub(SalaryCapRepositoryInterface::class),
            $nukeCompatMock,
            self::createStub(\mysqli::class),
            $authServiceStub,
        );

        // executeWaiverOperation continues past loginBox when userInfo is non-null;
        // suppress output from PageLayout calls
        ob_start();
        try {
            $controller->executeWaiverOperation('gm', 'view');
        } catch (\Throwable) {
            // Expected — downstream stubs may throw; what we're asserting is loginBox NEVER called
        }
        ob_end_clean();
    }
}
