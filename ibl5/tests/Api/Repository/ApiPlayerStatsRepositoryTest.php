<?php

declare(strict_types=1);

namespace Tests\Api\Repository;

use Api\Repository\ApiPlayerStatsRepository;
use Tests\WideUnit\WideUnitTestCase;

class ApiPlayerStatsRepositoryTest extends WideUnitTestCase
{
    private ApiPlayerStatsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ApiPlayerStatsRepository($this->mockDb);
    }

    public function testGetCareerStatsReturnsRowForKnownUuid(): void
    {
        $this->mockDb->setMockData([
            ['player_uuid' => 'uuid-abc', 'pid' => 42, 'name' => 'Jane Smith', 'career_games' => 120],
        ]);

        $result = $this->repository->getCareerStats('uuid-abc');

        self::assertIsArray($result);
        self::assertSame('Jane Smith', $result['name']);
        self::assertSame(120, $result['career_games']);
    }

    public function testGetCareerStatsReturnsNullWhenPlayerNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getCareerStats('uuid-unknown');

        self::assertNull($result);
    }

    public function testGetCareerStatsQueriesCareerStatsView(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getCareerStats('uuid-test');

        $this->assertQueryExecuted('vw_player_career_stats');
    }

    public function testGetSeasonHistoryReturnsRows(): void
    {
        $this->mockDb->setMockData([
            ['player_uuid' => 'uuid-abc', 'pid' => 42, 'year' => 2025, 'games' => 82, 'pts' => 2050],
            ['player_uuid' => 'uuid-abc', 'pid' => 42, 'year' => 2024, 'games' => 75, 'pts' => 1800],
        ]);

        $result = $this->repository->getSeasonHistory('uuid-abc');

        self::assertCount(2, $result);
        self::assertSame(2025, $result[0]['year']);
    }

    public function testGetSeasonHistoryReturnsEmptyWhenNone(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getSeasonHistory('uuid-no-history');

        self::assertSame([], $result);
    }

    public function testGetSeasonHistoryQueriesHistTable(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getSeasonHistory('uuid-test');

        $this->assertQueryExecuted('ibl_hist');
    }
}
