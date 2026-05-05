<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class AllStarAppearancesEntryPointTest extends ModuleEntryPointTestCase
{
    public function testRendersAppearancesList(): void
    {
        $this->mockDb->setMockData([
            ['name' => 'Test Player', 'pid' => 1, 'appearances' => 5],
        ]);
        $output = $this->runModule('AllStarAppearances');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_awards');
    }

    public function testRendersWithNoAppearances(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('AllStarAppearances');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_awards');
    }
}
