<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class FreeAgencyPreviewEntryPointTest extends ModuleEntryPointTestCase
{
    private function seedSeasonMocks(string $endingYear = '2026'): void
    {
        $this->mockDb->onQuery('ibl_settings', [
            ['name' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['name' => 'Current Season Ending Year', 'value' => $endingYear],
            ['name' => 'Allow Trades', 'value' => 'Yes'],
            ['name' => 'Allow Waiver Moves', 'value' => 'Yes'],
        ]);
        $this->mockDb->onQuery('ibl_sim_dates', [
            ['sim' => 1, 'start_date' => '2025-11-01', 'end_date' => '2025-11-07'],
        ]);
    }

    public function testRendersUpcomingFreeAgents(): void
    {
        $this->seedSeasonMocks();
        $this->mockDb->setMockData([]);

        $output = $this->runModule('FreeAgencyPreview');

        $this->assertNotEmpty($output);
    }

    public function testHandlesEmptyFreeAgentsList(): void
    {
        $this->seedSeasonMocks();
        $this->mockDb->setMockData([]);

        $output = $this->runModule('FreeAgencyPreview');

        $this->assertNotEmpty($output);
    }
}
