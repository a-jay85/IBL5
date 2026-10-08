<?php

declare(strict_types=1);

namespace Tests\SeasonRosterChanges;

use SeasonRosterChanges\SeasonRosterChangesRepository;
use SeasonRosterChanges\Contracts\SeasonRosterChangesRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

class SeasonRosterChangesRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testGetSeasonRosterChangesReturnsEmptyArrayWhenNoMovements(): void
    {
        $this->mockDb->setMockData([]);
        $repository = new SeasonRosterChangesRepository($this->mockDb);

        $result = $repository->getSeasonRosterChanges(2024);

        $this->assertSame([], $result);
    }

    public function testGetSeasonRosterChangesReturnsData(): void
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
        $repository = new SeasonRosterChangesRepository($this->mockDb);

        $result = $repository->getSeasonRosterChanges(2024);

        $this->assertCount(1, $result);
        $this->assertSame('Test Player', $result[0]['name']);
        $this->assertSame(1, $result[0]['old_teamid']);
        $this->assertSame(2, $result[0]['new_teamid']);
    }
}
