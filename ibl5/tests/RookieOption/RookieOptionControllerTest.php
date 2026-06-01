<?php

declare(strict_types=1);

namespace Tests\RookieOption;

use PHPUnit\Framework\TestCase;
use RookieOption\RookieOptionController;
use RookieOption\Contracts\RookieOptionControllerInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * RookieOptionControllerTest - Tests for RookieOptionController
 */
class RookieOptionControllerTest extends TestCase
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
        $controller = new RookieOptionController($this->mockDb, self::createStub(TeamIdentityRepositoryInterface::class));

        $this->assertInstanceOf(RookieOptionController::class, $controller);
    }

    public function testImplementsInterface(): void
    {
        $controller = new RookieOptionController($this->mockDb, self::createStub(TeamIdentityRepositoryInterface::class));

        $this->assertInstanceOf(RookieOptionControllerInterface::class, $controller);
    }

}
