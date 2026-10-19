<?php

declare(strict_types=1);

namespace Tests\Rivalries;

use PHPUnit\Framework\TestCase;
use Rivalries\RivalriesRepository;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Security tests: every pair query binds its inputs; hostile names never reach SQL text.
 */
final class RivalriesRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;
    private RivalriesRepository $repo;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockDb->setMockData([]);
        $this->repo = new RivalriesRepository($this->mockDb);
    }

    public function testFranchisePairGamesBindsBothOrientationsAsInts(): void
    {
        $this->repo->getFranchisePairGames(3, 7);

        self::assertSame('iiii', $this->mockDb->getLastBoundTypes());
        self::assertSame([3, 7, 7, 3], $this->mockDb->getLastBoundParams());
    }

    public function testGmPairGamesBindsHostileNameAsString(): void
    {
        $hostile = "<script>alert(1)</script>' OR 1=1 -- ";
        $this->repo->getGmPairGames($hostile, 'Rival Gm');

        self::assertSame('ssss', $this->mockDb->getLastBoundTypes());
        self::assertContains($hostile, $this->mockDb->getLastBoundParams());
        foreach ($this->mockDb->getPreparedQueries() as $query) {
            self::assertStringNotContainsString($hostile, $query);
            self::assertStringNotContainsString('<script>', $query);
        }
    }

    public function testGmExistsBindsName(): void
    {
        $this->repo->gmExists('Some Gm');

        self::assertSame('s', $this->mockDb->getLastBoundTypes());
        self::assertSame(['Some Gm'], $this->mockDb->getLastBoundParams());
    }

    public function testPairQueriesCarryDedupeAndTenureRule(): void
    {
        $this->repo->getGmPairGames('A Gm', 'B Gm');

        $queries = $this->mockDb->getPreparedQueries();
        self::assertNotSame([], $queries);
        $query = $queries[array_key_last($queries)];
        self::assertStringContainsString('GROUP BY b.game_date, b.visitor_teamid, b.home_teamid, b.game_of_that_day', $query);
        self::assertStringContainsString('+ t.is_mid_season_start', $query);
        self::assertStringContainsString('v_gm <> h_gm', $query);
    }
}
