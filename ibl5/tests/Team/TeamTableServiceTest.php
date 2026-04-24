<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Team\Contracts\TeamTableServiceInterface;
use League\League;
use Team\TeamTableService;
use Season\Season;

/**
 * Tests for TeamTableService
 *
 * Validates table rendering, starters extraction, and dropdown group logic
 */
class TeamTableServiceTest extends TestCase
{
    private \MockDatabase $mockDb;
    private TeamTableService $service;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $repository = new \Team\TeamRepository($this->mockDb);
        $this->service = new TeamTableService($this->mockDb, $repository);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(TeamTableServiceInterface::class, $this->service);
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
        $mockDb = new \MockDatabase();
        $mockDb->setMockData([
            ['teamid' => 1, 'team_name' => 'Atlanta'],
            ['teamid' => 2, 'team_name' => 'Boston'],
        ]);
        $repository = new \Team\TeamRepository($mockDb);
        return new TeamTableService($mockDb, $repository);
    }

    private function createSeasonStub(string $phase): Season
    {
        $season = $this->createStub(Season::class);
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
}
