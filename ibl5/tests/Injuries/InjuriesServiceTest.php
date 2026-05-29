<?php

declare(strict_types=1);

namespace Tests\Injuries;

use PHPUnit\Framework\TestCase;
use Injuries\InjuriesService;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * InjuriesServiceTest - Tests for InjuriesService
 */
class InjuriesServiceTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testServiceCanBeInstantiated(): void
    {
        $service = new InjuriesService($this->mockDb);

        $this->assertInstanceOf(InjuriesService::class, $service);
    }

    public function testServiceImplementsCorrectInterface(): void
    {
        $service = new InjuriesService($this->mockDb);

        $this->assertInstanceOf(
            \Injuries\Contracts\InjuriesServiceInterface::class,
            $service
        );
    }

    // ============================================
    // GET INJURED PLAYERS TESTS
    // ============================================

    public function testGetInjuredPlayersWithTeamsReturnsEmptyArrayWhenNoInjuries(): void
    {
        $this->mockDb->setMockData([]);
        $service = new InjuriesService($this->mockDb);

        $result = $service->getInjuredPlayersWithTeams();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleServicesCanBeInstantiated(): void
    {
        $service1 = new InjuriesService($this->mockDb);
        $service2 = new InjuriesService($this->mockDb);

        $this->assertNotSame($service1, $service2);
    }
}
