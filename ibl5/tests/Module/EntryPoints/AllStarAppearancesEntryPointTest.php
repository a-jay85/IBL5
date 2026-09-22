<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * AllStarAppearances is now a redirect stub; the list lives at
 * RecordHolders?op=allstar. The 302 header itself is asserted by curl
 * (Verification Matrix row 18) since CLI PHPUnit cannot read response headers.
 */
class AllStarAppearancesEntryPointTest extends ModuleEntryPointTestCase
{
    public function testStubEmitsNoBody(): void
    {
        $this->mockDb->setMockData([
            ['name' => 'Test Player', 'pid' => 1, 'appearances' => 5],
        ]);

        $output = $this->runModule('AllStarAppearances', [], [], $this->dbGlobals());

        $this->assertSame('', $output);
        $this->assertQueryNotExecuted('ibl_awards');
    }
}
