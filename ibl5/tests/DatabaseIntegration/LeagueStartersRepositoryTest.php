<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use LeagueStarters\LeagueStartersRepository;

/**
 * Tests LeagueStartersRepository against real MariaDB — starter queries
 * filtering by position depth and joining team data.
 */
class LeagueStartersRepositoryTest extends DatabaseTestCase
{
    private LeagueStartersRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new LeagueStartersRepository($this->db);
    }

    public function testGetAllStartersReturnsStarterPlayers(): void
    {
        // Insert a starter (pg_depth=1)
        $this->insertTestPlayer(200140001, 'Starter PG', ['teamid' => 1, 'pg_depth' => 1]);

        $starters = $this->repo->getAllStartersWithTeamData();

        $pids = array_column($starters, 'pid');
        self::assertContains(200140001, $pids);
    }

    public function testGetAllStartersExcludesNonStarters(): void
    {
        // Insert a starter to guarantee non-empty results
        $this->insertTestPlayer(200140004, 'Starter Guard', ['teamid' => 1, 'pg_depth' => 1]);
        // Insert a non-starter (all depths = 0)
        $this->insertTestPlayer(200140002, 'Bench Player', [
            'teamid' => 1, 'pg_depth' => 0, 'sg_depth' => 0, 'sf_depth' => 0, 'pf_depth' => 0, 'c_depth' => 0,
        ]);

        $starters = $this->repo->getAllStartersWithTeamData();

        self::assertNotEmpty($starters, 'Expected at least one starter');
        $pids = array_column($starters, 'pid');
        self::assertContains(200140004, $pids, 'Starter should be included');
        self::assertNotContains(200140002, $pids, 'Non-starter should be excluded');
    }

    public function testGetAllStartersIncludesTeamData(): void
    {
        $this->insertTestPlayer(200140003, 'Starter SG', ['teamid' => 1, 'sg_depth' => 1]);

        $starters = $this->repo->getAllStartersWithTeamData();

        self::assertNotEmpty($starters);
        $first = $starters[0];
        self::assertArrayHasKey('teamname', $first);
        self::assertArrayHasKey('color1', $first);
        self::assertArrayHasKey('color2', $first);
    }
}
