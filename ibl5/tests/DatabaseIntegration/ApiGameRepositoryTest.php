<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Api\Pagination\Paginator;
use Api\Repository\ApiGameRepository;

/**
 * Tests ApiGameRepository against real MariaDB —
 * game listing via vw_schedule_upcoming view and box score queries
 * from ibl_box_scores / ibl_box_scores_teams.
 * CI seed has schedule data and 28 teams.
 */
class ApiGameRepositoryTest extends DatabaseTestCase
{
    private ApiGameRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ApiGameRepository($this->db);
    }

    // ── getGames ────────────────────────────────────────────────

    public function testGetGamesReturnsResults(): void
    {
        // Insert a schedule row to ensure we have data
        $this->insertScheduleRow(2025, '2025-01-15', 1, 0, 2, 0);

        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '10'],
            'game_date',
            ['game_date', 'season_year'],
        );

        $games = $this->repo->getGames($paginator);

        self::assertNotEmpty($games);
    }

    public function testGetGamesRowHasExpectedStructure(): void
    {
        $this->insertScheduleRow(2025, '2025-02-20', 1, 100, 2, 95);

        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '5'],
            'game_date',
            ['game_date'],
        );

        $games = $this->repo->getGames($paginator);

        self::assertNotEmpty($games);
        $game = $games[0];

        self::assertArrayHasKey('game_uuid', $game);
        self::assertArrayHasKey('season_year', $game);
        self::assertArrayHasKey('game_date', $game);
        self::assertArrayHasKey('game_status', $game);
        self::assertArrayHasKey('visitor_name', $game);
        self::assertArrayHasKey('home_name', $game);
        self::assertArrayHasKey('visitor_score', $game);
        self::assertArrayHasKey('home_score', $game);
    }

    public function testGetGamesFilterByStatus(): void
    {
        // Insert scheduled (0-0) and completed (scored) games
        $this->insertScheduleRow(2025, '2025-03-01', 1, 0, 2, 0);
        $this->insertScheduleRow(2025, '2025-03-02', 3, 105, 4, 98);

        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '100'],
            'game_date',
            ['game_date'],
        );

        $scheduled = $this->repo->getGames($paginator, ['status' => 'scheduled']);

        foreach ($scheduled as $game) {
            self::assertSame('scheduled', $game['game_status']);
        }
    }

    public function testGetGamesFilterBySeason(): void
    {
        $this->insertScheduleRow(2025, '2025-04-01', 1, 0, 2, 0);

        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '100'],
            'game_date',
            ['game_date'],
        );

        $games = $this->repo->getGames($paginator, ['season' => '2025']);

        foreach ($games as $game) {
            self::assertSame(2025, $game['season_year']);
        }
    }

    public function testGetGamesFilterByDateRange(): void
    {
        $this->insertScheduleRow(2025, '2025-05-10', 1, 0, 2, 0);
        $this->insertScheduleRow(2025, '2025-05-15', 3, 0, 4, 0);
        $this->insertScheduleRow(2025, '2025-05-20', 5, 0, 6, 0);

        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '100'],
            'game_date',
            ['game_date'],
        );

        $games = $this->repo->getGames($paginator, [
            'date_start' => '2025-05-10',
            'date_end' => '2025-05-15',
        ]);

        foreach ($games as $game) {
            self::assertGreaterThanOrEqual('2025-05-10', $game['game_date']);
            self::assertLessThanOrEqual('2025-05-15', $game['game_date']);
        }
    }

    // ── countGames ──────────────────────────────────────────────

    public function testCountGamesReturnsPositiveCount(): void
    {
        $this->insertScheduleRow(2025, '2025-06-01', 1, 0, 2, 0);

        $count = $this->repo->countGames();

        self::assertGreaterThan(0, $count);
    }

    public function testCountGamesWithStatusFilter(): void
    {
        $this->insertScheduleRow(2025, '2025-07-01', 1, 0, 2, 0);

        $allCount = $this->repo->countGames();
        $scheduledCount = $this->repo->countGames(['status' => 'scheduled']);

        self::assertLessThanOrEqual($allCount, $scheduledCount);
    }

    // ── getGameByUuid ───────────────────────────────────────────

    public function testGetGameByUuidReturnsGame(): void
    {
        $schedId = $this->insertScheduleRow(2025, '2025-08-01', 1, 90, 2, 85);

        // Look up the uuid from the inserted row
        $stmt = $this->db->prepare('SELECT uuid FROM ibl_schedule WHERE SchedID = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $schedId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row);
        $uuid = $row['uuid'];
        self::assertIsString($uuid);

        $game = $this->repo->getGameByUuid($uuid);

        self::assertNotNull($game);
        self::assertSame($uuid, $game['game_uuid']);
        self::assertSame(2025, $game['season_year']);
        self::assertSame('completed', $game['game_status']);
    }

    public function testGetGameByUuidReturnsNullForUnknownUuid(): void
    {
        $result = $this->repo->getGameByUuid('nonexistent-game-uuid');

        self::assertNull($result);
    }

    // ── getBoxscoreTeams ────────────────────────────────────────

    public function testGetBoxscoreTeamsReturnsTeamStats(): void
    {
        $date = '2025-09-01';
        $this->insertTeamBoxscoreRow($date, 'Game Name', 1, 1, 2);

        $result = $this->repo->getBoxscoreTeams(1, 2, $date);

        self::assertNotEmpty($result);
        $row = $result[0];

        self::assertArrayHasKey('visitorQ1points', $row);
        self::assertArrayHasKey('homeQ1points', $row);
        self::assertArrayHasKey('attendance', $row);
        self::assertArrayHasKey('capacity', $row);
    }

    public function testGetBoxscoreTeamsReturnsEmptyForNoMatch(): void
    {
        $result = $this->repo->getBoxscoreTeams(999, 998, '2099-01-01');

        self::assertSame([], $result);
    }

    // ── getBoxscorePlayers ──────────────────────────────────────

    public function testGetBoxscorePlayersReturnsPlayerLines(): void
    {
        $date = '2025-10-01';
        $this->insertTestPlayer(200000095, 'DB BoxPlr Batch7', ['tid' => 1]);
        $this->insertPlayerBoxscoreRow($date, 200000095, 'DB BoxPlr Batch7', 'PG', 1, 2, 1);

        $result = $this->repo->getBoxscorePlayers(1, 2, $date);

        self::assertNotEmpty($result);
        $row = $result[0];

        self::assertArrayHasKey('name', $row);
        self::assertArrayHasKey('pos', $row);
        self::assertArrayHasKey('gameMIN', $row);
        self::assertArrayHasKey('game2GM', $row);
        self::assertArrayHasKey('player_uuid', $row);
    }

    public function testGetBoxscorePlayersReturnsEmptyForNoMatch(): void
    {
        $result = $this->repo->getBoxscorePlayers(999, 998, '2099-01-01');

        self::assertSame([], $result);
    }
}
