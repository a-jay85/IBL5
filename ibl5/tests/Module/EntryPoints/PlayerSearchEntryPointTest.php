<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use Tests\WideUnit\Mocks\TestDataFactory;

class PlayerSearchEntryPointTest extends ModuleEntryPointTestCase
{
    public function testEmptyPostRendersDefaultSearchForm(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('PlayerSearch');

        $this->assertNotSame('', $output);
        $this->assertStringContainsString('Player Search', $output);
    }

    public function testPostWithFilterRunsSearch(): void
    {
        $this->mockDb->setMockData([
            TestDataFactory::createPlayer(['pid' => 1, 'name' => 'Test Player', 'pos' => 'G', 'teamid' => 1]),
        ]);
        $output = $this->runModule('PlayerSearch', [], ['search_name' => 'Test']);

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('ibl_plr');
    }

    public function testPostWithEmptyFiltersRunsSearchWithNoResults(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('PlayerSearch', [], ['search_name' => '']);

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('ibl_plr');
    }
}
