<?php

declare(strict_types=1);

namespace Tests\DraftPickLocator;

use DraftPickLocator\DraftPickLocatorRepository;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

class DraftPickLocatorRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testGetAllDraftPicksGroupedByTeamGroupsRowsByTeamId(): void
    {
        $this->mockDb->setMockData([
            ['teampick_teamid' => 1, 'ownerofpick' => 'Team A', 'year' => 2026, 'round' => 1],
            ['teampick_teamid' => 1, 'ownerofpick' => 'Team A', 'year' => 2027, 'round' => 2],
            ['teampick_teamid' => 2, 'ownerofpick' => 'Team B', 'year' => 2026, 'round' => 1],
        ]);
        $repo = new DraftPickLocatorRepository($this->mockDb);

        $result = $repo->getAllDraftPicksGroupedByTeam();

        $this->assertSame([
            1 => [
                ['ownerofpick' => 'Team A', 'year' => 2026, 'round' => 1],
                ['ownerofpick' => 'Team A', 'year' => 2027, 'round' => 2],
            ],
            2 => [
                ['ownerofpick' => 'Team B', 'year' => 2026, 'round' => 1],
            ],
        ], $result);
    }

    public function testGetAllDraftPicksGroupedByTeamReturnsEmptyArrayWhenNoPicks(): void
    {
        $this->mockDb->setMockData([]);
        $repo = new DraftPickLocatorRepository($this->mockDb);

        $this->assertSame([], $repo->getAllDraftPicksGroupedByTeam());
    }

    public function testGetDraftPicksForTeamReturnsPicks(): void
    {
        $this->mockDb->setMockData([
            ['ownerofpick' => 'Team A', 'year' => 2026, 'round' => 1],
            ['ownerofpick' => 'Team A', 'year' => 2027, 'round' => 2],
        ]);
        $repo = new DraftPickLocatorRepository($this->mockDb);

        $result = $repo->getDraftPicksForTeam(1);

        $this->assertSame([
            ['ownerofpick' => 'Team A', 'year' => 2026, 'round' => 1],
            ['ownerofpick' => 'Team A', 'year' => 2027, 'round' => 2],
        ], $result);
    }

    public function testGetDraftPicksForTeamReturnsEmptyArrayForTeamWithNoPicks(): void
    {
        $this->mockDb->setMockData([]);
        $repo = new DraftPickLocatorRepository($this->mockDb);

        $this->assertSame([], $repo->getDraftPicksForTeam(99));
    }
}
