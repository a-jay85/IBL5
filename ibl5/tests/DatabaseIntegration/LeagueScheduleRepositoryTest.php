<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use LeagueSchedule\LeagueScheduleRepository;

class LeagueScheduleRepositoryTest extends DatabaseTestCase
{
    private LeagueScheduleRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new LeagueScheduleRepository($this->db);
    }

    public function testGetAllGamesReturnsInsertedScheduleRow(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-01-15', 1, 100, 2, 95);

        $results = $this->repo->getAllGamesWithBoxScoreInfo();

        $found = $this->findBySchedId($results, $schedId);
        self::assertNotNull($found);
        self::assertSame(1, $found['visitor_teamid']);
        self::assertSame(2, $found['home_teamid']);
    }

    public function testGameWithBoxScoreHasNonZeroGameOfThatDay(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-02-15', 1, 100, 2, 95);
        $this->insertTeamBoxscoreRow('2090-02-15', 'test-game', 3, 1, 2);

        $results = $this->repo->getAllGamesWithBoxScoreInfo();

        $found = $this->findBySchedId($results, $schedId);
        self::assertNotNull($found);
        self::assertSame(3, $found['game_of_that_day']);
    }

    public function testGameWithoutBoxScoreHasZeroGameOfThatDay(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-03-15', 3, 80, 4, 90);

        $results = $this->repo->getAllGamesWithBoxScoreInfo();

        $found = $this->findBySchedId($results, $schedId);
        self::assertNotNull($found);
        self::assertSame(0, $found['game_of_that_day']);
    }

    public function testMultipleBstRowsReturnsMinGameOfThatDay(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-04-15', 1, 105, 2, 98);
        $this->insertTeamBoxscoreRow('2090-04-15', 'game-a', 5, 1, 2);
        $this->insertTeamBoxscoreRow('2090-04-15', 'game-b', 2, 1, 2);

        $results = $this->repo->getAllGamesWithBoxScoreInfo();

        $found = $this->findBySchedId($results, $schedId);
        self::assertNotNull($found);
        self::assertSame(2, $found['game_of_that_day']);
    }

    public function testResultRowHasAllRequiredKeys(): void
    {
        $schedId = $this->insertScheduleRow(2090, '2090-05-15', 1, 90, 2, 85);

        $results = $this->repo->getAllGamesWithBoxScoreInfo();

        $found = $this->findBySchedId($results, $schedId);
        self::assertNotNull($found);
        $expectedKeys = ['id', 'game_date', 'visitor_teamid', 'visitor_score', 'home_teamid', 'home_score', 'box_id', 'game_of_that_day'];
        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $found, "Missing key: $key");
        }
    }

    public function testOrderedByDateAscSchedIdAsc(): void
    {
        $schedId1 = $this->insertScheduleRow(2090, '2090-06-20', 1, 90, 2, 85);
        $schedId2 = $this->insertScheduleRow(2090, '2090-06-15', 3, 90, 4, 85);

        $results = $this->repo->getAllGamesWithBoxScoreInfo();

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
        // June 15 before June 20
        self::assertLessThan($pos1, $pos2);
    }

    public function testGetTeamRecordsKeyedByIntTid(): void
    {
        $records = $this->repo->getTeamRecords();

        self::assertIsArray($records);
        foreach ($records as $teamid => $record) {
            self::assertIsInt($teamid);
            self::assertIsString($record);
            break;
        }
    }

    public function testGetTeamRecordsContainsAllSeededTeams(): void
    {
        $records = $this->repo->getTeamRecords();

        // CI seed has 28 real teams
        self::assertGreaterThanOrEqual(28, count($records));
    }

    public function testGetTeamRecordsReflectsSeededStandings(): void
    {
        $records = $this->repo->getTeamRecords();

        // Seed standings have team 1 (Metros) — verify it exists and has a record string
        self::assertArrayHasKey(1, $records);
        self::assertMatchesRegularExpression('/^\d+-\d+$/', $records[1]);
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
