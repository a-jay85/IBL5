<?php

declare(strict_types=1);

namespace Tests\Module\EntryPoints;

use Tests\Integration\Mocks\TestDataFactory;

/**
 * Integration tests for modules/Schedule/index.php entry point.
 *
 * Exercises the (int) $_GET['teamID'] type-casting boundary with edge cases:
 * missing params, zero, negative, non-numeric strings, floats, out-of-range IDs.
 */
class ScheduleEntryPointTest extends ModuleEntryPointTestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function fullTeamData(array $overrides = []): array
    {
        return array_merge(TestDataFactory::createTeam(), [
            'Used_Extension_This_Chunk' => 0,
            'Used_Extension_This_Season' => 0,
            'leagueRecord' => '10-5',
        ], $overrides);
    }

    public function testMissingTeamIdShowsLeagueSchedule(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Schedule');

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_schedule');
    }

    public function testTeamIdZeroShowsLeagueSchedule(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Schedule', ['teamID' => '0']);

        $this->assertNotEmpty($output);
        // teamID=0 fails the > 0 guard — no Team::initialize call
        $this->assertQueryExecuted('ibl_schedule');
    }

    public function testValidTeamIdShowsTeamSchedule(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData(['teamid' => 5])]);
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Schedule', ['teamID' => '5']);

        $this->assertNotEmpty($output);
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testNegativeTeamIdShowsLeagueSchedule(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Schedule', ['teamID' => '-1']);

        $this->assertNotEmpty($output);
        // (int)'-1' === -1, fails > 0 guard
        $this->assertQueryExecuted('ibl_schedule');
    }

    public function testNonNumericStringTeamIdShowsLeagueSchedule(): void
    {
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Schedule', ['teamID' => 'abc']);

        $this->assertNotEmpty($output);
        // (int)'abc' === 0, fails > 0 guard
        $this->assertQueryExecuted('ibl_schedule');
    }

    public function testFloatStringTeamIdTruncatesToInt(): void
    {
        $this->mockDb->setMockTeamData([self::fullTeamData(['teamid' => 5])]);
        $this->mockDb->setMockData([]);
        $output = $this->runModule('Schedule', ['teamID' => '5.9']);

        $this->assertNotEmpty($output);
        // (int)'5.9' === 5 — queries team 5
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testUnknownTeamIdThrowsRuntimeException(): void
    {
        // Team::initialize throws RuntimeException when no team is found.
        // This reveals the module doesn't handle missing teams gracefully.
        $this->mockDb->setMockTeamData([]);
        $this->mockDb->setMockData([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Team not found: 99999');
        $this->runModule('Schedule', ['teamID' => '99999']);
    }
}
