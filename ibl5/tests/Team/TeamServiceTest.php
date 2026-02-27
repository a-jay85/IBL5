<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Team\TeamService;
use Team\Contracts\TeamServiceInterface;

/**
 * Tests for TeamService
 *
 * Validates data orchestration logic
 */
class TeamServiceTest extends TestCase
{
    private TeamService $service;

    protected function setUp(): void
    {
        $mockDb = new \MockDatabase();
        $repository = new \Team\TeamRepository($mockDb);
        $this->service = new TeamService($mockDb, $repository);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(TeamServiceInterface::class, $this->service);
    }
}
