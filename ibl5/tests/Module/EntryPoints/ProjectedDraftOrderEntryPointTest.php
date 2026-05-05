<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class ProjectedDraftOrderEntryPointTest extends ModuleEntryPointTestCase
{
    public function testRendersProjectedOrderWhenNotFinalized(): void
    {
        $this->mockDb->onQuery('Draft Order Finalized', [['value' => 'No']]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('ProjectedDraftOrder');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Projected Draft Order', $output);
    }

    public function testRendersFinalizedOrderWhenFinalized(): void
    {
        $this->mockDb->onQuery('Draft Order Finalized', [['value' => 'Yes']]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('ProjectedDraftOrder');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Draft Order', $output);
        $this->assertStringNotContainsString('Projected Draft Order', $output);
    }
}
