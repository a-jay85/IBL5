<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * CareerLeaderboards is now a redirect stub; the board lives at
 * Leaderboards?tab=career. The 302 header itself is asserted by curl
 * (Verification Matrix row V20) since CLI PHPUnit cannot read response headers.
 */
class CareerLeaderboardsEntryPointTest extends ModuleEntryPointTestCase
{
    public function testStubEmitsNoBody(): void
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->onQuery('cache', []);

        $output = $this->runModule('CareerLeaderboards', [], [
            'submitted' => '1',
            'boards_type' => 'Regular Season Totals',
        ], $this->dbGlobals());

        $this->assertSame('', $output);
        $this->assertQueryNotExecuted('ibl_hist');
    }
}
