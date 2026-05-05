<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class AwardHistoryEntryPointTest extends ModuleEntryPointTestCase
{
    public function testEmptyPostRendersFormWithNoResults(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('AwardHistory');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Player Awards', $output);
        $this->assertQueryNotExecuted('ibl_awards');
    }

    public function testPostWithFilterRunsSearch(): void
    {
        $this->mockDb->setMockData([
            ['year' => 2024, 'award' => 'MVP', 'name' => 'Test Player', 'table_id' => 1, 'pid' => 1],
        ]);
        $output = $this->runModule('AwardHistory', [], ['aw_name' => 'Test']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_awards');
    }

    public function testPostWithEmptyFilterRunsSearchWithNoResults(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('AwardHistory', [], ['aw_name' => '']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_awards');
    }
}
