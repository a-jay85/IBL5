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
        $this->stubTenures($hostile, 'Rival Gm');
        $this->repo->getGmPairGames($hostile, 'Rival Gm');

        // Two tenure spans, each bound once per side (3 ints apiece), then the four GM names.
        self::assertSame('iiiiiiiiiiiissss', $this->mockDb->getLastBoundTypes());
        self::assertSame(
            [1, 2000, 2005, 2, 1990, 9999, 1, 2000, 2005, 2, 1990, 9999, $hostile, 'Rival Gm', 'Rival Gm', $hostile],
            $this->mockDb->getLastBoundParams(),
        );
        foreach ($this->mockDb->getPreparedQueries() as $query) {
            self::assertStringNotContainsString($hostile, $query);
            self::assertStringNotContainsString('<script>', $query);
        }
    }

    public function testGmPairReadsSkipGameQueriesWhenAGmHasNoTenure(): void
    {
        $this->mockDb->onQuery('gm_display_name IN', [
            ['gm_display_name' => 'A Gm', 'franchise_id' => 1, 'from_year' => 2000, 'to_year' => 2005],
        ]);

        self::assertSame([], $this->repo->getGmPairGames('A Gm', 'Ghost Gm'));
        self::assertSame([], $this->repo->getGmPlayoffSeries('A Gm', 'Ghost Gm'));
        self::assertSame(['totals' => [], 'highs' => []], $this->repo->getGmTopPerformers('A Gm', 'Ghost Gm'));
        self::assertCount(3, $this->mockDb->getPreparedQueries());
    }

    public function testFindGmNameBindsName(): void
    {
        $this->repo->findGmName('Some Gm');

        self::assertSame('ss', $this->mockDb->getLastBoundTypes());
        self::assertSame(['Some Gm', 'Some Gm'], $this->mockDb->getLastBoundParams());
    }

    public function testPairQueriesCarryDedupeAndTenureRule(): void
    {
        $this->stubTenures('A Gm', 'B Gm');
        $this->repo->getGmPairGames('A Gm', 'B Gm');

        $queries = $this->mockDb->getPreparedQueries();
        self::assertNotSame([], $queries);
        $query = $queries[array_key_last($queries)];
        self::assertStringContainsString('GROUP BY b.game_date, b.visitor_teamid, b.home_teamid, b.game_of_that_day', $query);
        self::assertStringContainsString('+ t.is_mid_season_start', $query);
        self::assertStringContainsString('v_gm <> h_gm', $query);
    }

    private function stubTenures(string $a, string $b): void
    {
        $this->mockDb->onQuery('gm_display_name IN', [
            ['gm_display_name' => $a, 'franchise_id' => 1, 'from_year' => 2000, 'to_year' => 2005],
            ['gm_display_name' => $b, 'franchise_id' => 2, 'from_year' => 1990, 'to_year' => 9999],
        ]);
    }
}
