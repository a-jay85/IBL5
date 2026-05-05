<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use Tests\WideUnit\Mocks\TestDataFactory;

/**
 * FreeAgency/index.php delegates to FreeAgencyController::handleRequest()
 * which uses is_user() (static-cached). Separate processes prevent the
 * static cache from leaking between tests.
 *
 * Unauthenticated paths are not tested here because loginbox() calls die(),
 * which terminates the test process. Covered by E2E flows instead.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class FreeAgencyEntryPointTest extends ModuleEntryPointTestCase
{
    private function seedSeasonMocks(): void
    {
        $this->mockDb->onQuery('ibl_settings', [
            ['name' => 'Current Season Phase', 'value' => 'Free Agency'],
            ['name' => 'Current Season Ending Year', 'value' => '2026'],
            ['name' => 'Allow Trades', 'value' => 'Yes'],
            ['name' => 'Allow Waiver Moves', 'value' => 'Yes'],
        ]);
        $this->mockDb->onQuery('ibl_sim_dates', [
            ['sim' => 1, 'start_date' => '2025-11-01', 'end_date' => '2025-11-07'],
        ]);
    }

    public function testDefaultActionRendersDisplayPage(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->onQuery('FROM ibl_plr', []);
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('FreeAgency', [], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
            'pa' => '',
            'pid' => '0',
        ]));

        $this->assertNotEmpty($output);
    }

    public function testNegotiateActionRendersNegotiationPage(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->onQuery('FROM ibl_plr', [TestDataFactory::createPlayer([
            'pid' => 1,
            'teamname' => 'Test Team',
            'color1' => 'FF0000',
            'color2' => '000000',
            'nickname' => '',
            'loyalty' => 50,
            'playing_time' => 50,
            'winner' => 50,
            'tradition' => 50,
            'security' => 50,
        ])]);
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('FreeAgency', [], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
            'pa' => 'negotiate',
            'pid' => '1',
        ]));

        $this->assertNotEmpty($output);
    }

    public function testUnknownActionFallsToDisplay(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->onQuery('FROM ibl_plr', []);
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('FreeAgency', [], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
            'pa' => 'bogus',
            'pid' => '0',
        ]));

        $this->assertNotEmpty($output);
    }
}
