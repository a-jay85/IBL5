<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class FranchiseHistoryEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);
        $this->mockDb->onQuery('ibl_sim_dates', []);
    }

    public function testRendersFranchiseHistory(): void
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->onQuery('vw_franchise_summary', []);
        $this->mockDb->onQuery('ibl_team_win_loss', []);
        $this->mockDb->onQuery('vw_playoff_series_results', []);
        $this->mockDb->onQuery('ibl_heat_win_loss', []);
        $output = $this->runModule('FranchiseHistory');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_team_info');
    }
}
