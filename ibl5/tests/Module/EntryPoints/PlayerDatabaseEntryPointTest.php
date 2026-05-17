<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use Tests\WideUnit\Mocks\TestDataFactory;

class PlayerDatabaseEntryPointTest extends ModuleEntryPointTestCase
{
    public function testEmptyPostRendersDefaultSearchForm(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('PlayerDatabase');

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Player Search', $output);
    }

    public function testPostWithFilterRunsSearch(): void
    {
        $this->mockDb->setMockData([
            TestDataFactory::createPlayer(['pid' => 1, 'name' => 'Test Player', 'pos' => 'G', 'teamid' => 1]),
        ]);
        $output = $this->runModule('PlayerDatabase', [], ['search_name' => 'Test']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_plr');
    }

    public function testPostWithEmptyFiltersRunsSearchWithNoResults(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('PlayerDatabase', [], ['search_name' => '']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_plr');
    }
}
