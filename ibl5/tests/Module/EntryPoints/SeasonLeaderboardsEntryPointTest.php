<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * SeasonLeaderboards is now a redirect stub using ModuleRedirect::send();
 * the board lives at Leaderboards?tab=season. The redirect header itself
 * is asserted by curl since CLI PHPUnit cannot read response headers.
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
