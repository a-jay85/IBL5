<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * League_Starters is a legacy redirect stub for the LeagueStarters module.
 * The 302 header itself is asserted by the curl rows (7 and 8) of the
 * Verification Matrix since CLI PHPUnit cannot read response headers.
 */
class LeagueStartersLegacyStubEntryPointTest extends ModuleEntryPointTestCase
{
    public function testLeagueStartersLegacyStubEmitsNoBody(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('League_Starters', [], [], $this->dbGlobals());

        $this->assertSame('', $output);
    }
}
