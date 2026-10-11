<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use FranchiseRecordBook\FranchiseRecordBookRepository;
use League\League;

#[Group('database')]
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

        self::assertNotSame([], $result);
        $first = $result[0];
        self::assertSame('team', $first['scope']);
        self::assertSame(1, $first['teamid']);
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

        self::assertNotSame([], $result);
        $first = $result[0];
        self::assertSame('league', $first['scope']);
        self::assertSame('career', $first['record_type']);
    }

    public function testGetLeagueSingleSeasonRecordsReturnsRows(): void
    {
        $result = $this->repo->getLeagueSingleSeasonRecords();

        self::assertNotSame([], $result);
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

    /**
     * @return array{int, int}
     */
    private function insertCareerRankingTie(): array
    {
        $base = [
            'scope' => 'league',
            'record_type' => 'career',
            'stat_category' => 'ppg',
            'ranking' => 60,
            'stat_value' => 30.5,
            'stat_raw' => 305,
        ];
        $idA = $this->insertRow('ibl_rcb_alltime_records', $base + ['teamid' => 27, 'player_name' => 'Tie Career A']);
        $idB = $this->insertRow('ibl_rcb_alltime_records', $base + ['teamid' => 28, 'player_name' => 'Tie Career B']);
        self::assertLessThan($idB, $idA, 'alltime record ids must be strictly ascending');

        return [$idA, $idB];
    }

    public function testGetLeagueCareerRecordsBreaksCrossTeamRankingTieById(): void
    {
        [$idA, $idB] = $this->insertCareerRankingTie();

        $rows = array_values(array_filter(
            $this->repo->getLeagueCareerRecords(60),
            static fn (array $row): bool => $row['stat_category'] === 'ppg' && $row['ranking'] === 60,
        ));

        self::assertCount(2, $rows);
        self::assertSame([$idA, $idB], [$rows[0]['id'], $rows[1]['id']]);
        self::assertSame(['Tie Career A', 'Tie Career B'], [$rows[0]['player_name'], $rows[1]['player_name']]);
    }

    public function testGetLeagueCareerRecordsLimitExcludesTieRowsAboveLimit(): void
    {
        $this->insertCareerRankingTie();

        foreach ($this->repo->getLeagueCareerRecords(59) as $row) {
            self::assertNotSame(60, $row['ranking']);
        }
    }
}
