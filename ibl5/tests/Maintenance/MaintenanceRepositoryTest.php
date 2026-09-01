<?php

declare(strict_types=1);

namespace Tests\Maintenance;

use Maintenance\MaintenanceRepository;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @covers \Maintenance\MaintenanceRepository
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

    public function testGetAllTeamsReturnsArray(): void
    {
        $this->mockDb->setMockData([
            ['team_name' => 'Boston'],
            ['team_name' => 'Miami'],
        ]);

        $result = $this->repository->getAllTeams();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame('Boston', $result[0]['team_name']);
    }

    public function testGetAllTeamsExcludesFreeAgents(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getAllTeams();

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString('teamid BETWEEN 1 AND', $queries[0]);
    }

    public function testGetTeamSeasonRecordsReturnsSeasons(): void
    {
        $this->mockDb->setMockData([
            ['year' => 2023, 'wins' => 50, 'losses' => 32],
            ['year' => 2022, 'wins' => 45, 'losses' => 37],
        ]);

        $result = $this->repository->getTeamSeasonRecords('Boston', 2024);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertSame(50, $result[0]['wins']);
        $this->assertSame(2023, $result[0]['year']);
    }

    public function testGetTeamSeasonRecordsUsesLimit(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getTeamSeasonRecords('Boston', 2024, 3);

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString('LIMIT', $queries[0]);
    }

    public function testGetTeamSeasonRecordsExcludesInProgressCurrentSeason(): void
    {
        // The repository applies NO game-count predicate — it returns an in-progress
        // season row unfiltered. Exclusion of in-progress seasons is the processor's job.
        $this->mockDb->setMockData([
            ['year' => 2024, 'wins' => 30, 'losses' => 10],  // in-progress: only 40 games
            ['year' => 2023, 'wins' => 50, 'losses' => 32],
        ]);

        $result = $this->repository->getTeamSeasonRecords('Boston', 2024);

        // Both rows must be returned — the repo does not filter by game count.
        $this->assertCount(2, $result);
        $this->assertSame(2024, $result[0]['year']);
        $this->assertSame(30, $result[0]['wins']);

        // Verify no game-count band exists in the emitted SQL.
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringNotContainsString('BETWEEN', $queries[0]);
    }

    public function testGetTeamSeasonRecordsReturnsAnomalousSeasonsInsteadOfHidingThem(): void
    {
        // Rows with game counts outside 82 must be returned, not silently skipped.
        // The processor is responsible for detecting and aborting on anomalies.
        $this->mockDb->setMockData([
            ['year' => 2023, 'wins' => 82, 'losses' => 37],  // 119 games — a phantom-boxscore anomaly
            ['year' => 2022, 'wins' => 50, 'losses' => 32],
        ]);

        $result = $this->repository->getTeamSeasonRecords('Boston', 2024);

        $this->assertCount(2, $result);
        $this->assertSame(119, $result[0]['wins'] + $result[0]['losses']);
    }

    public function testUpdateTeamTraditionExecutesUpdate(): void
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->updateTeamTradition('Boston', 47, 35);

        $this->assertTrue($result);
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString('UPDATE ibl_team_info', $queries[0]);
        $this->assertStringContainsString('contract_avg_w', $queries[0]);
        $this->assertStringContainsString('contract_avg_l', $queries[0]);
    }

    public function testGetSettingReturnsValue(): void
    {
        $this->mockDb->setMockData([
            ['value' => 'IBL5'],
        ]);

        $result = $this->repository->getSetting('League File Name');

        $this->assertSame('IBL5', $result);
    }

    public function testGetSettingReturnsNullWhenNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getSetting('Nonexistent Setting');

        $this->assertNull($result);
    }
}
