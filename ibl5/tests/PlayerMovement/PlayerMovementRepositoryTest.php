<?php

declare(strict_types=1);

namespace Tests\PlayerMovement;

use PlayerMovement\PlayerMovementRepository;
use PlayerMovement\Contracts\PlayerMovementRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

class PlayerMovementRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testGetPlayerMovementsReturnsEmptyArrayWhenNoMovements(): void
    {
        $this->mockDb->setMockData([]);
        $repository = new PlayerMovementRepository($this->mockDb);

        $result = $repository->getPlayerMovements(2024);

        $this->assertSame([], $result);
    }

    public function testGetPlayerMovementsReturnsData(): void
    {
        $this->mockDb->setMockData([
            [
                'pid' => 100,
                'name' => 'Test Player',
                'old_teamid' => 1,
                'old_team' => 'Hawks',
                'new_teamid' => 2,
                'new_team' => 'Celtics',
                'old_city' => 'Atlanta',
                'old_color1' => 'E03A3E',
                'old_color2' => 'C1D32F',
                'new_city' => 'Boston',
                'new_color1' => '007A33',
                'new_color2' => 'BA9653',
            ],
        ]);
        $repository = new PlayerMovementRepository($this->mockDb);

        $result = $repository->getPlayerMovements(2024);

        $this->assertCount(1, $result);
        $this->assertSame('Test Player', $result[0]['name']);
        $this->assertSame(1, $result[0]['old_teamid']);
        $this->assertSame(2, $result[0]['new_teamid']);
    }
}
