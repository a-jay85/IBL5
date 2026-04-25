<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use AwardHistory\AwardHistoryRepository;

class AwardHistoryRepositoryTest extends DatabaseTestCase
{
    private AwardHistoryRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new AwardHistoryRepository($this->db);
    }

    public function testNoFiltersReturnsAllAwards(): void
    {
        $this->insertAwardRow('AWH TestPlyr', 'MVP', 2020);

        $result = $this->repo->searchAwards($this->buildParams());

        self::assertArrayHasKey('results', $result);
        self::assertArrayHasKey('count', $result);
        self::assertGreaterThanOrEqual(1, $result['count']);
    }

    public function testFilterByYearExactMatch(): void
    {
        $this->insertAwardRow('AWH YearTest', 'MVP', 2095);

        $result = $this->repo->searchAwards($this->buildParams(['year' => 2095]));

        self::assertSame(1, $result['count']);
        self::assertSame('AWH YearTest', $result['results'][0]['name']);
        self::assertSame(2095, $result['results'][0]['year']);
    }

    public function testFilterByAwardLikePattern(): void
    {
        $this->insertAwardRow('AWH AwardLike', 'Most Valuable Player', 2020);

        $result = $this->repo->searchAwards($this->buildParams(['award' => 'Most Valuable']));

        $found = $this->findByName($result['results'], 'AWH AwardLike');
        self::assertNotNull($found);
        self::assertSame('Most Valuable Player', $found['award']);
    }

    public function testFilterByNameLikePattern(): void
    {
        $this->insertAwardRow('AWH NameLike', 'DPOY', 2020);

        $result = $this->repo->searchAwards($this->buildParams(['name' => 'AWH Name']));

        $found = $this->findByName($result['results'], 'AWH NameLike');
        self::assertNotNull($found);
    }

    public function testCombinedYearAndNameFilter(): void
    {
        $this->insertAwardRow('AWH Combined', 'MVP', 2096);
        $this->insertAwardRow('AWH Combined', 'DPOY', 2097);

        $result = $this->repo->searchAwards($this->buildParams(['year' => 2096, 'name' => 'AWH Combined']));

        self::assertSame(1, $result['count']);
        self::assertSame(2096, $result['results'][0]['year']);
    }

    public function testCombinedAwardAndYearFilter(): void
    {
        $this->insertAwardRow('AWH ComboAY', 'Sixth Man', 2096);
        $this->insertAwardRow('AWH ComboAY2', 'MVP', 2096);

        $result = $this->repo->searchAwards($this->buildParams(['year' => 2096, 'award' => 'Sixth']));

        self::assertSame(1, $result['count']);
        self::assertSame('AWH ComboAY', $result['results'][0]['name']);
    }

    public function testSortByName(): void
    {
        $this->insertAwardRow('AWH Zulu', 'MVP', 2098);
        $this->insertAwardRow('AWH Alpha', 'MVP', 2098);

        $result = $this->repo->searchAwards($this->buildParams(['year' => 2098, 'sortby' => 1]));

        self::assertGreaterThanOrEqual(2, $result['count']);
        self::assertSame('AWH Alpha', $result['results'][0]['name']);
    }

    public function testSortByAward(): void
    {
        $this->insertAwardRow('AWH SortAwd', 'DPOY', 2099);
        $this->insertAwardRow('AWH SortAwd2', 'MVP', 2099);

        $result = $this->repo->searchAwards($this->buildParams(['year' => 2099, 'sortby' => 2]));

        self::assertGreaterThanOrEqual(2, $result['count']);
        self::assertSame('DPOY', $result['results'][0]['award']);
    }

    public function testSortByYear(): void
    {
        $this->insertAwardRow('AWH SortYr', 'MVP', 2092);
        $this->insertAwardRow('AWH SortYr', 'MVP', 2091);

        $result = $this->repo->searchAwards($this->buildParams(['name' => 'AWH SortYr', 'sortby' => 3]));

        self::assertSame(2, $result['count']);
        self::assertSame(2091, $result['results'][0]['year']);
    }

    public function testInvalidSortbyDefaultsToYear(): void
    {
        $this->insertAwardRow('AWH BadSort', 'MVP', 2093);
        $this->insertAwardRow('AWH BadSort', 'MVP', 2092);

        $result = $this->repo->searchAwards($this->buildParams(['name' => 'AWH BadSort', 'sortby' => 999]));

        self::assertSame(2, $result['count']);
        // Default sort is 'year' ASC
        self::assertSame(2092, $result['results'][0]['year']);
    }

    public function testLeftJoinPopulatesPidForKnownPlayer(): void
    {
        $pid = 200120001;
        $this->insertTestPlayer($pid, 'AWH PidKnown');
        $this->insertAwardRow('AWH PidKnown', 'MVP', 2020);

        $result = $this->repo->searchAwards($this->buildParams(['name' => 'AWH PidKnown']));

        self::assertSame(1, $result['count']);
        self::assertSame($pid, $result['results'][0]['pid']);
    }

    public function testPidNullForPlayersNotInPlr(): void
    {
        $this->insertAwardRow('AWH NoPid', 'MVP', 2020);

        $result = $this->repo->searchAwards($this->buildParams(['name' => 'AWH NoPid']));

        self::assertSame(1, $result['count']);
        self::assertNull($result['results'][0]['pid']);
    }

    public function testCountMatchesResultsArrayLength(): void
    {
        $this->insertAwardRow('AWH Count1', 'MVP', 2094);
        $this->insertAwardRow('AWH Count2', 'DPOY', 2094);

        $result = $this->repo->searchAwards($this->buildParams(['year' => 2094]));

        self::assertSame(count($result['results']), $result['count']);
    }

    public function testNoMatchReturnsEmptyResults(): void
    {
        $result = $this->repo->searchAwards($this->buildParams(['year' => 9999]));

        self::assertSame(0, $result['count']);
        self::assertSame([], $result['results']);
    }

    public function testResultRowHasExpectedKeys(): void
    {
        $this->insertAwardRow('AWH KeyTest', 'MVP', 2020);

        $result = $this->repo->searchAwards($this->buildParams(['name' => 'AWH KeyTest']));

        self::assertSame(1, $result['count']);
        $row = $result['results'][0];
        self::assertArrayHasKey('year', $row);
        self::assertArrayHasKey('award', $row);
        self::assertArrayHasKey('name', $row);
        self::assertArrayHasKey('table_id', $row);
        self::assertArrayHasKey('pid', $row);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function buildParams(array $overrides = []): array
    {
        return array_merge([
            'year' => null,
            'award' => null,
            'name' => null,
            'sortby' => 3,
        ], $overrides);
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<string, mixed>|null
     */
    private function findByName(array $results, string $name): ?array
    {
        foreach ($results as $row) {
            if ($row['name'] === $name) {
                return $row;
            }
        }
        return null;
    }
}
