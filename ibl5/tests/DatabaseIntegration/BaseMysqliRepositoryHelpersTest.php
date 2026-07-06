<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class BaseMysqliRepositoryHelpersTest extends DatabaseTestCase
{
    private TestableRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TestableRepository($this->db);
    }

    public function testFetchAllInListEmptyReturnsEmptyArray(): void
    {
        $result = $this->repo->callFetchAllInList(
            "SELECT * FROM `ibl_team_info` WHERE teamid IN ({IN})",
            'i',
            []
        );

        self::assertSame([], $result);
    }

    public function testFetchAllInListEmptyRunsZeroQueries(): void
    {
        $counter = new QueryCountingRepository($this->db);
        $counter->callFetchAllInList(
            "SELECT * FROM `ibl_team_info` WHERE teamid IN ({IN})",
            'i',
            []
        );

        self::assertSame(0, $counter->getQueryCount());
    }

    public function testFetchAllInListIntType(): void
    {
        $result = $this->repo->callFetchAllInList(
            "SELECT teamid, team_name FROM `ibl_team_info` WHERE teamid IN ({IN}) ORDER BY teamid",
            'i',
            [1, 2, 3]
        );

        self::assertCount(3, $result);
        self::assertSame(1, $result[0]['teamid']);
        self::assertSame(2, $result[1]['teamid']);
        self::assertSame(3, $result[2]['teamid']);
    }

    public function testFetchAllInListStringType(): void
    {
        $result = $this->repo->callFetchAllInList(
            "SELECT teamid, team_name FROM `ibl_team_info` WHERE team_name IN ({IN}) ORDER BY teamid",
            's',
            ['Metros', 'Stars']
        );

        self::assertCount(2, $result);
        self::assertSame('Metros', $result[0]['team_name']);
        self::assertSame('Stars', $result[1]['team_name']);
    }

    public function testFetchAllInListRejectsMultiCharType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('fetchAllInList: $type must be a single mysqli type character');

        $this->repo->callFetchAllInList(
            "SELECT * FROM `ibl_team_info` WHERE teamid IN ({IN})",
            'ii',
            [1, 2]
        );
    }

    public function testFetchAllInListWithPrefixParams(): void
    {
        $result = $this->repo->callFetchAllInListWithPrefix(
            "SELECT teamid, team_name FROM `ibl_team_info` WHERE team_city = ? AND teamid IN ({IN}) ORDER BY teamid",
            'i',
            [1, 2, 3, 4, 5],
            's',
            'New York'
        );

        self::assertCount(1, $result);
        self::assertSame(1, $result[0]['teamid']);
        self::assertSame('Metros', $result[0]['team_name']);
    }

    public function testGameOfThatDaySubqueryProducesValidSql(): void
    {
        $subquery = $this->repo->callGameOfThatDaySubquery();

        self::assertStringContainsString('MIN(game_of_that_day)', $subquery);
        self::assertStringContainsString('GROUP BY game_date, visitor_teamid, home_teamid', $subquery);
        self::assertStringContainsString('ibl_box_scores_teams', $subquery);
    }

    public function testGameOfThatDaySubqueryIsExecutable(): void
    {
        $subquery = $this->repo->callGameOfThatDaySubquery();
        $query = "SELECT COUNT(*) AS cnt FROM {$subquery} bst";

        $stmt = $this->db->prepare($query);
        self::assertNotFalse($stmt, 'gameOfThatDaySubquery must produce valid SQL: ' . $this->db->error);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        self::assertIsArray($row);
        self::assertArrayHasKey('cnt', $row);
        self::assertGreaterThanOrEqual(0, $row['cnt']);
    }
}

/**
 * @internal Test double exposing protected helpers for testing.
 */
class TestableRepository extends \BaseMysqliRepository
{
    /**
     * @param list<int|string> $ids
     * @return list<array<string, mixed>>
     */
    public function callFetchAllInList(string $query, string $type, array $ids): array
    {
        return $this->fetchAllInList($query, $type, $ids);
    }

    /**
     * @param list<int|string> $ids
     * @return list<array<string, mixed>>
     */
    public function callFetchAllInListWithPrefix(
        string $query,
        string $type,
        array $ids,
        string $prefixTypes = '',
        mixed ...$prefixParams,
    ): array {
        return $this->fetchAllInList($query, $type, $ids, $prefixTypes, ...$prefixParams);
    }

    public function callGameOfThatDaySubquery(): string
    {
        return $this->gameOfThatDaySubquery();
    }
}

/**
 * @internal Counts calls to fetchAll to verify the empty-list short-circuit.
 */
class QueryCountingRepository extends \BaseMysqliRepository
{
    private int $queryCount = 0;

    protected function fetchAll(string $query, string $types = '', mixed ...$params): array
    {
        $this->queryCount++;
        return parent::fetchAll($query, $types, ...$params);
    }

    /**
     * @param list<int|string> $ids
     * @return list<array<string, mixed>>
     */
    public function callFetchAllInList(string $query, string $type, array $ids): array
    {
        return $this->fetchAllInList($query, $type, $ids);
    }

    public function getQueryCount(): int
    {
        return $this->queryCount;
    }
}
