<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use League\League;
use Team\TeamTableService;
use Season\Season;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Tests for TeamTableService
 *
 * Validates table rendering, starters extraction, and dropdown group logic
 */
class TeamTableServiceTest extends TestCase
{
    private MockDatabase $mockDb;
    private TeamTableService $service;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $repository = new \Team\TeamRepository($this->mockDb);
        $this->service = new TeamTableService($this->mockDb, $repository);
    }

    // ============================================
    // extractStartersData() TESTS
    // ============================================

    public function testExtractStartersDataReturnsCorrectStructure(): void
    {
        $roster = [
            ['pid' => 1, 'name' => 'John Doe', 'pg_depth' => 1, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 0, 'c_depth' => 0],
            ['pid' => 2, 'name' => 'Jane Smith', 'pg_depth' => 0, 'sg_depth' => 1, 'sf_depth' => 0, 'pf_depth' => 0, 'c_depth' => 0],
            ['pid' => 3, 'name' => 'Bob Johnson', 'pg_depth' => 0, 'sg_depth' => 0, 'sf_depth' => 1, 'pf_depth' => 0, 'c_depth' => 0],
            ['pid' => 4, 'name' => 'Mike Williams', 'pg_depth' => 0, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 1, 'c_depth' => 0],
            ['pid' => 5, 'name' => 'Tom Brown', 'pg_depth' => 0, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 0, 'c_depth' => 1],
        ];

        $starters = $this->service->extractStartersData($roster);

        $this->assertIsArray($starters);
        $this->assertArrayHasKey('PG', $starters);
        $this->assertArrayHasKey('SG', $starters);
        $this->assertArrayHasKey('SF', $starters);
        $this->assertArrayHasKey('PF', $starters);
        $this->assertArrayHasKey('C', $starters);

        $this->assertSame('John Doe', $starters['PG']['name']);
        $this->assertSame(1, $starters['PG']['pid']);
        $this->assertSame('Jane Smith', $starters['SG']['name']);
        $this->assertSame(2, $starters['SG']['pid']);
        $this->assertSame('Bob Johnson', $starters['SF']['name']);
        $this->assertSame(3, $starters['SF']['pid']);
        $this->assertSame('Mike Williams', $starters['PF']['name']);
        $this->assertSame(4, $starters['PF']['pid']);
        $this->assertSame('Tom Brown', $starters['C']['name']);
        $this->assertSame(5, $starters['C']['pid']);
    }

    public function testExtractStartersDataHandlesPartialData(): void
    {
        $roster = [
            ['pid' => 1, 'name' => 'John Doe', 'pg_depth' => 1, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 0, 'c_depth' => 0],
            ['pid' => 3, 'name' => 'Bob Johnson', 'pg_depth' => 0, 'sg_depth' => 0, 'sf_depth' => 1, 'pf_depth' => 0, 'c_depth' => 0],
        ];

        $starters = $this->service->extractStartersData($roster);

        $this->assertSame('John Doe', $starters['PG']['name']);
        $this->assertSame(1, $starters['PG']['pid']);
        $this->assertSame('Bob Johnson', $starters['SF']['name']);
        $this->assertSame(3, $starters['SF']['pid']);

        $this->assertNull($starters['SG']['name']);
        $this->assertNull($starters['SG']['pid']);
        $this->assertNull($starters['PF']['name']);
        $this->assertNull($starters['PF']['pid']);
        $this->assertNull($starters['C']['name']);
        $this->assertNull($starters['C']['pid']);
    }

    public function testExtractStartersDataIgnoresBackups(): void
    {
        $roster = [
            ['pid' => 1, 'name' => 'Starter PG', 'pg_depth' => 1, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 0, 'c_depth' => 0],
            ['pid' => 2, 'name' => 'Backup PG', 'pg_depth' => 2, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 0, 'c_depth' => 0],
        ];

        $starters = $this->service->extractStartersData($roster);

        $this->assertSame('Starter PG', $starters['PG']['name']);
        $this->assertSame(1, $starters['PG']['pid']);
    }

    public function testExtractStartersDataHandlesEmptyRoster(): void
    {
        $starters = $this->service->extractStartersData([]);

        foreach (['PG', 'SG', 'SF', 'PF', 'C'] as $position) {
            $this->assertNull($starters[$position]['name']);
            $this->assertNull($starters[$position]['pid']);
        }
    }

    public function testExtractStartersDataUsesStrictComparison(): void
    {
        // Depth values come from the database as strings; verify '1' (int cast) works
        $roster = [
            ['pid' => 10, 'name' => 'String Depth', 'pg_depth' => '1', 'sg_depth' => '0', 'sf_depth' => '0', 'pf_depth' => '0', 'c_depth' => '0'],
        ];

        $starters = $this->service->extractStartersData($roster);

        $this->assertSame('String Depth', $starters['PG']['name']);
    }

    // --- Merged from TeamServiceBuildDropdownGroupsTest ---

    /**
     * Create a TeamTableService instance pre-loaded with mock team data
     * for tests that exercise buildDropdownGroups() "vs. Team" group.
     */
    private function createServiceWithTeamData(): TeamTableService
    {
        $mockDb = new MockDatabase();
        $mockDb->setMockData([
            ['teamid' => 1, 'team_name' => 'Atlanta'],
            ['teamid' => 2, 'team_name' => 'Boston'],
        ]);
        $repository = new \Team\TeamRepository($mockDb);
        return new TeamTableService($mockDb, $repository);
    }

    private function createSeasonStub(string $phase): Season
    {
        $season = self::createStub(Season::class);
        $season->phase = $phase;
        return $season;
    }

    public function testReturnsExpectedGroupKeys(): void
    {
        $service = $this->createServiceWithTeamData();
        $season = $this->createSeasonStub('Regular Season');
        $groups = $service->buildDropdownGroups($season);

        $expectedGroups = [
            'Views',
            'Location',
            'Result',
            'Season Half',
            'By Month',
            'vs. Division',
            'vs. Conference',
            'vs. Team',
        ];

        foreach ($expectedGroups as $group) {
            $this->assertArrayHasKey($group, $groups, "Missing group: $group");
        }
    }

    public function testViewsGroupContainsStandardViews(): void
    {
        $service = $this->createServiceWithTeamData();
        $season = $this->createSeasonStub('Regular Season');
        $groups = $service->buildDropdownGroups($season);

        $views = $groups['Views'];
        $this->assertArrayHasKey('ratings', $views);
        $this->assertArrayHasKey('total_s', $views);
        $this->assertArrayHasKey('avg_s', $views);
        $this->assertArrayHasKey('per36mins', $views);
        $this->assertArrayHasKey('chunk', $views);
        $this->assertArrayHasKey('contracts', $views);
    }

    public function testPlayoffsAveragesExcludedDuringRegularSeason(): void
    {
        $service = $this->createServiceWithTeamData();
        $season = $this->createSeasonStub('Regular Season');
        $groups = $service->buildDropdownGroups($season);

        $this->assertArrayNotHasKey('playoffs', $groups['Views']);
    }

    #[DataProvider('playoffPhaseProvider')]
    public function testPlayoffsAveragesIncludedDuringPostSeason(string $phase): void
    {
        $service = $this->createServiceWithTeamData();
        $season = $this->createSeasonStub($phase);
        $groups = $service->buildDropdownGroups($season);

        $this->assertArrayHasKey('playoffs', $groups['Views']);
        $this->assertSame('Playoffs Averages', $groups['Views']['playoffs']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function playoffPhaseProvider(): array
    {
        return [
            'Playoffs phase' => ['Playoffs'],
            'Draft phase' => ['Draft'],
            'Free Agency phase' => ['Free Agency'],
        ];
    }

    public function testLocationGroupHasSplitPrefixes(): void
    {
        $service = $this->createServiceWithTeamData();
        $season = $this->createSeasonStub('Regular Season');
        $groups = $service->buildDropdownGroups($season);

        $this->assertSame(['split:home' => 'Home', 'split:road' => 'Road'], $groups['Location']);
    }

    public function testResultGroupHasSplitPrefixes(): void
    {
        $service = $this->createServiceWithTeamData();
        $season = $this->createSeasonStub('Regular Season');
        $groups = $service->buildDropdownGroups($season);

        $this->assertSame(['split:wins' => 'Wins', 'split:losses' => 'Losses'], $groups['Result']);
    }

    public function testByMonthGroupContainsAllMonths(): void
    {
        $service = $this->createServiceWithTeamData();
        $season = $this->createSeasonStub('Regular Season');
        $groups = $service->buildDropdownGroups($season);

        $byMonth = $groups['By Month'];
        $this->assertCount(7, $byMonth);
        $this->assertArrayHasKey('split:month_11', $byMonth);
        $this->assertArrayHasKey('split:month_5', $byMonth);
        $this->assertSame('November', $byMonth['split:month_11']);
        $this->assertSame('May', $byMonth['split:month_5']);
    }

    public function testVsDivisionGroupMatchesLeagueConstant(): void
    {
        $service = $this->createServiceWithTeamData();
        $season = $this->createSeasonStub('Regular Season');
        $groups = $service->buildDropdownGroups($season);

        $vsDivision = $groups['vs. Division'];
        $this->assertCount(count(League::DIVISION_NAMES), $vsDivision);

        foreach (League::DIVISION_NAMES as $division) {
            $key = 'split:div_' . strtolower($division);
            $this->assertArrayHasKey($key, $vsDivision);
            $this->assertSame('vs. ' . $division, $vsDivision[$key]);
        }
    }

    public function testVsConferenceGroupMatchesLeagueConstant(): void
    {
        $service = $this->createServiceWithTeamData();
        $season = $this->createSeasonStub('Regular Season');
        $groups = $service->buildDropdownGroups($season);

        $vsConference = $groups['vs. Conference'];
        $this->assertCount(count(League::CONFERENCE_NAMES), $vsConference);

        foreach (League::CONFERENCE_NAMES as $conference) {
            $key = 'split:conf_' . strtolower($conference);
            $this->assertArrayHasKey($key, $vsConference);
            $this->assertSame('vs. ' . $conference, $vsConference[$key]);
        }
    }

    public function testVsTeamGroupUsesTeamData(): void
    {
        $service = $this->createServiceWithTeamData();
        $season = $this->createSeasonStub('Regular Season');
        $groups = $service->buildDropdownGroups($season);

        $vsTeam = $groups['vs. Team'];
        $this->assertArrayHasKey('split:vs_1', $vsTeam);
        $this->assertSame('vs. Atlanta', $vsTeam['split:vs_1']);
        $this->assertArrayHasKey('split:vs_2', $vsTeam);
        $this->assertSame('vs. Boston', $vsTeam['split:vs_2']);
    }

    public function testAllValuesAreStrings(): void
    {
        $service = $this->createServiceWithTeamData();
        $season = $this->createSeasonStub('Regular Season');
        $groups = $service->buildDropdownGroups($season);

        foreach ($groups as $groupName => $options) {
            $this->assertIsArray($options, "Group '$groupName' should be an array");
            foreach ($options as $key => $label) {
                $this->assertIsString($key, "Key in group '$groupName' should be string");
                $this->assertIsString($label, "Label in group '$groupName' should be string");
            }
        }
    }

    // --- Season DI seam (Recipe B: ctor-injected nullable Season, gated in-place) ---

    /**
     * Seam (positive): an injected Season whose isOffseasonPhase() returns true must
     * steer getRosterAndStarters() to the free-agency roster branch, proving the
     * INJECTED instance reaches the gated `$this->season ?? new Season($db)` call site
     * instead of the fallback.
     */
    public function testInjectedSeasonDrivesRosterBranchToFreeAgency(): void
    {
        $season = self::createStub(Season::class);
        $season->method('isOffseasonPhase')->willReturn(true);

        $repository = $this->createMock(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->expects($this->once())->method('getFreeAgencyRoster')->with(5)->willReturn([]);
        $repository->expects($this->never())->method('getRosterUnderContract');

        $service = new TeamTableService($this->mockDb, $repository, $season);
        $service->getRosterAndStarters(5);
    }

    /**
     * Negative/boundary: the exact production call shape — construct WITHOUT the
     * optional $season arg. The fallback `new Season($db)` fires (in tests the
     * class_alias resolves it to the mock, phase 'Regular Season' =>
     * isOffseasonPhase() false), steering to the under-contract roster branch.
     * No TypeError; the gated method-body construction still runs.
     */
    public function testFallbackSeasonUsedForRosterBranchWhenNoneInjected(): void
    {
        $repository = $this->createMock(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->expects($this->once())->method('getRosterUnderContract')->with(5)->willReturn([]);
        $repository->expects($this->never())->method('getFreeAgencyRoster');

        $service = new TeamTableService($this->mockDb, $repository);
        $service->getRosterAndStarters(5);
    }

    // ============================================
    // Phase 1 characterization — expiring players & the starter set
    // ============================================

    /**
     * Executable form of the Phase 1.2 decision: an expiring-contract player who is
     * not the depth-chart starter must not acquire a star when the offseason roster
     * query is widened to include him.
     *
     * Scope note — the plan's 1.2 prose describes starters as coming from a separate
     * starters table. That is not how this codebase works: extractStartersData() is
     * *roster-derived*, reading the `*_depth` columns carried on each roster row. The
     * exclusion is therefore a property of the depth values, not of the roster filter
     * — which the companion test below proves, and which is why getTableOutput()
     * narrows the row set it feeds to extractStartersData() when $markExpiringRows is
     * on (the narrowing-filter contingency the plan pre-authorised in 1.2).
     */
    public function testExtractStartersDataIgnoresExpiringRosterRows(): void
    {
        $roster = [
            ['pid' => 501, 'name' => 'Contracted Starter', 'teamid' => 7, 'cy' => 1, 'cyt' => 3, 'pg_depth' => 1, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 0, 'c_depth' => 0],
            ['pid' => 502, 'name' => 'Expiring Bench', 'teamid' => 7, 'cy' => 3, 'cyt' => 3, 'pg_depth' => 2, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 0, 'c_depth' => 0],
        ];

        $starters = $this->service->extractStartersData($roster);

        $pids = [];
        foreach ($starters as $data) {
            if ($data['pid'] !== null) {
                $pids[] = $data['pid'];
            }
        }

        $this->assertContains(501, $pids, 'The contracted depth-1 player is the starter');
        $this->assertNotContains(502, $pids, 'The expiring player must not acquire a star');
    }

    /**
     * Negative/boundary companion to the test above. Seeding the *expiring* player at
     * depth 1 must return his pid. Without this, the assertion above would pass for
     * the wrong reason (any always-empty starter set satisfies "does not contain"),
     * and it is what makes the roster-derived derivation fail loudly.
     */
    public function testExtractStartersDataReturnsExpiringPidWhenSeededAsStarter(): void
    {
        $roster = [
            ['pid' => 502, 'name' => 'Expiring Starter', 'teamid' => 7, 'cy' => 3, 'cyt' => 3, 'pg_depth' => 1, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 0, 'c_depth' => 0],
        ];

        $starters = $this->service->extractStartersData($roster);

        $this->assertSame(502, $starters['PG']['pid']);
        $this->assertSame('Expiring Starter', $starters['PG']['name']);
    }

    /**
     * Phase 1.4 characterization: getRosterAndStarters() — the entry point used by
     * the Trading preview (TradeRosterPreviewApiHandler:98) and the Depth Chart
     * (DepthChartController:222) — must keep routing to getFreeAgencyRoster()
     * during the offseason, i.e. to the query that carries `AND cyt != cy`, and must
     * pass its rows through untouched.
     *
     * The SQL-level exclusion itself is pinned by the DatabaseIntegration test
     * TeamRepositoryTest::testGetFreeAgencyRosterExcludesExpiringContracts(); this
     * unit test pins the *routing*, which is the part this PR could regress.
     */
    public function testGetRosterAndStartersExcludesExpiringPlayers(): void
    {
        $season = self::createStub(Season::class);
        $season->method('isOffseasonPhase')->willReturn(true);

        // Exactly the rows getFreeAgencyRoster()'s `AND cyt != cy` filter returns.
        $filtered = [
            ['pid' => 601, 'name' => 'Contracted Guy', 'teamid' => 7, 'cy' => 1, 'cyt' => 3, 'pg_depth' => 1, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 0, 'c_depth' => 0],
        ];

        $repository = $this->createMock(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->expects($this->once())->method('getFreeAgencyRoster')->with(7)->willReturn($filtered);
        $repository->expects($this->never())->method('getRosterUnderContract');

        $service = new TeamTableService($this->mockDb, $repository, $season);
        $out = $service->getRosterAndStarters(7);

        $names = array_column($out['roster'], 'name');
        $this->assertSame(['Contracted Guy'], $names);
        $this->assertNotContains('Expiring Guy', $names);
        $this->assertSame([601], $out['starterPids']);
    }

    /**
     * Boundary guard against an over-broad filter: a player in a later contract year
     * who is NOT expiring (cy 3 of 4) must still reach the Trading/Depth Chart roster.
     */
    public function testGetRosterAndStartersIncludesMidContractPlayer(): void
    {
        $season = self::createStub(Season::class);
        $season->method('isOffseasonPhase')->willReturn(true);

        $filtered = [
            ['pid' => 603, 'name' => 'Mid Contract Guy', 'teamid' => 7, 'cy' => 3, 'cyt' => 4, 'pg_depth' => 0, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 0, 'c_depth' => 0],
        ];

        $repository = $this->createMock(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->expects($this->once())->method('getFreeAgencyRoster')->with(7)->willReturn($filtered);
        $repository->expects($this->never())->method('getRosterUnderContract');

        $service = new TeamTableService($this->mockDb, $repository, $season);
        $out = $service->getRosterAndStarters(7);

        $this->assertContains('Mid Contract Guy', array_column($out['roster'], 'name'));
    }

    /**
     * getRosterAndStarters() is deliberately frozen at one parameter — the shared
     * consumers call it positionally and this PR must not thread anything through it.
     */
    public function testGetRosterAndStartersSignatureUnchanged(): void
    {
        $method = new \ReflectionMethod(TeamTableService::class, 'getRosterAndStarters');

        $this->assertSame(1, $method->getNumberOfParameters());
        $this->assertSame('teamid', $method->getParameters()[0]->getName());
    }

    // ============================================
    // Phase 3 tests — markExpiringRows flag, interface parity, roster routing
    // ============================================

    /**
     * Build a minimal team row that satisfies Team::fill() so that
     * Team::initialize($this->db, $teamid) succeeds with the MockDatabase.
     *
     * @return array<string, mixed>
     */
    private function buildMockTeamRow(int $teamid): array
    {
        return [
            'teamid'       => $teamid,
            'team_city'    => 'Test City',
            'team_name'    => 'Test Team',
            'color1'       => 'FF0000',
            'color2'       => '0000FF',
            'arena'        => 'Test Arena',
            'capacity'     => 10000,
            'owner_name'   => 'Test Owner',
            'owner_email'  => 'owner@test.com',
            'discord_id'   => null,
            'league_record' => null,
        ];
    }

    /**
     * Build a full player roster row with all fields expected by PlayerDataMapper,
     * so that getTableOutput() can render without triggering "undefined array key" warnings.
     *
     * @param array<string, mixed> $overrides Fields to override on the base row
     * @return array<string, mixed>
     */
    private function buildFullRosterRow(array $overrides = []): array
    {
        $base = [
            // Basic
            'pid' => 1, 'ordinal' => 1, 'name' => 'Player', 'age' => 25,
            'teamid' => 5, 'pos' => 'PG',
            // Ratings
            'r_fga' => 10, 'r_fgp' => 45, 'r_fta' => 5, 'r_ftp' => 80,
            'r_3ga' => 3, 'r_3gp' => 35, 'r_orb' => 2, 'r_drb' => 4,
            'r_ast' => 6, 'r_stl' => 1, 'r_tvr' => 2, 'r_blk' => 0,
            'r_foul' => 2, 'oo' => 70, 'od' => 65, 'r_drive_off' => 68,
            'dd' => 62, 'po' => 55, 'pd' => 50, 'r_trans_off' => 72,
            'td' => 70, 'clutch' => 65, 'consistency' => 70,
            'talent' => 70, 'skill' => 65, 'intangibles' => 60,
            // Free agency
            'loyalty' => 50, 'playing_time' => 50, 'winner' => 50,
            'tradition' => 50, 'security' => 50,
            // Contract
            'exp' => 3, 'bird' => 3, 'cy' => 1, 'cyt' => 3,
            'salary_yr1' => 1000000, 'salary_yr2' => 1000000,
            'salary_yr3' => 1000000, 'salary_yr4' => 0,
            'salary_yr5' => 0, 'salary_yr6' => 0,
            // Draft
            'draftyear' => 2020, 'draftround' => 1, 'draftpickno' => 5,
            // Physical
            'htft' => 6, 'htin' => 3, 'wt' => 200,
            // Status
            'injured' => 0, 'retired' => 0, 'droptime' => null,
            // Depth
            'pg_depth' => 0, 'sg_depth' => 0, 'sf_depth' => 0,
            'pf_depth' => 0, 'c_depth' => 0,
        ];
        return array_merge($base, $overrides);
    }

    /**
     * Interface parity: renderTableForDisplay() on the interface and implementation
     * must have the same parameter count (8) and the 8th parameter must be named
     * markExpiringRows with a default of false.
     */
    public function testRenderTableForDisplayMatchesInterfaceSignature(): void
    {
        $implMethod = new \ReflectionMethod(TeamTableService::class, 'renderTableForDisplay');
        $ifaceMethod = new \ReflectionMethod(\Team\Contracts\TeamTableServiceInterface::class, 'renderTableForDisplay');

        $this->assertSame(8, $implMethod->getNumberOfParameters(), 'Implementation must have 8 parameters');
        $this->assertSame(8, $ifaceMethod->getNumberOfParameters(), 'Interface must have 8 parameters');

        $param = $implMethod->getParameters()[7];
        $this->assertSame('markExpiringRows', $param->getName());
        $this->assertTrue($param->isDefaultValueAvailable());
        $this->assertFalse($param->getDefaultValue());
    }

    /**
     * Phase 3.1: getTableOutput() during an offseason must call getRosterUnderContract()
     * for a real team (not getFreeAgencyRoster()), proving the widening.
     */
    public function testGetTableOutputUsesUnderContractRosterDuringOffseason(): void
    {
        $this->mockDb->onQuery('ibl_team_info', [$this->buildMockTeamRow(5)]);

        $season = self::createStub(Season::class);
        $season->method('isOffseasonPhase')->willReturn(true);

        $repository = $this->createMock(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->expects($this->once())->method('getRosterUnderContract')->with(5)->willReturn([]);
        $repository->expects($this->never())->method('getFreeAgencyRoster');
        $repository->method('getTeam')->willReturn(['color1' => '000000', 'color2' => 'FFFFFF']);

        $service = new TeamTableService($this->mockDb, $repository, $season);
        $service->getTableOutput(5, null, 'ratings');
    }

    /**
     * Negative/boundary: during Regular Season (isOffseasonPhase() false), the flag
     * must not fire — no player-fa-expiring-row class in the output.
     */
    public function testMarkExpiringRowsIsFalseDuringRegularSeason(): void
    {
        $this->mockDb->onQuery('ibl_team_info', [$this->buildMockTeamRow(5)]);

        $season = self::createStub(Season::class);
        $season->method('isOffseasonPhase')->willReturn(false);
        $season->phase = 'Regular Season';
        $season->lastSimEndDate = '2025-01-01';

        $repository = self::createStub(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->method('getRosterUnderContract')->willReturn([]);
        $repository->method('getTeam')->willReturn(['color1' => '000000', 'color2' => 'FFFFFF']);

        $service = new TeamTableService($this->mockDb, $repository, $season);
        $html = $service->getTableOutput(5, null, 'ratings');

        $this->assertStringNotContainsString('player-fa-expiring-row', $html);
    }

    /**
     * Negative/boundary: getTableOutput(0, ...) — the Free Agents pool —
     * must NOT emit player-fa-expiring-row even during an offseason.
     */
    public function testMarkExpiringRowsIsFalseForFreeAgentPoolDuringOffseason(): void
    {
        $this->mockDb->onQuery('ibl_team_info', [$this->buildMockTeamRow(0)]);

        $season = self::createStub(Season::class);
        $season->method('isOffseasonPhase')->willReturn(true);
        $season->phase = 'Free Agency';
        $season->lastSimEndDate = '2025-01-01';

        $repository = self::createStub(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->method('getFreeAgents')->willReturn([]);
        $repository->method('getTeam')->willReturn(['color1' => '000000', 'color2' => 'FFFFFF']);

        $service = new TeamTableService($this->mockDb, $repository, $season);
        $html = $service->getTableOutput(0, null, 'ratings');

        $this->assertStringNotContainsString('player-fa-expiring-row', $html);
    }

    /**
     * Negative/boundary: getTableOutput(-1, ...) — entire league roster —
     * must NOT emit player-fa-expiring-row even during an offseason.
     */
    public function testMarkExpiringRowsIsFalseForEntireLeagueRosterDuringOffseason(): void
    {
        $this->mockDb->onQuery('ibl_team_info', [$this->buildMockTeamRow(-1)]);

        $season = self::createStub(Season::class);
        $season->method('isOffseasonPhase')->willReturn(true);
        $season->phase = 'Free Agency';
        $season->lastSimEndDate = '2025-01-01';

        $repository = self::createStub(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->method('getEntireLeagueRoster')->willReturn([]);
        $repository->method('getTeam')->willReturn(['color1' => '000000', 'color2' => 'FFFFFF']);

        $service = new TeamTableService($this->mockDb, $repository, $season);
        $html = $service->getTableOutput(-1, null, 'ratings');

        $this->assertStringNotContainsString('player-fa-expiring-row', $html);
    }

    /**
     * Negative/boundary: a historical view (yr !== '') must use getHistoricalRoster()
     * and must NOT emit player-fa-expiring-row (historical pages show past state).
     */
    public function testMarkExpiringRowsIsFalseForHistoricalRosterDuringOffseason(): void
    {
        $this->mockDb->onQuery('ibl_team_info', [$this->buildMockTeamRow(5)]);

        $season = self::createStub(Season::class);
        $season->method('isOffseasonPhase')->willReturn(true);
        $season->phase = 'Free Agency';
        $season->lastSimEndDate = '2025-01-01';

        $repository = $this->createMock(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->expects($this->once())->method('getHistoricalRoster')->with(5, '2029')->willReturn([]);
        $repository->expects($this->never())->method('getRosterUnderContract');
        $repository->method('getTeam')->willReturn(['color1' => '000000', 'color2' => 'FFFFFF']);

        $service = new TeamTableService($this->mockDb, $repository, $season);
        $html = $service->getTableOutput(5, '2029', 'ratings');

        $this->assertStringNotContainsString('player-fa-expiring-row', $html);
    }

    /**
     * Sort order guard: the expiring-rows path must not reorder the roster.
     * Players must appear in the order the repository returns them.
     */
    public function testExpiringPlayersKeepNormalSortOrder(): void
    {
        $this->mockDb->onQuery('ibl_team_info', [$this->buildMockTeamRow(5)]);

        $season = self::createStub(Season::class);
        $season->method('isOffseasonPhase')->willReturn(true);
        $season->phase = 'Free Agency';
        $season->lastSimEndDate = '2025-01-01';

        // Roster rows: expiring first, then under-contract. Order must be preserved.
        $roster = [
            $this->buildFullRosterRow(['pid' => 10, 'name' => 'Alpha Expiring', 'cy' => 3, 'cyt' => 3, 'pg_depth' => 2]),
            $this->buildFullRosterRow(['pid' => 11, 'name' => 'Beta Contracted', 'cy' => 1, 'cyt' => 3, 'pg_depth' => 1]),
        ];

        $repository = self::createStub(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->method('getRosterUnderContract')->willReturn($roster);
        $repository->method('getTeam')->willReturn(['color1' => '000000', 'color2' => 'FFFFFF']);

        $service = new TeamTableService($this->mockDb, $repository, $season);
        $html = $service->getTableOutput(5, null, 'ratings');

        // Alpha must appear before Beta in the HTML
        $posAlpha = strpos($html, 'Alpha Expiring');
        $posBeta  = strpos($html, 'Beta Contracted');
        $this->assertNotFalse($posAlpha, 'Alpha Expiring must be in output');
        $this->assertNotFalse($posBeta, 'Beta Contracted must be in output');
        $this->assertLessThan($posBeta, $posAlpha, 'Alpha must appear before Beta (sort order preserved)');
    }

    // ============================================
    // getTableOutput() WRAPPER PINS (tab path)
    // ============================================

    /** The wrapper the view-control component injects: first <caption …> through its </caption>. */
    private static function captionOf(string $html): string
    {
        $start = strpos($html, '<caption class="team-table-caption">');
        self::assertNotFalse($start, 'wrapper caption missing');
        $end = strpos($html, '</caption>', $start);
        self::assertNotFalse($end, 'wrapper caption unterminated');
        return substr($html, $start, $end + strlen('</caption>') - $start);
    }

    /**
     * Ordered data-display keys of the tab anchors in a caption.
     *
     * @return list<string>
     */
    private static function displayKeysOf(string $caption): array
    {
        preg_match_all('/data-display="([^"]+)"/', $caption, $m);
        return $m[1];
    }

    /**
     * Ordered option values inside the named <optgroup>.
     *
     * @return list<string>
     */
    private static function optionValuesOf(string $caption, string $groupLabel): array
    {
        $open = '<optgroup label="' . $groupLabel . '">';
        $start = strpos($caption, $open);
        self::assertNotFalse($start, "optgroup $groupLabel missing");
        $end = strpos($caption, '</optgroup>', $start);
        self::assertNotFalse($end);
        preg_match_all('/<option value="([^"]*)"/', substr($caption, $start, $end - $start), $m);
        return $m[1];
    }

    /**
     * Season stub whose isOffseasonPhase() matches production: Draft and Free Agency are offseason.
     */
    private function seasonStubForPhase(string $phase): Season
    {
        $season = self::createStub(Season::class);
        $season->phase = $phase;
        $season->method('isOffseasonPhase')->willReturn(in_array($phase, ['Draft', 'Free Agency'], true));
        return $season;
    }

    /**
     * Historical rows render through the snapshot mapper, which reads these extra columns.
     *
     * @return array<string, mixed>
     */
    private function buildHistoricalRosterRow(): array
    {
        return $this->buildFullRosterRow([
            'year' => 2024, 'team' => 'Test Team', 'r_2ga' => 8, 'r_2gp' => 50,
            'r_oo' => 70, 'r_od' => 65, 'r_dd' => 62, 'r_po' => 55, 'r_pd' => 50,
            'r_td' => 70, 'salary' => 1000000,
        ]);
    }

    /**
     * Render getTableOutput() down the tab path (teamid <= 0, or a historical yr).
     * The roster source is wired by teamid and the free-agent argument is pinned to the phase.
     */
    private function tabPathHtml(string $phase, int $teamid, ?string $yr, string $display = 'ratings'): string
    {
        $this->mockDb->onQuery('ibl_team_info', [$this->buildMockTeamRow($teamid)]);
        $rows = [$yr === null ? $this->buildFullRosterRow() : $this->buildHistoricalRosterRow()];

        $isOffseason = in_array($phase, ['Draft', 'Free Agency'], true);
        $repository = self::createStub(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->method('getFreeAgents')->willReturnCallback(
            static function (bool $isFreeAgency) use ($isOffseason, $rows): array {
                self::assertSame($isOffseason, $isFreeAgency);
                return $rows;
            }
        );
        $repository->method('getEntireLeagueRoster')->willReturn($rows);
        $repository->method('getHistoricalRoster')->willReturn($rows);
        $repository->method('getTeam')->willReturn(['color1' => '000000', 'color2' => 'FFFFFF']);

        $service = new TeamTableService($this->mockDb, $repository, $this->seasonStubForPhase($phase));
        return $service->getTableOutput($teamid, $yr, $display);
    }

    public function testTabCaptionForHistoricalRosterIsByteIdentical(): void
    {
        $caption = self::captionOf($this->tabPathHtml('Regular Season', 5, '2024'));

        $expected = <<<'HTML'
<caption class="team-table-caption"><div class="ibl-tabs" style="--team-color-primary: #000000; --team-color-secondary: #FFFFFF;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=ratings" class="ibl-tab ibl-tab--active" data-display="ratings" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5&amp;yr=2024&amp;display=ratings" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=ratings">Ratings</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=total_s" class="ibl-tab" data-display="total_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5&amp;yr=2024&amp;display=total_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=total_s">Season Totals</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=avg_s" class="ibl-tab" data-display="avg_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5&amp;yr=2024&amp;display=avg_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=avg_s">Season Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=per36mins" class="ibl-tab" data-display="per36mins" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5&amp;yr=2024&amp;display=per36mins" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=per36mins">Per 36 Minutes</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=chunk" class="ibl-tab" data-display="chunk" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5&amp;yr=2024&amp;display=chunk" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=chunk">Sim Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=contracts" class="ibl-tab" data-display="contracts" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5&amp;yr=2024&amp;display=contracts" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=contracts">Contracts</a></div></caption>
HTML;
        $this->assertSame($expected, $caption);
    }

    public function testTabCaptionForFreeAgentPoolIsByteIdentical(): void
    {
        $caption = self::captionOf($this->tabPathHtml('Regular Season', 0, null));

        $expected = <<<'HTML'
<caption class="team-table-caption"><div class="ibl-tabs" style="--team-color-primary: #000000; --team-color-secondary: #FFFFFF;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=ratings" class="ibl-tab ibl-tab--active" data-display="ratings" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=ratings" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=ratings">Ratings</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=total_s" class="ibl-tab" data-display="total_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=total_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=total_s">Season Totals</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=avg_s" class="ibl-tab" data-display="avg_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=avg_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=avg_s">Season Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=per36mins" class="ibl-tab" data-display="per36mins" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=per36mins" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=per36mins">Per 36 Minutes</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=chunk" class="ibl-tab" data-display="chunk" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=chunk" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=chunk">Sim Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=contracts" class="ibl-tab" data-display="contracts" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=contracts" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=contracts">Contracts</a></div></caption>
HTML;
        $this->assertSame($expected, $caption);
    }

    public function testTabCaptionForEntireLeagueIsByteIdentical(): void
    {
        $caption = self::captionOf($this->tabPathHtml('Regular Season', -1, null));

        $expected = <<<'HTML'
<caption class="team-table-caption"><div class="ibl-tabs" style="--team-color-primary: #000000; --team-color-secondary: #FFFFFF;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=ratings" class="ibl-tab ibl-tab--active" data-display="ratings" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=-1&amp;display=ratings" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=ratings">Ratings</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=total_s" class="ibl-tab" data-display="total_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=-1&amp;display=total_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=total_s">Season Totals</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=avg_s" class="ibl-tab" data-display="avg_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=-1&amp;display=avg_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=avg_s">Season Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=per36mins" class="ibl-tab" data-display="per36mins" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=-1&amp;display=per36mins" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=per36mins">Per 36 Minutes</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=chunk" class="ibl-tab" data-display="chunk" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=-1&amp;display=chunk" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=chunk">Sim Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=contracts" class="ibl-tab" data-display="contracts" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=-1&amp;display=contracts" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=contracts">Contracts</a></div></caption>
HTML;
        $this->assertSame($expected, $caption);
    }

    /** @return array<string, array{string, int, ?string}> */
    public static function tabPathOffseasonPhaseProvider(): array
    {
        $rows = [];
        foreach (['Playoffs', 'Draft', 'Free Agency'] as $phase) {
            $rows["$phase / historical 5"] = [$phase, 5, '2024'];
            $rows["$phase / free agents"]  = [$phase, 0, null];
            $rows["$phase / league"]       = [$phase, -1, null];
        }
        return $rows;
    }

    #[DataProvider('tabPathOffseasonPhaseProvider')]
    public function testTabPathAddsPlayoffsTabBeforeContractsOutsideRegularSeason(string $phase, int $teamid, ?string $yr): void
    {
        $caption = self::captionOf($this->tabPathHtml($phase, $teamid, $yr));

        $this->assertSame(
            ['ratings', 'total_s', 'avg_s', 'per36mins', 'chunk', 'playoffs', 'contracts'],
            self::displayKeysOf($caption)
        );

        $playoffsAnchor = match ($teamid) {
            5 => '<a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=playoffs" class="ibl-tab" data-display="playoffs" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5&amp;yr=2024&amp;display=playoffs" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=playoffs">Playoffs Averages</a>',
            0 => '<a href="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=playoffs" class="ibl-tab" data-display="playoffs" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=0&amp;display=playoffs" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=0&amp;display=playoffs">Playoffs Averages</a>',
            -1 => '<a href="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=playoffs" class="ibl-tab" data-display="playoffs" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=-1&amp;display=playoffs" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=-1&amp;display=playoffs">Playoffs Averages</a>',
            default => self::fail("unexpected teamid $teamid"),
        };
        $this->assertStringContainsString($playoffsAnchor, $caption);
    }

    /** @return array<string, array{int, ?string}> */
    public static function tabPathSourceProvider(): array
    {
        return [
            'historical 5' => [5, '2024'],
            'free agents'  => [0, null],
            'league'       => [-1, null],
        ];
    }

    #[DataProvider('tabPathSourceProvider')]
    public function testTabPathOmitsPlayoffsTabDuringRegularSeason(int $teamid, ?string $yr): void
    {
        $caption = self::captionOf($this->tabPathHtml('Regular Season', $teamid, $yr));

        $this->assertSame(
            ['ratings', 'total_s', 'avg_s', 'per36mins', 'chunk', 'contracts'],
            self::displayKeysOf($caption)
        );
        $this->assertStringNotContainsString('Playoffs Averages', $caption);
    }

    public function testTabPathMarksNonDefaultDisplayActive(): void
    {
        $caption = self::captionOf($this->tabPathHtml('Regular Season', 5, '2024', 'avg_s'));

        $expected = <<<'HTML'
<caption class="team-table-caption"><div class="ibl-tabs" style="--team-color-primary: #000000; --team-color-secondary: #FFFFFF;"><a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=ratings" class="ibl-tab" data-display="ratings" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5&amp;yr=2024&amp;display=ratings" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=ratings">Ratings</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=total_s" class="ibl-tab" data-display="total_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5&amp;yr=2024&amp;display=total_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=total_s">Season Totals</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=avg_s" class="ibl-tab ibl-tab--active" data-display="avg_s" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5&amp;yr=2024&amp;display=avg_s" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=avg_s">Season Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=per36mins" class="ibl-tab" data-display="per36mins" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5&amp;yr=2024&amp;display=per36mins" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=per36mins">Per 36 Minutes</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=chunk" class="ibl-tab" data-display="chunk" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5&amp;yr=2024&amp;display=chunk" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=chunk">Sim Averages</a><a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=contracts" class="ibl-tab" data-display="contracts" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5&amp;yr=2024&amp;display=contracts" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-push-url="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;yr=2024&amp;display=contracts">Contracts</a></div></caption>
HTML;
        $this->assertSame($expected, $caption);
        $this->assertSame(1, substr_count($caption, 'ibl-tab--active'));
    }

    #[DataProvider('tabPathSourceProvider')]
    public function testTabPathNeverCallsGetAllTeamsAndCallsGetTeamOnce(int $teamid, ?string $yr): void
    {
        $this->mockDb->onQuery('ibl_team_info', [$this->buildMockTeamRow($teamid)]);
        $rows = [$yr === null ? $this->buildFullRosterRow() : $this->buildHistoricalRosterRow()];

        $repository = $this->createMock(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->method('getFreeAgents')->willReturn($rows);
        $repository->method('getEntireLeagueRoster')->willReturn($rows);
        $repository->method('getHistoricalRoster')->willReturn($rows);
        $repository->expects($this->never())->method('getAllTeams');
        $repository->expects($this->once())->method('getTeam')->with($teamid)
            ->willReturn(['color1' => '000000', 'color2' => 'FFFFFF']);

        $service = new TeamTableService($this->mockDb, $repository, $this->seasonStubForPhase('Regular Season'));
        $html = $service->getTableOutput($teamid, $yr, 'ratings');

        $this->assertStringContainsString('ibl-tabs', self::captionOf($html));
    }

    public function testHostileYrIsAttributeEscapedInTabCaption(): void
    {
        $yr = '2024"><script>alert(1)</script>';
        $caption = self::captionOf($this->tabPathHtml('Regular Season', 5, $yr));

        $this->assertStringNotContainsString('"><script>', $caption);
        $this->assertStringContainsString(
            '&amp;yr=2024&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;&amp;display=ratings',
            $caption
        );
    }

    // ============================================
    // getTableOutput() WRAPPER PINS (dropdown path)
    // ============================================

    /**
     * Repository stub wired for a current-season dropdown render.
     */
    private function dropdownRepository(): \Team\Contracts\TeamRepositoryInterface
    {
        $this->mockDb->onQuery('ibl_team_info', [$this->buildMockTeamRow(5)]);

        $repository = self::createStub(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->method('getRosterUnderContract')->willReturn([$this->buildFullRosterRow()]);
        $repository->method('getTeam')->willReturn(['color1' => '000000', 'color2' => 'FFFFFF']);
        $repository->method('getAllTeams')->willReturn([
            ['teamid' => 1, 'team_name' => 'Atlanta'],
            ['teamid' => 2, 'team_name' => 'Boston'],
        ]);
        return $repository;
    }

    private function dropdownCaption(string $phase, ?string $yr, string $display, ?string $split = null): string
    {
        $service = new TeamTableService($this->mockDb, $this->dropdownRepository(), $this->seasonStubForPhase($phase));
        return self::captionOf($service->getTableOutput(5, $yr, $display, $split));
    }

    public function testDropdownCaptionForCurrentSeasonTeamIsByteIdentical(): void
    {
        $caption = $this->dropdownCaption('Regular Season', null, 'ratings');

        $expected = <<<'HTML'
<caption class="team-table-caption"><div class="ibl-view-dropdown" style="--team-color-primary: #000000; --team-color-secondary: #FFFFFF;"><select class="ibl-view-select" aria-label="Stats display" hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5" hx-target="closest .table-scroll-container" hx-swap="innerHTML" hx-trigger="change" onchange="if(window.htmx)return;var v=this.value,d=v,s='';if(v.indexOf('split:')===0){d='split';s='&amp;split='+encodeURIComponent(v.substring(6))}else{d=encodeURIComponent(d)}window.location.href='modules.php?name=Team&amp;op=team&amp;teamid=5&amp;display='+d+s"><optgroup label="Views"><option value="ratings" selected>Ratings</option><option value="total_s">Season Totals</option><option value="avg_s">Season Averages</option><option value="per36mins">Per 36 Minutes</option><option value="chunk">Sim Averages</option><option value="contracts">Contracts</option></optgroup><optgroup label="Location"><option value="split:home">Home</option><option value="split:road">Road</option></optgroup><optgroup label="Result"><option value="split:wins">Wins</option><option value="split:losses">Losses</option></optgroup><optgroup label="Season Half"><option value="split:pre_allstar">Pre All-Star</option><option value="split:post_allstar">Post All-Star</option></optgroup><optgroup label="By Month"><option value="split:month_11">November</option><option value="split:month_12">December</option><option value="split:month_1">January</option><option value="split:month_2">February</option><option value="split:month_3">March</option><option value="split:month_4">April</option><option value="split:month_5">May</option></optgroup><optgroup label="vs. Division"><option value="split:div_atlantic">vs. Atlantic</option><option value="split:div_central">vs. Central</option><option value="split:div_midwest">vs. Midwest</option><option value="split:div_pacific">vs. Pacific</option></optgroup><optgroup label="vs. Conference"><option value="split:conf_eastern">vs. Eastern</option><option value="split:conf_western">vs. Western</option></optgroup><optgroup label="vs. Team"><option value="split:vs_1">vs. Atlanta</option><option value="split:vs_2">vs. Boston</option></optgroup></select><noscript><a href="modules.php?name=Team&amp;op=team&amp;teamid=5&amp;display=ratings">Back to Ratings</a></noscript></div></caption>
HTML;
        $this->assertSame($expected, $caption);
    }

    /** @return array<string, array{string}> */
    public static function offseasonPhaseProvider(): array
    {
        return [
            'Playoffs'     => ['Playoffs'],
            'Draft'        => ['Draft'],
            'Free Agency'  => ['Free Agency'],
        ];
    }

    #[DataProvider('offseasonPhaseProvider')]
    public function testDropdownViewsGroupAddsPlayoffsOutsideRegularSeason(string $phase): void
    {
        $caption = $this->dropdownCaption($phase, null, 'ratings');

        $this->assertSame(
            ['ratings', 'total_s', 'avg_s', 'per36mins', 'chunk', 'playoffs', 'contracts'],
            self::optionValuesOf($caption, 'Views')
        );
        $this->assertStringContainsString('<option value="playoffs">Playoffs Averages</option>', $caption);
    }

    public function testDropdownViewsGroupOmitsPlayoffsDuringRegularSeason(): void
    {
        $caption = $this->dropdownCaption('Regular Season', null, 'ratings');
        $values = self::optionValuesOf($caption, 'Views');

        $this->assertNotContains('playoffs', $values);
        $this->assertSame(
            ['ratings', 'total_s', 'avg_s', 'per36mins', 'chunk', 'contracts'],
            $values
        );
    }

    public function testEmptyYrIsTreatedAsCurrentSeason(): void
    {
        $this->mockDb->onQuery('ibl_team_info', [$this->buildMockTeamRow(5)]);

        $repository = $this->createMock(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->expects($this->once())->method('getRosterUnderContract')->with(5)
            ->willReturn([$this->buildFullRosterRow()]);
        $repository->expects($this->never())->method('getHistoricalRoster');
        $repository->method('getTeam')->willReturn(['color1' => '000000', 'color2' => 'FFFFFF']);
        $repository->method('getAllTeams')->willReturn([
            ['teamid' => 1, 'team_name' => 'Atlanta'],
            ['teamid' => 2, 'team_name' => 'Boston'],
        ]);

        $service = new TeamTableService($this->mockDb, $repository, $this->seasonStubForPhase('Regular Season'));
        $caption = self::captionOf($service->getTableOutput(5, '', 'ratings'));

        $this->assertStringContainsString('<select class="ibl-view-select"', $caption);
        $this->assertStringNotContainsString('ibl-tabs', $caption);
        $this->assertStringContainsString('hx-get="modules.php?name=Team&amp;op=api&amp;teamid=5"', $caption);
        $this->assertStringNotContainsString('yr=', $caption);
    }

    public function testDropdownMarksNonDefaultDisplaySelected(): void
    {
        $caption = $this->dropdownCaption('Regular Season', null, 'avg_s');

        $this->assertStringContainsString('<option value="avg_s" selected>', $caption);
        $this->assertSame(1, substr_count($caption, ' selected'));
    }

    public function testDropdownSelectsSplitOptionWhenSplitGiven(): void
    {
        $caption = $this->dropdownCaption('Regular Season', null, 'split', 'home');

        $this->assertStringContainsString('<option value="split:home" selected>', $caption);
        $this->assertSame(1, substr_count($caption, ' selected'));
    }

    public function testDropdownSelectsNothingWhenSplitMissing(): void
    {
        $caption = $this->dropdownCaption('Regular Season', null, 'split', null);

        $this->assertSame(0, substr_count($caption, ' selected'));
    }

    public function testDropdownPathCallsGetAllTeamsOnceAndGetTeamOnce(): void
    {
        $this->mockDb->onQuery('ibl_team_info', [$this->buildMockTeamRow(5)]);

        $repository = $this->createMock(\Team\Contracts\TeamRepositoryInterface::class);
        $repository->method('getRosterUnderContract')->willReturn([$this->buildFullRosterRow()]);
        $repository->expects($this->once())->method('getAllTeams')->willReturn([
            ['teamid' => 1, 'team_name' => 'Atlanta'],
            ['teamid' => 2, 'team_name' => 'Boston'],
        ]);
        $repository->expects($this->once())->method('getTeam')->with(5)
            ->willReturn(['color1' => '000000', 'color2' => 'FFFFFF']);

        $service = new TeamTableService($this->mockDb, $repository, $this->seasonStubForPhase('Regular Season'));
        $caption = self::captionOf($service->getTableOutput(5, null, 'ratings'));

        $this->assertStringContainsString('ibl-view-select', $caption);
    }
}
