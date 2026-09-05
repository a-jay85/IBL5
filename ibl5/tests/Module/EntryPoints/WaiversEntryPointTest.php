<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;

/**
 * Waivers/index.php defines a global function waivers() that cannot be
 * redeclared, so each test runs in a separate process.
 *
 * Unauthenticated paths are not tested here because loginbox() calls die(),
 * which terminates the test process. Covered by E2E flows instead.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class WaiversEntryPointTest extends ModuleEntryPointTestCase
{
    private function seedSeasonMocks(): void
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
    }

    public function testRendersWaiversPageForAuthenticatedGm(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->onQuery('auth_users', [
            ['user_id' => 1, 'username' => 'testgm', 'user_email' => 'test@test.com'],
        ]);
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Waivers', [], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
    }

    public function testDisplayParameterSelectsRequestedTab(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->onQuery('auth_users', [
            ['user_id' => 1, 'username' => 'testgm', 'user_email' => 'test@test.com'],
        ]);
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Waivers', ['display' => 'total_s'], [], ['user' => $GLOBALS['user']]);

        $this->assertStringContainsString('class="ibl-tab ibl-tab--active" data-display="total_s"', $output);
    }

    public function testAbsentDisplayParameterDefaultsToRatingsTab(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->onQuery('auth_users', [
            ['user_id' => 1, 'username' => 'testgm', 'user_email' => 'test@test.com'],
        ]);
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Waivers', [], [], ['user' => $GLOBALS['user']]);

        $this->assertStringContainsString('class="ibl-tab ibl-tab--active" data-display="ratings"', $output);
    }

    public function testEmptyDisplayParameterSelectsNoActiveTab(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->onQuery('auth_users', [
            ['user_id' => 1, 'username' => 'testgm', 'user_email' => 'test@test.com'],
        ]);
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('Waivers', ['display' => ''], [], ['user' => $GLOBALS['user']]);

        $this->assertStringNotContainsString('ibl-tab--active', $output);
    }

    public function testDisplayParameterReachesRenderedOutputThroughHttpRequest(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->onQuery('auth_users', [
            ['user_id' => 1, 'username' => 'testgm', 'user_email' => 'test@test.com'],
        ]);
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $first = $this->runModule('Waivers', ['display' => 'ratings'], [], ['user' => $GLOBALS['user']]);

        $this->seedSeasonMocks();
        $this->mockDb->onQuery('auth_users', [
            ['user_id' => 1, 'username' => 'testgm', 'user_email' => 'test@test.com'],
        ]);
        $this->mockDb->setMockTeamData([self::fullTeamData()]);
        $this->mockDb->setMockData([]);

        $second = $this->runModule('Waivers', ['display' => 'total_s'], [], ['user' => $GLOBALS['user']]);

        $this->assertNotEmpty($first);
        $this->assertNotEmpty($second);
        $this->assertNotSame($first, $second, 'display parameter must reach rendered output through the injected request');
    }
}
