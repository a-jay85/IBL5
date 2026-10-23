<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * AllStarAppearances hosts the full all-star appearances list. The old
 * RecordHolders?op=allstar URL redirects here.
 */
class AllStarAppearancesEntryPointTest extends ModuleEntryPointTestCase
{
    public function testRendersFullAppearancesList(): void
    {
        $this->mockDb->setMockData([
            ['name' => 'Test Player', 'pid' => 1, 'appearances' => 3],
        ]);

        $output = $this->runModule('AllStarAppearances', [], [], $this->dbGlobals());

        $this->assertStringContainsString('<h1 class="ibl-title">All-Star Appearances</h1>', $output);
        $this->assertStringContainsString('Test Player', $output);
        $this->assertStringNotContainsString('Most All-Star Appearances', $output);
    }
}
