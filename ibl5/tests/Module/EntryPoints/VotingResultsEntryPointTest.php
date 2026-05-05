<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class VotingResultsEntryPointTest extends ModuleEntryPointTestCase
{
    public function testRendersVotingResults(): void
    {
        $this->mockDb->onQuery('ibl_settings', [
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['name' => 'Current Season Ending Year', 'value' => '2026'],
            ['name' => 'Allow Trades', 'value' => 'Yes'],
            ['name' => 'Allow Waiver Moves', 'value' => 'Yes'],
        ]);
        $this->mockDb->onQuery('ibl_sim_dates', [
            ['sim' => 1, 'start_date' => '2025-11-01', 'end_date' => '2025-11-07'],
        ]);
        $this->mockDb->setMockData([['category' => 'MVP', 'player' => 'Test', 'votes' => 10]]);

        $output = $this->runModule('VotingResults', [], [], $this->dbGlobals());

        $this->assertNotEmpty($output);
    }

    public function testHandlesEmptyResults(): void
    {
        $this->mockDb->onQuery('ibl_settings', [
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['name' => 'Current Season Ending Year', 'value' => '2026'],
            ['name' => 'Allow Trades', 'value' => 'Yes'],
            ['name' => 'Allow Waiver Moves', 'value' => 'Yes'],
        ]);
        $this->mockDb->onQuery('ibl_sim_dates', [
            ['sim' => 1, 'start_date' => '2025-11-01', 'end_date' => '2025-11-07'],
        ]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('VotingResults', [], [], $this->dbGlobals());

        $this->assertNotEmpty($output);
    }
}
