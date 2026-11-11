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

        // The pair filter binds twice: once ahead of the dedupe, once on the outer query.
        self::assertSame('iiiiiiii', $this->mockDb->getLastBoundTypes());
        self::assertSame([3, 7, 7, 3, 3, 7, 7, 3], $this->mockDb->getLastBoundParams());
    }

    public function testGmPairGamesBindsHostileNameAsString(): void
    {
        $hostile = "<script>alert(1)</script>' OR 1=1 -- ";
        $this->stubTenures($hostile, 'Rival Gm');
        $this->repo->getGmPairGames($hostile, 'Rival Gm');

        // Two franchise tuples, two tenure spans bound once per side, the CASE branches bound once
        // per side (franchise, from, to, name), then the four GM names.
        self::assertSame('iiiiiiiiiiiiiiiiiiisiiisiiisiiisssss', $this->mockDb->getLastBoundTypes());
        self::assertSame(
            [
                1, 2, 2, 1,
                1, 2000, 2005, 2, 1990, 9999, 1, 2000, 2005, 2, 1990, 9999,
                1, 2000, 2005, $hostile, 2, 1990, 9999, 'Rival Gm', 1, 2000, 2005, $hostile, 2, 1990, 9999, 'Rival Gm',
                $hostile, 'Rival Gm', 'Rival Gm', $hostile,
            ],
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

    /** Plan row 7 name; the plan's `gmExists` shipped as `findGmName`. */
    public function testGmExistsBindsName(): void
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
        self::assertStringContainsString('+ t.is_mid_season_start', $queries[0]);
        self::assertStringContainsString('ORDER BY t.start_season_year ASC, t.id ASC', $queries[0]);
        self::assertStringContainsString('v_gm <> h_gm', $query);
    }

    public function testGmPairGamesResolvesGmsWithoutCorrelatedSubquery(): void
    {
        $this->mockDb->onQuery('gm_display_name IN', [
            ['gm_display_name' => 'A Gm', 'franchise_id' => 1, 'from_year' => 2000, 'to_year' => 2005, 'is_pair' => 1],
            ['gm_display_name' => 'B Gm', 'franchise_id' => 2, 'from_year' => 1990, 'to_year' => 9999, 'is_pair' => 1],
            ['gm_display_name' => 'C Gm', 'franchise_id' => 2, 'from_year' => 1995, 'to_year' => 1999, 'is_pair' => 0],
        ]);
        $this->repo->getGmPairGames('A Gm', 'B Gm');

        $queries = $this->mockDb->getPreparedQueries();
        $query = $queries[array_key_last($queries)];
        self::assertStringNotContainsString('LIMIT 1', $query);
        // The non-pair GM C still gets a CASE branch, so a side C ran resolves to C and not to the pair GM.
        $filter = "AND ((b.visitor_teamid = ? AND b.home_teamid = ?) OR (b.visitor_teamid = ? AND b.home_teamid = ?))\n"
            . "      AND ((b.visitor_teamid = ? AND b.season_year BETWEEN ? AND ?) OR (b.visitor_teamid = ? AND b.season_year BETWEEN ? AND ?))\n"
            . '      AND ((b.home_teamid = ? AND b.season_year BETWEEN ? AND ?) OR (b.home_teamid = ? AND b.season_year BETWEEN ? AND ?))';
        self::assertStringContainsString($filter, $query);
        $branches = 'WHEN %s = ? AND g.season_year BETWEEN ? AND ? THEN ? ';
        $case = 'CONVERT((CASE ' . str_repeat($branches, 3) . 'ELSE NULL END) USING utf8mb4) COLLATE utf8mb4_unicode_ci';
        self::assertStringContainsString("gm_games AS (\n    SELECT g.*,\n        " . sprintf($case, 'g.visitor_teamid', 'g.visitor_teamid', 'g.visitor_teamid') . ' AS v_gm,', $query);
        self::assertStringContainsString("\n        " . sprintf($case, 'g.home_teamid', 'g.home_teamid', 'g.home_teamid') . " AS h_gm\n    FROM games g\n)", $query);

        self::assertSame('iiiiiiiiiiiiiiiiiiisiiisiiisiiisiiisiiisssss', $this->mockDb->getLastBoundTypes());
        self::assertSame(
            [
                1, 2, 2, 1,
                1, 2000, 2005, 2, 1990, 9999, 1, 2000, 2005, 2, 1990, 9999,
                1, 2000, 2005, 'A Gm', 2, 1990, 9999, 'B Gm', 2, 1995, 1999, 'C Gm',
                1, 2000, 2005, 'A Gm', 2, 1990, 9999, 'B Gm', 2, 1995, 1999, 'C Gm',
                'A Gm', 'B Gm', 'B Gm', 'A Gm',
            ],
            $this->mockDb->getLastBoundParams(),
        );
    }

    public function testGmPairGamesSkipsQueryWhenPairSharesOneFranchise(): void
    {
        $this->mockDb->onQuery('gm_display_name IN', [
            ['gm_display_name' => 'A Gm', 'franchise_id' => 1, 'from_year' => 2000, 'to_year' => 2005, 'is_pair' => 1],
            ['gm_display_name' => 'B Gm', 'franchise_id' => 1, 'from_year' => 2006, 'to_year' => 9999, 'is_pair' => 1],
        ]);

        self::assertSame([], $this->repo->getGmPairGames('A Gm', 'B Gm'));
        self::assertCount(1, $this->mockDb->getPreparedQueries());

        $fresh = new MockDatabase();
        $fresh->setMockData([]);
        $fresh->onQuery('gm_display_name IN', [
            ['gm_display_name' => 'A Gm', 'franchise_id' => 1, 'from_year' => 2000, 'to_year' => 2005, 'is_pair' => 1],
            ['gm_display_name' => 'B Gm', 'franchise_id' => 1, 'from_year' => 2006, 'to_year' => 9999, 'is_pair' => 1],
        ]);
        self::assertSame(['totals' => [], 'highs' => []], (new RivalriesRepository($fresh))->getGmTopPerformers('A Gm', 'B Gm'));
        self::assertCount(1, $fresh->getPreparedQueries());
    }

    private function stubTenures(string $a, string $b): void
    {
        $this->mockDb->onQuery('gm_display_name IN', [
            ['gm_display_name' => $a, 'franchise_id' => 1, 'from_year' => 2000, 'to_year' => 2005, 'is_pair' => 1],
            ['gm_display_name' => $b, 'franchise_id' => 2, 'from_year' => 1990, 'to_year' => 9999, 'is_pair' => 1],
        ]);
    }
}
