<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * PlayerExportGuide is now a redirect stub; the guide renders below the key
 * card on the ApiKeys page. The 302 header itself is asserted by curl
 * (Verification Matrix row 18) since CLI PHPUnit cannot read response headers.
 */
class PlayerExportGuideEntryPointTest extends ModuleEntryPointTestCase
{
    public function testStubEmitsNoBody(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('PlayerExportGuide', [], [], $this->dbGlobals());

        $this->assertSame('', $output);
    }
}
