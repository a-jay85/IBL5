<?php

declare(strict_types=1);

namespace Tests\SavedDepthChart;

use SavedDepthChart\Contracts\SavedDepthChartRepositoryInterface;
use SavedDepthChart\SavedDepthChartRepository;
use Tests\Integration\IntegrationTestCase;

/**
 * @covers \SavedDepthChart\SavedDepthChartRepository
 */
class SavedDepthChartRepositoryTest extends IntegrationTestCase
{
    public function testImplementsRepositoryInterface(): void
    {
        $repository = new SavedDepthChartRepository($this->mockDb);

        $this->assertInstanceOf(SavedDepthChartRepositoryInterface::class, $repository);
    }
}
