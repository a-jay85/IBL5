<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class DraftPickLocatorEntryPointTest extends ModuleEntryPointTestCase
{
    public function testRendersDraftPickLocator(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('DraftPickLocator');

        $this->assertNotEmpty($output);
    }
}
