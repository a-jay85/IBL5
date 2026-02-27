<?php

declare(strict_types=1);

namespace Tests\ProjectedDraftOrder;

use ProjectedDraftOrder\Contracts\ProjectedDraftOrderRepositoryInterface;
use ProjectedDraftOrder\ProjectedDraftOrderRepository;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\MockDatabase;

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

    public function testImplementsRepositoryInterface(): void
    {
        $this->assertInstanceOf(ProjectedDraftOrderRepositoryInterface::class, $this->repository);
    }

    public function testGetAllTeamsWithStandingsReturnsArray(): void
    {
        $this->db->setMockData([
            [
                'tid' => 1, 'team_name' => 'Heat', 'wins' => 50, 'losses' => 32,
                'pct' => 0.610, 'conference' => 'Eastern', 'division' => 'Atlantic',
                'confWins' => 30, 'confLosses' => 12, 'divWins' => 10, 'divLosses' => 4,
                'clinchedDivision' => 1, 'color1' => '98002E', 'color2' => 'F9A01B',
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
            ['Visitor' => 1, 'VScore' => 105, 'Home' => 2, 'HScore' => 98],
        ]);

        $result = $this->repository->getPlayedGames(2026);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['Visitor']);
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
            ['tid' => 1, 'pointsFor' => 8500.0, 'pointsAgainst' => 8200.0],
        ]);

        $result = $this->repository->getPointDifferentials(2026);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['tid']);
    }

    public function testExtendsBaseMysqliRepository(): void
    {
        $this->assertInstanceOf(\BaseMysqliRepository::class, $this->repository);
    }
}
