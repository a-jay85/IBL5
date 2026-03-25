<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Waivers\Contracts\WaiversProcessorInterface;
use Waivers\Contracts\WaiversServiceInterface;
use Waivers\Contracts\WaiversViewInterface;
use Waivers\WaiversController;

class WaiversControllerTest extends TestCase
{
    private WaiversController $controller;

    protected function setUp(): void
    {
        $serviceStub = $this->createStub(WaiversServiceInterface::class);
        $processorStub = $this->createStub(WaiversProcessorInterface::class);
        $viewStub = $this->createStub(WaiversViewInterface::class);
        $commonRepoStub = $this->createStub(\Services\CommonMysqliRepository::class);
        $nukeCompatStub = $this->createStub(\Utilities\NukeCompat::class);
        $dbStub = $this->createStub(\mysqli::class);

        $this->controller = new WaiversController(
            $serviceStub,
            $processorStub,
            $viewStub,
            $commonRepoStub,
            $nukeCompatStub,
            $dbStub
        );
    }

    public function testControllerCanBeInstantiated(): void
    {
        $this->assertInstanceOf(WaiversController::class, $this->controller);
    }

    public function testControllerImplementsCorrectInterface(): void
    {
        $this->assertInstanceOf(
            \Waivers\Contracts\WaiversControllerInterface::class,
            $this->controller
        );
    }

    public function testWaiverPoolMovesCategoryIdIsOne(): void
    {
        $this->assertSame(1, WaiversController::WAIVER_POOL_MOVES_CATEGORY_ID);
    }
}
