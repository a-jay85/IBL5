<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * SeasonLeaderboards is now a redirect stub; the board lives at
 * Leaderboards?tab=season. The 302 header itself is asserted by curl
 * (Verification Matrix row V20) since CLI PHPUnit cannot read response headers.
 */
class SeasonLeaderboardsEntryPointTest extends ModuleEntryPointTestCase
{
    public function testStubEmitsNoBody(): void
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->onQuery('cache', []);

        $output = $this->runModule('SeasonLeaderboards', [], ['year' => '2024'], $this->dbGlobals());

        $this->assertSame('', $output);
        $this->assertQueryNotExecuted('ibl_hist');
    }
}
