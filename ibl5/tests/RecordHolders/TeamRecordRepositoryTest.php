<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use League\League;
use PHPUnit\Framework\Attributes\DataProvider;
use RecordHolders\TeamRecordRepository;
use Tests\WideUnit\WideUnitTestCase;

/**
 * Mock-DB tests for TeamRecordRepository. The real-schema tests live in
 * Tests\DatabaseIntegration\TeamRecordRepositoryTest.
 */
final class TeamRecordRepositoryTest extends WideUnitTestCase
{
    private TeamRecordRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suppressErrorLog();
        $this->repository = new TeamRecordRepository($this->mockDb);
    }

    protected function tearDown(): void
    {
        unset($this->repository);
        parent::tearDown();
    }

    /**
     * Pins the whole SQL text, so a dropped or moved piece fails the test.
     * That covers the team-id cap, the zero-games filter, and both sort directions.
     */
    #[DataProvider('orderProvider')]
    public function testGetBestWorstSeasonRecordBuildsExactQuery(string $input, string $direction): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getBestWorstSeasonRecord($input);

        $queries = $this->getExecutedQueries();
        self::assertCount(1, $queries);
        $normalized = trim((string) preg_replace('/\s+/', ' ', $queries[0]));

        self::assertSame(
            'SELECT r.currentname AS team_name, r.year, r.wins, r.losses'
            . ' FROM ibl_team_season_records r'
            . ' JOIN ibl_team_info ti ON ti.teamid = r.team_id'
            . ' WHERE r.game_type = 1'
            . ' AND ti.teamid BETWEEN 1 AND ' . League::MAX_REAL_TEAMID
            . ' AND (r.wins + r.losses) > 0'
            . ' ORDER BY (r.wins / (r.wins + r.losses)) ' . $direction . ','
            . ' r.wins ' . $direction . ', r.year ASC, ti.teamid ASC'
            . ' LIMIT 5',
            $normalized,
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function orderProvider(): array
    {
        return [
            'desc' => ['DESC', 'DESC'],
            'asc' => ['ASC', 'ASC'],
            'unknown falls back to desc' => ['bogus', 'DESC'],
        ];
    }

    public function testGetBestWorstSeasonRecordMapsRows(): void
    {
        $this->mockDb->setMockData([
            ['team_name' => 'Metros', 'year' => 2098, 'wins' => 60, 'losses' => 22],
        ]);

        $result = $this->repository->getBestWorstSeasonRecord('DESC');

        self::assertSame(
            [['team_name' => 'Metros', 'year' => 2098, 'wins' => 60, 'losses' => 22]],
            $result,
        );
    }
}
