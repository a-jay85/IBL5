<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class StandingsEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);
        $this->mockDb->onQuery('ibl_sim_dates', []);
        $this->mockDb->onQuery('ibl_schedule', []);
        $this->mockDb->setMockData([]);
    }

    public function testRendersStandingsWithEndingYear(): void
    {
        $output = $this->runModule('Standings');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_standings');
    }

    public function testRendersZeroTeamsGracefully(): void
    {
        $output = $this->runModule('Standings');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_power');
    }
}
