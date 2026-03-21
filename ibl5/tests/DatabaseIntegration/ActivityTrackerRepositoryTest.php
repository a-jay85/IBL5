<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use ActivityTracker\ActivityTrackerRepository;

/**
 * Tests ActivityTrackerRepository against real MariaDB — team activity
 * listings with depth chart and voting timestamps.
 */
class ActivityTrackerRepositoryTest extends DatabaseTestCase
{
    private ActivityTrackerRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new ActivityTrackerRepository($this->db);
    }

    public function testGetTeamActivityReturns28Teams(): void
    {
        $teams = $this->repo->getTeamActivity();

        self::assertCount(28, $teams);
    }

    public function testGetTeamActivityIncludesActivityFields(): void
    {
        $teams = $this->repo->getTeamActivity();

        self::assertNotEmpty($teams);
        $first = $teams[0];
        self::assertArrayHasKey('teamid', $first);
        self::assertArrayHasKey('team_name', $first);
        self::assertArrayHasKey('team_city', $first);
        self::assertArrayHasKey('color1', $first);
        self::assertArrayHasKey('color2', $first);
        self::assertArrayHasKey('depth', $first);
        self::assertArrayHasKey('sim_depth', $first);
        self::assertArrayHasKey('asg_vote', $first);
        self::assertArrayHasKey('eoy_vote', $first);
    }
}
