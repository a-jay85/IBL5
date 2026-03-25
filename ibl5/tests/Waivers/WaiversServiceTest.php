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
        $commonRepoStub = $this->createStub(\Services\CommonMysqliRepository::class);
        $processorStub = $this->createStub(\Waivers\Contracts\WaiversProcessorInterface::class);
        $viewStub = $this->createStub(\Waivers\Contracts\WaiversViewInterface::class);
        $teamQueryRepoStub = $this->createStub(\Team\Contracts\TeamQueryRepositoryInterface::class);
        $dbStub = $this->createStub(\mysqli::class);

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
