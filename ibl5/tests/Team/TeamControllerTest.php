<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Team\TeamController;
use Team\Contracts\TeamControllerInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * TeamControllerTest - Tests for TeamController
 */
class TeamControllerTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $controller = new TeamController($this->mockDb, $this->createStub(TeamIdentityRepositoryInterface::class), $this->createStub(\Auth\AuthService::class), $this->createStub(\League\LeagueContext::class));

        $this->assertInstanceOf(TeamController::class, $controller);
    }

    public function testImplementsInterface(): void
    {
        $controller = new TeamController($this->mockDb, $this->createStub(TeamIdentityRepositoryInterface::class), $this->createStub(\Auth\AuthService::class), $this->createStub(\League\LeagueContext::class));

        $this->assertInstanceOf(TeamControllerInterface::class, $controller);
    }

}
