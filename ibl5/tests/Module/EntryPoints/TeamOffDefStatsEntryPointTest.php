<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class TeamOffDefStatsEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->onQuery('ibl_settings', [['setting_value' => 'Regular Season']]);
        $this->mockDb->onQuery('ibl_sim_dates', []);
    }

    public function testRendersDefaultTeamStats(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('TeamOffDefStats');

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('ibl_box_scores');
    }

    public function testRendersTeamStatsInPlayoffs(): void
    {
        $this->mockDb->onQuery('ibl_settings', [['setting_value' => 'Playoffs']]);
        $this->mockDb->setMockData([]);
        $output = $this->runModule('TeamOffDefStats');

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('ibl_box_scores');
    }

    public function testRendersTeamStatsInPreseason(): void
    {
        $this->mockDb->onQuery('ibl_settings', [['setting_value' => 'Preseason']]);
        $this->mockDb->setMockData([]);
        $output = $this->runModule('TeamOffDefStats');

        $this->assertNotSame('', $output);
        $this->assertQueryExecuted('ibl_box_scores');
    }
}
