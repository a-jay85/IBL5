<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

/**
 * Entry-point tests for modules/CapWhatIf/index.php.
 *
 * The team is resolved server-side from the authenticated identity; these cover
 * the valid render, the owner-scoping guarantee (a request-supplied teamid is
 * never read), the no-team notice, and garbage-input resilience.
 */
class CapWhatIfEntryPointTest extends ModuleEntryPointTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDb->onQuery('ibl_settings', [
            ['setting_key' => 'Current Season Phase', 'value' => 'Regular Season'],
            ['setting_key' => 'Current Season Ending Year', 'value' => '2026'],
        ]);
        $this->mockDb->onQuery('ibl_sim_dates', []);
    }

    /**
     * Resolve the authenticated GM to the Metros (teamid 1) with a two-player
     * roster.
     */
    private function seedMetrosOwner(): void
    {
        // getTeamnameFromUsername (gm_username lookup) → Metros.
        $this->mockDb->onQuery('gm_username', [['team_name' => 'Metros']]);
        // getTidFromTeamname (teamid lookup) → 1.
        $this->mockDb->onQuery('teamid FROM ibl_team_info', [['teamid' => 1]]);
        // getRosterUnderContractOrderedByName → two active contracts.
        $this->mockDb->onQuery('FROM ibl_plr p', [
            ['pid' => 1, 'name' => 'Player One', 'cy' => 1, 'cyt' => 3, 'salary_yr1' => 800, 'salary_yr2' => 880],
            ['pid' => 2, 'name' => 'Player Two', 'cy' => 1, 'cyt' => 2, 'salary_yr1' => 600, 'salary_yr2' => 660],
        ]);
    }

    public function testValidScenarioRendersResultTableForOwner(): void
    {
        $this->authenticateAs('metrosgm');
        $this->seedMetrosOwner();

        $output = $this->runModule('CapWhatIf', ['waive' => '1', 'years' => '3', 'salary' => '1000']);

        $this->assertStringContainsString('Cap Calculator', $output);
        $this->assertStringContainsString('<table', $output);
        $this->assertStringContainsString('Player One', $output);
    }

    public function testInjectedTeamidDoesNotChangeResolvedTeam(): void
    {
        $this->authenticateAs('metrosgm');
        $this->seedMetrosOwner();

        $this->runModule('CapWhatIf', ['teamid' => '2', 'years' => '1', 'salary' => '500']);

        // The roster query must bind the OWNER's teamid (1), never the injected 2.
        $rosterQueries = array_filter(
            $this->mockDb->getExecutedQueries(),
            static fn (string $q): bool => stripos($q, 'FROM ibl_plr p') !== false
        );
        $this->assertNotEmpty($rosterQueries);
        foreach ($rosterQueries as $q) {
            $this->assertStringContainsString('p.teamid = 1', $q);
            $this->assertStringNotContainsString('p.teamid = 2', $q);
        }
    }

    public function testNoTeamUserSeesNoticeNotCapTable(): void
    {
        $this->authenticateAs('freeagent');
        $this->mockDb->onQuery('gm_username', [['team_name' => 'Free Agents']]);

        $output = $this->runModule('CapWhatIf', []);

        $this->assertStringContainsString('must be a team GM', $output);
        $this->assertStringNotContainsString('<table', $output);
    }

    public function testGarbageInputDoesNotCrash(): void
    {
        $this->authenticateAs('metrosgm');
        $this->seedMetrosOwner();

        $output = $this->runModule('CapWhatIf', ['years' => '99', 'salary' => '-5', 'waive' => 'abc']);

        $this->assertStringContainsString('Cap Calculator', $output);
        $this->assertStringNotContainsString('Fatal error', $output);
        $this->assertStringNotContainsString('Warning', $output);
    }
}
