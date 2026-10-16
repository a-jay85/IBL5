<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use Api\Pagination\Paginator;
use Api\Repository\ApiGameRepository;

/**
 * Tests ApiGameRepository against real MariaDB —
 * game listing via vw_schedule_upcoming view and box score queries
 * from ibl_box_scores / ibl_box_scores_teams.
 * CI seed has schedule data and 28 teams.
 */
#[Group('database')]
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

        self::assertNotSame([], $games);
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

        self::assertNotSame([], $games);
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

    public function testGetGamesWithAllFiltersReturnsOnlyMatchingRows(): void
    {
        $teamUuid = $this->fetchScalarString('SELECT uuid AS v FROM `ibl_team_info` WHERE teamid = 5');

        // Matches: team 5 visiting and team 5 hosting, completed, 2097-03-10.
        $this->insertScheduleRow(2097, '2097-03-10', 5, 90, 6, 80, 0, ['uuid' => 'apigr-visit']);
        $this->insertScheduleRow(2097, '2097-03-10', 10, 70, 5, 75, 0, ['uuid' => 'apigr-home']);
        // Each of these misses exactly one filter.
        $this->insertScheduleRow(2097, '2097-03-10', 7, 90, 8, 80, 0, ['uuid' => 'apigr-team']);
        $this->insertScheduleRow(2097, '2097-03-10', 5, 0, 9, 0, 0, ['uuid' => 'apigr-status']);
        $this->insertScheduleRow(2097, '2097-04-10', 6, 90, 5, 80, 0, ['uuid' => 'apigr-date']);
        $this->insertScheduleRow(2096, '2096-03-10', 5, 90, 6, 80, 0, ['uuid' => 'apigr-season']);

        $filters = [
            'season' => '2097',
            'status' => 'completed',
            'team' => $teamUuid,
            'date' => '2097-03-10',
            'date_start' => '2097-03-01',
            'date_end' => '2097-03-31',
        ];
        $paginator = new Paginator(['page' => '1', 'per_page' => '100'], 'game_date', ['game_date']);

        $games = $this->repo->getGames($paginator, $filters);

        $uuids = array_column($games, 'game_uuid');
        sort($uuids);
        self::assertSame(['apigr-home', 'apigr-visit'], $uuids);

        // Independent count straight from the base table.
        $expected = $this->fetchScalarString(
            "SELECT COUNT(*) AS v FROM `ibl_schedule`
             WHERE season_year = 2097 AND game_date = '2097-03-10'
               AND (visitor_teamid = 5 OR home_teamid = 5)
               AND NOT (visitor_score = 0 AND home_score = 0)
               AND uuid LIKE 'apigr-%'"
        );
        self::assertSame(2, (int) $expected);
        self::assertSame((int) $expected, $this->repo->countGames($filters));
    }

    public function testGetGamesWithNoFiltersMatchesCountGames(): void
    {
        $this->insertScheduleRow(2097, '2097-05-01', 1, 0, 2, 0, 0, ['uuid' => 'apigr-nf-a']);
        $this->insertScheduleRow(2097, '2097-05-02', 3, 100, 4, 90, 0, ['uuid' => 'apigr-nf-b']);
        $this->insertScheduleRow(2097, '2097-05-03', 5, 80, 6, 85, 0, ['uuid' => 'apigr-nf-c']);

        $total = $this->repo->countGames();
        $paginator = new Paginator(['page' => '1', 'per_page' => '100', 'order' => 'desc'], 'game_date', ['game_date']);

        $games = $this->repo->getGames($paginator);

        // Independent count straight from the view.
        self::assertSame((int) $this->fetchScalarString('SELECT COUNT(*) AS v FROM `vw_schedule_upcoming`'), $total);
        self::assertCount(min($total, 100), $games);
        self::assertSame(['apigr-nf-c', 'apigr-nf-b', 'apigr-nf-a'], array_slice(array_column($games, 'game_uuid'), 0, 3));
    }

    private function fetchScalarString(string $sql): string
    {
        $result = $this->db->query($sql);
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);
        self::assertIsScalar($row['v']);

        return (string) $row['v'];
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
        $stmt = $this->db->prepare('SELECT uuid FROM ibl_schedule WHERE id = ?');
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

        self::assertNotSame([], $result);
        $row = $result[0];

        self::assertArrayHasKey('visitor_q1_points', $row);
        self::assertArrayHasKey('home_q1_points', $row);
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
        $this->insertTestPlayer(200000095, 'DB BoxPlr Batch7', ['teamid' => 1]);
        $this->insertPlayerBoxscoreRow($date, 200000095, 'DB BoxPlr Batch7', 'PG', 1, 2, 1);

        $result = $this->repo->getBoxscorePlayers(1, 2, $date);

        self::assertNotSame([], $result);
        $row = $result[0];

        self::assertArrayHasKey('name', $row);
        self::assertArrayHasKey('pos', $row);
        self::assertArrayHasKey('game_min', $row);
        self::assertArrayHasKey('game_2gm', $row);
        self::assertArrayHasKey('player_uuid', $row);
    }

    public function testGetBoxscorePlayersReturnsEmptyForNoMatch(): void
    {
        $result = $this->repo->getBoxscorePlayers(999, 998, '2099-01-01');

        self::assertSame([], $result);
    }
}
