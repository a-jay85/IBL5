<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class CapSpaceEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->onQuery('ibl_settings', [['value' => 'Regular Season']]);
        $this->mockDb->onQuery('ibl_sim_dates', []);
    }

    public function testRendersCapData(): void
    {
        $this->mockDb->setMockData([]);
        $this->mockDb->onQuery('ibl_team_info', []);
        $this->mockDb->onQuery('ibl_plr', []);
        $output = $this->runModule('CapSpace');

        $this->assertNotEmpty($output);
    }
}
