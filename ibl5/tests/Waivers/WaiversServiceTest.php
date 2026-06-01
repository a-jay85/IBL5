<?php

declare(strict_types=1);

namespace Tests\Waivers;

use PHPUnit\Framework\TestCase;
use Waivers\Contracts\WaiversServiceInterface;
use Waivers\WaiversService;

class WaiversServiceTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $commonRepoStub = self::createStub(\Repositories\Contracts\TeamIdentityRepositoryInterface::class);
        $processorStub = self::createStub(\Waivers\Contracts\WaiversProcessorInterface::class);
        $viewStub = self::createStub(\Waivers\Contracts\WaiversViewInterface::class);
        $teamQueryRepoStub = self::createStub(\Team\Contracts\TeamQueryRepositoryInterface::class);
        $dbStub = self::createStub(\mysqli::class);

        $service = new WaiversService(
            $commonRepoStub,
            $processorStub,
            $viewStub,
            $teamQueryRepoStub,
            $dbStub
        );

        self::assertInstanceOf(WaiversServiceInterface::class, $service);
    }
}
