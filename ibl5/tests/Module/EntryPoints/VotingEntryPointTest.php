<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Voting/index.php delegates to main($user) which calls loginbox() for
 * unauthenticated users (loginbox() calls die()). Unauthenticated path
 * is covered by E2E — same pattern as FreeAgency.
 *
 * Requires separate processes because is_user() has a static cache.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class VotingEntryPointTest extends ModuleEntryPointTestCase
{
    public function testDefaultRendersVotingPageForGm(): void
    {
        $this->authenticateAs('testgm');
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

        $output = $this->runModule('Voting', [], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
        ]));

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testAdminGmSeesVotingResultsExpander(): void
    {
        $this->authenticateAs('testadmin');
        $this->arrangeSeason();

        $adminStub = self::createStub(\Auth\Contracts\AuthServiceInterface::class);
        $adminStub->method('isAuthenticated')->willReturn(true);
        $adminStub->method('isAdmin')->willReturn(true);
        $adminStub->method('getCookieArray')->willReturn(['testadmin', 'testadmin', '']);

        $output = $this->runModule('Voting', [], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
            'authService' => $adminStub,
        ]));

        $this->assertStringContainsString('ShowAndHideResults', $output);
        $this->assertStringContainsString('Voting Results', $output);
    }

    public function testRegularGmDoesNotSeeVotingResultsExpander(): void
    {
        // authenticateAs() pins isAdmin => false.
        $this->authenticateAs('testgm');
        $this->arrangeSeason();

        $output = $this->runModule('Voting', [], [], array_merge($this->dbGlobals(), [
            'user' => $GLOBALS['user'],
        ]));

        $this->assertNotEmpty($output);
        $this->assertStringNotContainsString('ShowAndHideResults', $output);
        $this->assertStringNotContainsString('Voting Results', $output);
    }

    private function arrangeSeason(): void
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
    }
}
