<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Team\Contracts\TeamServiceInterface;
use Team\TeamService;

/**
 * Tests for TeamService::buildDropdownGroups()
 *
 * Validates the dropdown group structure for the table view selector,
 * including conditional playoff availability.
 */
class TeamServiceBuildDropdownGroupsTest extends TestCase
{
    private \MockDatabase $mockDb;
    private TeamService $service;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();

        // Provide mock team data for getAllTeams() used in "vs. Team" group
        $this->mockDb->setMockData([
            ['teamid' => 1, 'team_name' => 'Atlanta'],
            ['teamid' => 2, 'team_name' => 'Boston'],
        ]);

        $repository = new \Team\TeamRepository($this->mockDb);
        $this->service = new TeamService($this->mockDb, $repository);
    }

    public function testReturnsExpectedGroupKeys(): void
    {
        $season = $this->createSeasonStub('Regular Season');
        $groups = $this->service->buildDropdownGroups($season);

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
        $season = $this->createSeasonStub('Regular Season');
        $groups = $this->service->buildDropdownGroups($season);

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
        $season = $this->createSeasonStub('Regular Season');
        $groups = $this->service->buildDropdownGroups($season);

        $this->assertArrayNotHasKey('playoffs', $groups['Views']);
    }

    #[DataProvider('playoffPhaseProvider')]
    public function testPlayoffsAveragesIncludedDuringPostSeason(string $phase): void
    {
        $season = $this->createSeasonStub($phase);
        $groups = $this->service->buildDropdownGroups($season);

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
        $season = $this->createSeasonStub('Regular Season');
        $groups = $this->service->buildDropdownGroups($season);

        $this->assertSame(['split:home' => 'Home', 'split:road' => 'Road'], $groups['Location']);
    }

    public function testResultGroupHasSplitPrefixes(): void
    {
        $season = $this->createSeasonStub('Regular Season');
        $groups = $this->service->buildDropdownGroups($season);

        $this->assertSame(['split:wins' => 'Wins', 'split:losses' => 'Losses'], $groups['Result']);
    }

    public function testByMonthGroupContainsAllMonths(): void
    {
        $season = $this->createSeasonStub('Regular Season');
        $groups = $this->service->buildDropdownGroups($season);

        $byMonth = $groups['By Month'];
        $this->assertCount(7, $byMonth);
        $this->assertArrayHasKey('split:month_11', $byMonth);
        $this->assertArrayHasKey('split:month_5', $byMonth);
        $this->assertSame('November', $byMonth['split:month_11']);
        $this->assertSame('May', $byMonth['split:month_5']);
    }

    public function testVsDivisionGroupMatchesLeagueConstant(): void
    {
        $season = $this->createSeasonStub('Regular Season');
        $groups = $this->service->buildDropdownGroups($season);

        $vsDivision = $groups['vs. Division'];
        $this->assertCount(count(\League::DIVISION_NAMES), $vsDivision);

        foreach (\League::DIVISION_NAMES as $division) {
            $key = 'split:div_' . strtolower($division);
            $this->assertArrayHasKey($key, $vsDivision);
            $this->assertSame('vs. ' . $division, $vsDivision[$key]);
        }
    }

    public function testVsConferenceGroupMatchesLeagueConstant(): void
    {
        $season = $this->createSeasonStub('Regular Season');
        $groups = $this->service->buildDropdownGroups($season);

        $vsConference = $groups['vs. Conference'];
        $this->assertCount(count(\League::CONFERENCE_NAMES), $vsConference);

        foreach (\League::CONFERENCE_NAMES as $conference) {
            $key = 'split:conf_' . strtolower($conference);
            $this->assertArrayHasKey($key, $vsConference);
            $this->assertSame('vs. ' . $conference, $vsConference[$key]);
        }
    }

    public function testVsTeamGroupUsesTeamData(): void
    {
        $season = $this->createSeasonStub('Regular Season');
        $groups = $this->service->buildDropdownGroups($season);

        $vsTeam = $groups['vs. Team'];
        $this->assertArrayHasKey('split:vs_1', $vsTeam);
        $this->assertSame('vs. Atlanta', $vsTeam['split:vs_1']);
        $this->assertArrayHasKey('split:vs_2', $vsTeam);
        $this->assertSame('vs. Boston', $vsTeam['split:vs_2']);
    }

    public function testAllValuesAreStrings(): void
    {
        $season = $this->createSeasonStub('Regular Season');
        $groups = $this->service->buildDropdownGroups($season);

        foreach ($groups as $groupName => $options) {
            $this->assertIsArray($options, "Group '$groupName' should be an array");
            foreach ($options as $key => $label) {
                $this->assertIsString($key, "Key in group '$groupName' should be string");
                $this->assertIsString($label, "Label in group '$groupName' should be string");
            }
        }
    }

    private function createSeasonStub(string $phase): \Season
    {
        $season = $this->createStub(\Season::class);
        $season->phase = $phase;
        return $season;
    }
}
