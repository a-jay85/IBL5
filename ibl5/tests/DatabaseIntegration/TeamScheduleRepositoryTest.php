<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use TeamSchedule\TeamScheduleRepository;

#[Group('database')]
class TeamScheduleRepositoryTest extends DatabaseTestCase
{
    private TeamScheduleRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TeamScheduleRepository($this->db);
    }

    public function testGetScheduleReturnsGamesWhereTeamIsVisitor(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-01-10', 5, 100, 6, 95);

        $results = $this->repo->getSchedule(5, 2090);

        $found = $this->findBySchedId($results, $schedId);
        self::assertNotNull($found, 'Team as visitor should appear');
    }

    public function testGetScheduleReturnsGamesWhereTeamIsHome(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-01-11', 5, 90, 6, 100);

        $results = $this->repo->getSchedule(6, 2090);

        $found = $this->findBySchedId($results, $schedId);
        self::assertNotNull($found, 'Team as home should appear');
    }

    public function testGetScheduleExcludesUnrelatedTeams(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-01-12', 5, 90, 6, 100);

        $results = $this->repo->getSchedule(7, 2090);

        $found = $this->findBySchedId($results, $schedId);
        self::assertNull($found, 'Unrelated team should not see this game');
    }

    public function testGetSchedulePopulatesGameOfThatDayWhenBstExists(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-02-10', 5, 105, 6, 98);
        $this->insertTeamBoxscoreRow('2090-02-10', 'ts-game', 2, 5, 6);

        $results = $this->repo->getSchedule(5, 2090);

        $found = $this->findBySchedId($results, $schedId);
        self::assertNotNull($found);
        self::assertSame(2, $found['game_of_that_day']);
    }

    public function testGetScheduleNullGameOfThatDayWhenNoBst(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-03-10', 5, 80, 6, 90);

        $results = $this->repo->getSchedule(5, 2090);

        $found = $this->findBySchedId($results, $schedId);
        self::assertNotNull($found);
        // TeamSchedule does NOT normalize null to 0 (unlike LeagueSchedule)
        self::assertNull($found['game_of_that_day']);
    }

    public function testGetScheduleOrderedByDateAsc(): void
    {
        $schedId1 = $this->insertScheduleRow(2090, '2090-04-20', 5, 100, 6, 90);
        $schedId2 = $this->insertScheduleRow(2090, '2090-04-10', 5, 95, 7, 85);

        $results = $this->repo->getSchedule(5, 2090);

        $pos1 = null;
        $pos2 = null;
        foreach ($results as $i => $row) {
            if ($row['id'] === $schedId1) {
                $pos1 = $i;
            }
            if ($row['id'] === $schedId2) {
                $pos2 = $i;
            }
        }
        self::assertNotNull($pos1);
        self::assertNotNull($pos2);
        // April 10 before April 20
        self::assertLessThan($pos1, $pos2);
    }

    public function testGetScheduleReturnsBothHomeAndAwayGames(): void
    {
        $schedIdAway = $this->insertScheduleRow(2090, '2090-05-01', 5, 100, 6, 95);
        $schedIdHome = $this->insertScheduleRow(2090, '2090-05-02', 7, 90, 5, 105);

        $results = $this->repo->getSchedule(5, 2090);

        $foundAway = $this->findBySchedId($results, $schedIdAway);
        $foundHome = $this->findBySchedId($results, $schedIdHome);
        self::assertNotNull($foundAway);
        self::assertNotNull($foundHome);
    }

    public function testGetProjectedGamesReturnsGamesInDateRange(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-06-15', 5, 0, 6, 0);

        $results = $this->repo->getProjectedGamesNextSimResult(5, '2090-06-10', '2090-06-20', 2090);

        $found = $this->findBySchedId($results, $schedId);
        self::assertNotNull($found, 'Game in range should be returned');
    }

    public function testGetProjectedGamesExcludesGameExactlyOnLastSimEndDate(): void
    {
        // ADDDATE excludes lastSimEndDate itself — uses day after
        $schedId = $this->insertScheduleRow(2090, '2090-07-10', 5, 0, 6, 0);

        $results = $this->repo->getProjectedGamesNextSimResult(5, '2090-07-10', '2090-07-20', 2090);

        $found = $this->findBySchedId($results, $schedId);
        self::assertNull($found, 'Game on lastSimEndDate should be excluded by ADDDATE');
    }

    public function testGetProjectedGamesIncludesGameDayAfterLastSimEndDate(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-07-11', 5, 0, 6, 0);

        $results = $this->repo->getProjectedGamesNextSimResult(5, '2090-07-10', '2090-07-20', 2090);

        $found = $this->findBySchedId($results, $schedId);
        self::assertNotNull($found, 'Game day after lastSimEndDate should be included');
    }

    public function testGetProjectedGamesExcludesGameAfterProjectedEnd(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-08-25', 5, 0, 6, 0);

        $results = $this->repo->getProjectedGamesNextSimResult(5, '2090-08-01', '2090-08-20', 2090);

        $found = $this->findBySchedId($results, $schedId);
        self::assertNull($found, 'Game after projected end should be excluded');
    }

    public function testGetProjectedGamesFiltersToRequestedTeamOnly(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-09-15', 7, 0, 8, 0);

        $results = $this->repo->getProjectedGamesNextSimResult(5, '2090-09-10', '2090-09-20', 2090);

        $found = $this->findBySchedId($results, $schedId);
        self::assertNull($found, 'Game for different team should be excluded');
    }

    public function testGetProjectedGamesEmptyWhenNoGamesInRange(): void
    {
        $results = $this->repo->getProjectedGamesNextSimResult(5, '2099-01-01', '2099-01-10', 2099);

        // Filter to only test data
        $testResults = array_filter(
            $results,
            static fn (array $row): bool => $row['season_year'] === 2099,
        );
        self::assertCount(0, $testResults);
    }

    public function testGetProjectedGamesOrderedByDateAsc(): void
    {
        $schedId1 = $this->insertScheduleRow(2090, '2090-10-20', 5, 0, 6, 0);
        $schedId2 = $this->insertScheduleRow(2090, '2090-10-15', 5, 0, 7, 0);

        $results = $this->repo->getProjectedGamesNextSimResult(5, '2090-10-10', '2090-10-25', 2090);

        $pos1 = null;
        $pos2 = null;
        foreach ($results as $i => $row) {
            if ($row['id'] === $schedId1) {
                $pos1 = $i;
            }
            if ($row['id'] === $schedId2) {
                $pos2 = $i;
            }
        }
        self::assertNotNull($pos1);
        self::assertNotNull($pos2);
        // Oct 15 before Oct 20
        self::assertLessThan($pos1, $pos2);
    }

    public function testGetScheduleFiltersBySeasonYear(): void
    {
        $currentSeason = $this->insertScheduleRow(2090, '2090-01-10', 5, 100, 6, 95);
        $otherSeason = $this->insertScheduleRow(2089, '2089-06-01', 5, 110, 6, 105);

        $results = $this->repo->getSchedule(5, 2090);

        $foundCurrent = $this->findBySchedId($results, $currentSeason);
        $foundOther = $this->findBySchedId($results, $otherSeason);
        self::assertNotNull($foundCurrent, 'Current season game should appear');
        self::assertNull($foundOther, 'Other season game should be filtered out');
    }

    public function testGetProjectedGamesFiltersBySeasonYear(): void
    {
        $currentSeason = $this->insertScheduleRow(2090, '2090-06-15', 5, 0, 6, 0);
        $otherSeason = $this->insertScheduleRow(2089, '2090-06-15', 5, 0, 7, 0);

        $results = $this->repo->getProjectedGamesNextSimResult(5, '2090-06-10', '2090-06-20', 2090);

        $foundCurrent = $this->findBySchedId($results, $currentSeason);
        $foundOther = $this->findBySchedId($results, $otherSeason);
        self::assertNotNull($foundCurrent, 'Current season game should appear');
        self::assertNull($foundOther, 'Other season game should be filtered out');
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<string, mixed>|null
     */
    private function findBySchedId(array $results, int $schedId): ?array
    {
        foreach ($results as $row) {
            if ($row['id'] === $schedId) {
                return $row;
            }
        }
        return null;
    }
}
