<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

class ActivityTrackerEntryPointTest extends ModuleEntryPointTestCase
{
    public function testRendersTeamActivity(): void
    {
        $this->mockDb->setMockData([]);

        $output = $this->runModule('ActivityTracker');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_team_info');
    }

}
