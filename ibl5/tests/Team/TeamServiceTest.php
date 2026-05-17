<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Team\TeamService;
use Team\Contracts\TeamServiceInterface;
use Tests\WideUnit\Mocks\MockDatabase;

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
        $mockDb = new MockDatabase();
        $repository = new \Team\TeamRepository($mockDb);
        $this->service = new TeamService($mockDb, $repository, $this->createStub(\League\LeagueContext::class));
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(TeamServiceInterface::class, $this->service);
    }
}
