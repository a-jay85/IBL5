<?php

declare(strict_types=1);

namespace Tests\ProjectedDraftOrder;

use ProjectedDraftOrder\ProjectedDraftOrderRepository;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @covers \ProjectedDraftOrder\ProjectedDraftOrderRepository
 */
class ProjectedDraftOrderRepositoryTest extends TestCase
{
    private MockDatabase $db;
    private ProjectedDraftOrderRepository $repository;

    protected function setUp(): void
    {
        $this->db = new MockDatabase();
        $this->repository = new ProjectedDraftOrderRepository($this->db);
    }

    public function testGetAllTeamsWithStandingsReturnsArray(): void
    {
        $this->db->setMockData([
            [
                'teamid' => 1, 'team_name' => 'Heat', 'wins' => 50, 'losses' => 32,
                'pct' => 0.610, 'conference' => 'Eastern', 'division' => 'Atlantic',
                'conf_wins' => 30, 'conf_losses' => 12, 'div_wins' => 10, 'div_losses' => 4,
                'clinched_division' => 1, 'color1' => '98002E', 'color2' => 'F9A01B',
            ],
        ]);

        $result = $this->repository->getAllTeamsWithStandings();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('Heat', $result[0]['team_name']);
    }

    public function testGetAllTeamsWithStandingsReturnsEmptyArrayWhenNoData(): void
    {
        $this->db->setMockData([]);

        $result = $this->repository->getAllTeamsWithStandings();

        $this->assertSame([], $result);
    }

    public function testGetPlayedGamesReturnsArray(): void
    {
        $this->db->setMockData([
            ['visitor_teamid' => 1, 'visitor_score' => 105, 'home_teamid' => 2, 'home_score' => 98],
        ]);

        $result = $this->repository->getPlayedGames(2026);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['visitor_teamid']);
    }

    public function testGetPickOwnershipReturnsArray(): void
    {
        $this->db->setMockData([
            ['ownerofpick' => 'Heat', 'teampick' => 'Celtics', 'round' => 1, 'notes' => 'via trade'],
        ]);

        $result = $this->repository->getPickOwnership(2026);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame('Heat', $result[0]['ownerofpick']);
    }

    public function testGetPointDifferentialsReturnsArray(): void
    {
        $this->db->setMockData([
            ['teamid' => 1, 'pointsFor' => 8500.0, 'pointsAgainst' => 8200.0],
        ]);

        $result = $this->repository->getPointDifferentials(2026);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['teamid']);
    }

    public function testSaveFinalDraftOrderExecutesInsert(): void
    {
        $picks = [
            ['round' => 1, 'pick' => 1, 'team' => 'Heat', 'teamid' => 1],
            ['round' => 1, 'pick' => 2, 'team' => 'Celtics', 'teamid' => 2],
        ];

        $this->repository->saveFinalDraftOrder(2026, $picks);

        $queries = $this->db->getExecutedQueries();
        $insertQueries = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_draft'));
        $this->assertCount(1, $insertQueries);
    }

    public function testSaveFinalDraftOrderWithEmptyPicksSkipsInsert(): void
    {
        $this->repository->saveFinalDraftOrder(2026, []);

        $queries = $this->db->getExecutedQueries();
        $insertQueries = array_filter($queries, static fn (string $q): bool => str_contains($q, 'INSERT INTO ibl_draft'));
        $this->assertCount(0, $insertQueries);
    }

    public function testSaveFinalDraftOrderUpdatesSettings(): void
    {
        $picks = [
            ['round' => 1, 'pick' => 1, 'team' => 'Heat', 'teamid' => 1],
        ];

        $this->repository->saveFinalDraftOrder(2026, $picks);

        $queries = $this->db->getExecutedQueries();
        $settingsQueries = array_filter($queries, static fn (string $q): bool => str_contains($q, 'ibl_settings'));
        $this->assertNotEmpty($settingsQueries);
    }

    public function testSaveFinalDraftOrderDeletesOldDraftRows(): void
    {
        $this->repository->saveFinalDraftOrder(2026, []);

        $queries = $this->db->getExecutedQueries();
        $deleteQueries = array_filter($queries, static fn (string $q): bool => str_contains($q, 'DELETE FROM ibl_draft'));
        $this->assertCount(2, $deleteQueries);
    }
}
