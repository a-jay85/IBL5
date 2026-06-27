<?php

declare(strict_types=1);

namespace Tests\CapSpace;

use CapSpace\CapSpaceRepository;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

class CapSpaceRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testGetPlayersUnderContractAfterSeasonReturnsRows(): void
    {
        $this->mockDb->setMockData([
            ['cy' => 1, 'cyt' => 3],
            ['cy' => 2, 'cyt' => 4],
        ]);
        $repo = new CapSpaceRepository($this->mockDb);

        $result = $repo->getPlayersUnderContractAfterSeason(5);

        $this->assertSame([
            ['cy' => 1, 'cyt' => 3],
            ['cy' => 2, 'cyt' => 4],
        ], $result);
    }

    public function testGetPlayersUnderContractAfterSeasonReturnsEmptyWhenNoFutureContracts(): void
    {
        $this->mockDb->setMockData([]);
        $repo = new CapSpaceRepository($this->mockDb);

        $this->assertSame([], $repo->getPlayersUnderContractAfterSeason(5));
    }
}
