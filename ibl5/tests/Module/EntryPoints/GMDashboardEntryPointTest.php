<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * GMDashboard/index.php uses is_user() with a static cache and loginbox() → die()
 * for unauthenticated access, so each test runs in a separate process.
 *
 * The dashboard wires six real read-only services against the MockDatabase; with
 * empty mock data every section renders its empty-state, so the page still renders.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class GMDashboardEntryPointTest extends ModuleEntryPointTestCase
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

    /**
     * The owner team row, enriched with the contract/salary columns that
     * player+team JOIN queries (CapSpace cap calc, FreeAgencyPreview) read.
     * The MockDatabase routes every query containing `ibl_team_info` to this
     * row, so it must carry every key any of the six services touches — a full
     * contract span (cy=1, cyt=6, salary_yr1..6) keeps both services warning-free.
     *
     * @return array<string, mixed>
     */
    private function ownerTeamRow(): array
    {
        return self::fullTeamData([
            'cy' => 1,
            'cyt' => 6,
            'salary_yr1' => 100,
            'salary_yr2' => 100,
            'salary_yr3' => 100,
            'salary_yr4' => 100,
            'salary_yr5' => 100,
            'salary_yr6' => 100,
        ]);
    }

    public function testRendersDashboardForAuthenticatedOwner(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->setMockTeamData([$this->ownerTeamRow()]);
        $this->mockDb->setMockData([]);

        $output = $this->runModule('GMDashboard', [], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Test Team Dashboard', $output);
        $this->assertStringContainsString('Pending Trades', $output);
    }

    public function testIgnoresTeamidRequestParam(): void
    {
        $this->authenticateAs('testgm');
        $this->seedSeasonMocks();
        $this->mockDb->setMockTeamData([$this->ownerTeamRow()]);
        $this->mockDb->setMockData([]);

        // A ?teamid=2 injection must be ignored — output still reflects the
        // authenticated owner (Test Team), never team 2.
        $output = $this->runModule('GMDashboard', ['teamid' => '2'], [], [
            'user' => $GLOBALS['user'],
        ]);

        $this->assertStringContainsString('Test Team Dashboard', $output);
    }
}
