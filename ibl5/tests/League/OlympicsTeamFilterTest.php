<?php

declare(strict_types=1);

namespace Tests\League;

use League\OlympicsTeamFilter;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * @covers \League\OlympicsTeamFilter
 */
class OlympicsTeamFilterTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        OlympicsTeamFilter::resetCache();
        $this->mockDb = new MockDatabase();
    }

    protected function tearDown(): void
    {
        OlympicsTeamFilter::resetCache();
    }

    public function testGetRealTeamIdsReturnsOnlyRealTeams(): void
    {
        $this->mockDb->setMockData([
            ['teamid' => 1],
            ['teamid' => 2],
            ['teamid' => 5],
        ]);

        $ids = OlympicsTeamFilter::getRealTeamIds($this->mockDb);

        $this->assertSame([1, 2, 5], $ids);
    }

    public function testIsRealOlympicsTeamReturnsTrueForRealTeam(): void
    {
        $this->mockDb->setMockData([
            ['teamid' => 1],
            ['teamid' => 3],
        ]);

        $this->assertTrue(OlympicsTeamFilter::isRealOlympicsTeam($this->mockDb, 1));
        $this->assertTrue(OlympicsTeamFilter::isRealOlympicsTeam($this->mockDb, 3));
    }

    public function testIsRealOlympicsTeamReturnsFalseForFillerTeam(): void
    {
        $this->mockDb->setMockData([
            ['teamid' => 1],
        ]);

        $this->assertFalse(OlympicsTeamFilter::isRealOlympicsTeam($this->mockDb, 99));
    }

    public function testCachePreventsMultipleQueries(): void
    {
        $this->mockDb->setMockData([
            ['teamid' => 1],
        ]);

        OlympicsTeamFilter::getRealTeamIds($this->mockDb);
        OlympicsTeamFilter::getRealTeamIds($this->mockDb);

        $queries = $this->mockDb->getExecutedQueries();
        $olympicsQueries = array_filter(
            $queries,
            static fn (string $q): bool => str_contains($q, 'ibl_olympics_team_info'),
        );
        $this->assertCount(1, $olympicsQueries);
    }

    public function testResetCacheClearsState(): void
    {
        $this->mockDb->setMockData([
            ['teamid' => 1],
        ]);

        OlympicsTeamFilter::getRealTeamIds($this->mockDb);
        OlympicsTeamFilter::resetCache();

        $this->mockDb->setMockData([
            ['teamid' => 1],
            ['teamid' => 2],
        ]);

        $ids = OlympicsTeamFilter::getRealTeamIds($this->mockDb);

        $this->assertSame([1, 2], $ids);
    }

    public function testGetRealTeamIdsReturnsEmptyArrayWhenNoRealTeams(): void
    {
        $this->mockDb->setMockData([]);

        $ids = OlympicsTeamFilter::getRealTeamIds($this->mockDb);

        $this->assertSame([], $ids);
    }
}
