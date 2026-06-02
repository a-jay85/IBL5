<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Waivers\Contracts\WaiversProcessorInterface;
use Waivers\Contracts\WaiversServiceInterface;
use Waivers\Contracts\WaiversViewInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Repositories\Contracts\SalaryCapRepositoryInterface;
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

        $this->controller = new WaiversController(
            $serviceStub,
            $processorStub,
            $viewStub,
            $teamIdentityRepoStub,
            $salaryCapRepoStub,
            $nukeCompatStub,
            $dbStub
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
}
