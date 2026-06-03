<?php

declare(strict_types=1);

namespace Tests\Injuries;

use PHPUnit\Framework\TestCase;
use Injuries\Contracts\InjuriesRepositoryInterface;
use Injuries\InjuriesService;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;

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
    // CONSTRUCTOR / DEFAULT-CONSTRUCTION CHARACTERIZATION
    // ============================================

    /**
     * Characterization: default (no injected repository) construction keeps the
     * prior League-backed behavior — an empty injured-players query yields [].
     */
    public function testGetInjuredPlayersWithTeamsReturnsEmptyArrayWhenNoInjuries(): void
    {
        $this->mockDb->setMockData([]);
        $service = new InjuriesService($this->mockDb);

        $result = $service->getInjuredPlayersWithTeams();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ============================================
    // INJECTED REPOSITORY TESTS
    // ============================================

    /**
     * Boundary: an injected repository that reports no injuries yields [] without
     * touching the database for player/team hydration.
     */
    public function testGetInjuredPlayersWithTeamsReturnsEmptyWhenInjectedRepoIsEmpty(): void
    {
        $repository = $this->createMock(InjuriesRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getInjuredPlayers')
            ->willReturn([]);

        $service = new InjuriesService($this->mockDb, $repository);

        $result = $service->getInjuredPlayersWithTeams();

        $this->assertSame([], $result);
    }

    /**
     * The injected repository is the source of injured rows; each row is mapped
     * into the player/team display shape.
     */
    public function testGetInjuredPlayersWithTeamsMapsRowsFromInjectedRepository(): void
    {
        $injuredRow = TestDataFactory::createPlayer([
            'pid' => 7,
            'name' => 'Injured Star',
            'pos' => 'PG',
            'teamid' => 3,
            'injured' => 10,
            'retired' => 0,
        ]);

        $repository = $this->createMock(InjuriesRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('getInjuredPlayers')
            ->willReturn([$injuredRow]);

        // Team::initialize() resolves the player's team from ibl_team_info.
        $this->mockDb->onQuery('ibl_team_info', [
            TestDataFactory::createTeam([
                'teamid' => 3,
                'team_name' => 'Bulls',
                'team_city' => 'Chicago',
                'color1' => 'CE1141',
                'color2' => '000000',
            ]),
        ]);

        $service = new InjuriesService($this->mockDb, $repository);

        $result = $service->getInjuredPlayersWithTeams();

        $this->assertCount(1, $result);
        $this->assertSame(7, $result[0]['playerID']);
        $this->assertSame('Injured Star', $result[0]['name']);
        $this->assertSame('PG', $result[0]['position']);
        $this->assertSame('Chicago', $result[0]['teamCity']);
        $this->assertSame('Bulls', $result[0]['teamName']);
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
