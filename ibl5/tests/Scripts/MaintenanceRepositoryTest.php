<?php

declare(strict_types=1);

namespace Tests\Scripts;

use Scripts\MaintenanceRepository;
use Scripts\Contracts\MaintenanceRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\MockDatabase;

/**
 * @covers \Scripts\MaintenanceRepository
 */
class MaintenanceRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;
    private MaintenanceRepository $repository;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->repository = new MaintenanceRepository($this->mockDb);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(MaintenanceRepositoryInterface::class, $this->repository);
    }

    public function testGetAllTeamsReturnsArray(): void
    {
        $this->mockDb->setMockData([
            ['team_name' => 'Boston'],
            ['team_name' => 'Miami'],
        ]);

        $result = $this->repository->getAllTeams();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Boston', $result[0]['team_name']);
    }

    public function testGetAllTeamsExcludesFreeAgents(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getAllTeams();

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString('teamid BETWEEN 1 AND', $queries[0]);
    }

    public function testGetTeamRecentCompleteSeasonsReturnsSeasons(): void
    {
        $this->mockDb->setMockData([
            ['wins' => 50, 'losses' => 32],
            ['wins' => 45, 'losses' => 37],
        ]);

        $result = $this->repository->getTeamRecentCompleteSeasons('Boston');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(50, $result[0]['wins']);
    }

    public function testGetTeamRecentCompleteSeasonsUsesLimit(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getTeamRecentCompleteSeasons('Boston', 3);

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString('LIMIT', $queries[0]);
    }

    public function testUpdateTeamTraditionExecutesUpdate(): void
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->updateTeamTradition('Boston', 47, 35);

        $this->assertTrue($result);
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString('UPDATE ibl_team_info', $queries[0]);
        $this->assertStringContainsString('Contract_AvgW', $queries[0]);
        $this->assertStringContainsString('Contract_AvgL', $queries[0]);
    }

    public function testGetSettingReturnsValue(): void
    {
        $this->mockDb->setMockData([
            ['value' => 'IBL5'],
        ]);

        $result = $this->repository->getSetting('League File Name');

        $this->assertEquals('IBL5', $result);
    }

    public function testGetSettingReturnsNullWhenNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getSetting('Nonexistent Setting');

        $this->assertNull($result);
    }
}
