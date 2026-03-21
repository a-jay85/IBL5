<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Api\Pagination\Paginator;
use Api\Repository\ApiTeamRepository;

/**
 * Tests ApiTeamRepository against real MariaDB —
 * team listing, counting, and UUID lookup via ibl_team_info + ibl_standings.
 * CI seed has 28 real teams.
 */
class ApiTeamRepositoryTest extends DatabaseTestCase
{
    private ApiTeamRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ApiTeamRepository($this->db);
    }

    // ── getTeams ────────────────────────────────────────────────

    public function testGetTeamsReturnsTeamList(): void
    {
        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '100'],
            'team_name',
            ['team_name', 'team_city', 'teamid'],
        );

        $teams = $this->repo->getTeams($paginator);

        self::assertCount(28, $teams);
    }

    public function testGetTeamsRowHasExpectedStructure(): void
    {
        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '5'],
            'team_name',
            ['team_name'],
        );

        $teams = $this->repo->getTeams($paginator);

        self::assertNotEmpty($teams);
        $team = $teams[0];

        self::assertArrayHasKey('teamid', $team);
        self::assertArrayHasKey('uuid', $team);
        self::assertArrayHasKey('team_city', $team);
        self::assertArrayHasKey('team_name', $team);
        self::assertArrayHasKey('owner_name', $team);
        self::assertArrayHasKey('arena', $team);
        self::assertArrayHasKey('conference', $team);
        self::assertArrayHasKey('division', $team);
    }

    public function testGetTeamsPaginatesCorrectly(): void
    {
        $page1 = new Paginator(
            ['page' => '1', 'per_page' => '10'],
            'teamid',
            ['teamid'],
        );
        $page2 = new Paginator(
            ['page' => '2', 'per_page' => '10'],
            'teamid',
            ['teamid'],
        );

        $teams1 = $this->repo->getTeams($page1);
        $teams2 = $this->repo->getTeams($page2);

        self::assertCount(10, $teams1);
        self::assertCount(10, $teams2);

        // Pages should not overlap
        $ids1 = array_column($teams1, 'teamid');
        $ids2 = array_column($teams2, 'teamid');
        self::assertEmpty(array_intersect($ids1, $ids2));
    }

    // ── countTeams ──────────────────────────────────────────────

    public function testCountTeamsReturns28(): void
    {
        $count = $this->repo->countTeams();

        self::assertSame(28, $count);
    }

    // ── getTeamByUuid ───────────────────────────────────────────

    public function testGetTeamByUuidReturnsTeamWithStandings(): void
    {
        // Get a known UUID from the first team
        $paginator = new Paginator(
            ['page' => '1', 'per_page' => '1'],
            'teamid',
            ['teamid'],
        );
        $teams = $this->repo->getTeams($paginator);
        self::assertNotEmpty($teams);
        $uuid = $teams[0]['uuid'];
        self::assertIsString($uuid);

        $team = $this->repo->getTeamByUuid($uuid);

        self::assertNotNull($team);
        self::assertSame($uuid, $team['uuid']);
        self::assertArrayHasKey('league_record', $team);
        self::assertArrayHasKey('win_percentage', $team);
        self::assertArrayHasKey('home_wins', $team);
        self::assertArrayHasKey('home_losses', $team);
        self::assertArrayHasKey('away_wins', $team);
        self::assertArrayHasKey('away_losses', $team);
        self::assertArrayHasKey('games_remaining', $team);
    }

    public function testGetTeamByUuidReturnsNullForUnknownUuid(): void
    {
        $result = $this->repo->getTeamByUuid('nonexistent-uuid-value');

        self::assertNull($result);
    }
}
