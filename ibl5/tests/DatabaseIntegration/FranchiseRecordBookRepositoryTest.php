<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use FranchiseRecordBook\FranchiseRecordBookRepository;
use League\League;

class FranchiseRecordBookRepositoryTest extends DatabaseTestCase
{
    private FranchiseRecordBookRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FranchiseRecordBookRepository($this->db);
    }

    public function testGetTeamSingleSeasonRecordsReturnsRows(): void
    {
        $result = $this->repo->getTeamSingleSeasonRecords(1);

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertSame('team', $first['scope']);
        self::assertSame(1, $first['team_id']);
        self::assertSame('single_season', $first['record_type']);
        self::assertArrayHasKey('stat_category', $first);
        self::assertArrayHasKey('player_name', $first);
    }

    public function testGetTeamSingleSeasonRecordsRespectsLimit(): void
    {
        $result3 = $this->repo->getTeamSingleSeasonRecords(1, 3);
        $result10 = $this->repo->getTeamSingleSeasonRecords(1, 10);

        // With a limit of 3, should never return rankings > 3
        foreach ($result3 as $record) {
            self::assertLessThanOrEqual(3, $record['ranking']);
        }

        // Larger limit should return at least as many results
        self::assertGreaterThanOrEqual(count($result3), count($result10));
    }

    public function testGetLeagueCareerRecordsReturnsRows(): void
    {
        $result = $this->repo->getLeagueCareerRecords();

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertSame('league', $first['scope']);
        self::assertSame('career', $first['record_type']);
    }

    public function testGetLeagueSingleSeasonRecordsReturnsRows(): void
    {
        $result = $this->repo->getLeagueSingleSeasonRecords();

        self::assertNotEmpty($result);
        $first = $result[0];
        self::assertSame('league', $first['scope']);
        self::assertSame('single_season', $first['record_type']);
    }

    public function testGetAllTeamsReturnsOnlyRealTeams(): void
    {
        $result = $this->repo->getAllTeams();

        self::assertCount(28, $result);
        foreach ($result as $team) {
            self::assertArrayHasKey('teamid', $team);
            self::assertGreaterThanOrEqual(1, $team['teamid']);
            self::assertLessThanOrEqual(League::MAX_REAL_TEAMID, $team['teamid']);
        }
    }

    public function testGetTeamInfoReturnsRow(): void
    {
        $result = $this->repo->getTeamInfo(1);

        self::assertNotNull($result);
        self::assertSame(1, $result['teamid']);
        self::assertArrayHasKey('team_name', $result);
        self::assertArrayHasKey('color1', $result);
        self::assertArrayHasKey('color2', $result);
    }

    public function testGetTeamInfoReturnsNullForUnknown(): void
    {
        $result = $this->repo->getTeamInfo(9999);

        self::assertNull($result);
    }
}
