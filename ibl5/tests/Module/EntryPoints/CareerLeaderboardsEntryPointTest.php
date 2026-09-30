<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * CareerLeaderboards is now a redirect stub using ModuleRedirect::send();
 * the board lives at Leaderboards?tab=career. The redirect header itself
 * is asserted by curl since CLI PHPUnit cannot read response headers.
 */
class CareerLeaderboardsEntryPointTest extends ModuleEntryPointTestCase
{
    public function testStubEmitsNoBody(): void
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->onQuery('cache', []);

        $output = $this->runModule('CareerLeaderboards', [], [
            'submitted' => '1',
            'phase' => 'regular',
        ], $this->dbGlobals());

        $this->assertSame('', $output);
        $this->assertQueryNotExecuted('ibl_hist');
    }
}
