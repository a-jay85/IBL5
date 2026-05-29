<?php

declare(strict_types=1);

namespace Tests\SeriesRecords;

use PHPUnit\Framework\TestCase;
use SeriesRecords\SeriesRecordsController;
use SeriesRecords\Contracts\SeriesRecordsControllerInterface;
use Repositories\Contracts\TeamIdentityRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * SeriesRecordsControllerTest - Tests for SeriesRecordsController
 */
class SeriesRecordsControllerTest extends TestCase
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
        $controller = new SeriesRecordsController($this->mockDb, $this->createStub(TeamIdentityRepositoryInterface::class));

        $this->assertInstanceOf(SeriesRecordsController::class, $controller);
    }

    public function testImplementsInterface(): void
    {
        $controller = new SeriesRecordsController($this->mockDb, $this->createStub(TeamIdentityRepositoryInterface::class));

        $this->assertInstanceOf(SeriesRecordsControllerInterface::class, $controller);
    }


}
