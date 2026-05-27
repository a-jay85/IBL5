<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\LeagueContext;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

#[Group('database')]
class BaseMysqliRepositoryTest extends DatabaseTestCase
{
    private TestableBaseMysqliRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TestableBaseMysqliRepository($this->db);
    }

    // ==================== Constructor ====================

    public function testConstructorThrowsOnInvalidConnection(): void
    {
        $badDb = new \mysqli();
        try {
            $badDb->real_connect('db', 'nonexistent_user_xyz', 'wrong', 'x');
        } catch (\mysqli_sql_exception) {
            // connect_errno is now set
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1002);

        new TestableBaseMysqliRepository($badDb);
    }

    // ==================== resolveTable ====================

    public function testResolveTableWithoutLeagueContext(): void
    {
        self::assertSame('ibl_standings', $this->repo->callResolveTable('ibl_standings'));
    }

    public function testResolveTableWithLeagueContext(): void
    {
        $context = $this->createStub(LeagueContext::class);
        $context->method('getTableName')
            ->willReturnCallback(static fn (string $table): string => str_replace('ibl_', 'ibl_olympics_', $table));

        $repo = new TestableBaseMysqliRepository($this->db, $context);
        self::assertSame('ibl_olympics_standings', $repo->callResolveTable('ibl_standings'));
    }

    // ==================== executeQuery errors ====================

    public function testExecuteQueryThrowsOnTypeMismatch(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1001);

        $this->repo->callExecuteQuery('SELECT 1', 'ii', 1);
    }

    public function testExecuteQueryThrowsOnPrepareFail(): void
    {
        $this->expectException(\Throwable::class);

        $this->repo->callExecuteQuery('SELECT * FROM nonexistent_table_xyz', '');
    }

    public function testExecuteQueryThrowsOnExecuteFail(): void
    {
        $this->insertRow('ibl_team_info', [
            'teamid' => 999,
            'team_city' => 'Test',
            'team_name' => 'Testers',
        ]);

        $this->expectException(\Throwable::class);

        $this->repo->callExecute(
            "INSERT INTO `ibl_team_info` (teamid, team_city, team_name) VALUES (?, ?, ?)",
            'iss',
            999,
            'Dupe',
            'Dupers'
        );
    }

    // ==================== fetchOne / fetchAll / execute ====================

    public function testFetchOneReturnsRowWhenFound(): void
    {
        $row = $this->repo->callFetchOne(
            "SELECT team_name FROM `ibl_team_info` WHERE teamid = ?",
            'i',
            1
        );

        self::assertNotNull($row);
        self::assertSame('Metros', $row['team_name']);
    }

    public function testFetchOneReturnsNullWhenNotFound(): void
    {
        $row = $this->repo->callFetchOne(
            "SELECT team_name FROM `ibl_team_info` WHERE teamid = ?",
            'i',
            999999
        );

        self::assertNull($row);
    }

    public function testFetchAllReturnsMultipleRows(): void
    {
        $rows = $this->repo->callFetchAll(
            "SELECT teamid FROM `ibl_team_info` WHERE teamid IN (1, 2) ORDER BY teamid"
        );

        self::assertCount(2, $rows);
        self::assertSame(1, $rows[0]['teamid']);
        self::assertSame(2, $rows[1]['teamid']);
    }

    public function testFetchAllReturnsEmptyArray(): void
    {
        $rows = $this->repo->callFetchAll(
            "SELECT teamid FROM `ibl_team_info` WHERE teamid = ?",
            'i',
            999999
        );

        self::assertSame([], $rows);
    }

    public function testExecuteReturnsAffectedRowCount(): void
    {
        $affected = $this->repo->callExecute(
            "UPDATE `ibl_team_info` SET team_city = 'TestCity' WHERE teamid = ?",
            'i',
            1
        );

        self::assertSame(1, $affected);
    }

    public function testExecuteReturnsZeroWhenNoRowsAffected(): void
    {
        $affected = $this->repo->callExecute(
            "UPDATE `ibl_team_info` SET team_city = 'TestCity' WHERE teamid = ?",
            'i',
            999999
        );

        self::assertSame(0, $affected);
    }

    // ==================== fetchAllRealTeams ====================

    public function testFetchAllRealTeamsDefaultOrder(): void
    {
        $teams = $this->repo->callFetchAllRealTeams();
        self::assertNotEmpty($teams);

        $names = array_column($teams, 'team_name');
        $sorted = $names;
        sort($sorted);
        self::assertSame($sorted, $names);
    }

    public function testFetchAllRealTeamsOrderByTeamid(): void
    {
        $teams = $this->repo->callFetchAllRealTeams('teamid ASC');
        self::assertNotEmpty($teams);

        $ids = array_column($teams, 'teamid');
        $sorted = $ids;
        sort($sorted, SORT_NUMERIC);
        self::assertSame($sorted, $ids);
    }

    public function testFetchAllRealTeamsInvalidOrderFallsBackToDefault(): void
    {
        $teams = $this->repo->callFetchAllRealTeams('DROP TABLE');
        self::assertNotEmpty($teams);

        $names = array_column($teams, 'team_name');
        $sorted = $names;
        sort($sorted);
        self::assertSame($sorted, $names);
    }

    // ==================== getLastInsertId ====================

    public function testGetLastInsertIdAfterInsert(): void
    {
        $this->repo->callExecute(
            "INSERT INTO `ibl_sim_dates` (start_date, end_date) VALUES (?, ?)",
            'ss',
            '2025-03-01',
            '2025-03-10'
        );

        $id = $this->repo->callGetLastInsertId();
        self::assertGreaterThan(0, $id);
    }

    // ==================== transactional ====================

    public function testTransactionalCommitsOnSuccess(): void
    {
        // DatabaseTestCase starts a transaction, so this will use savepoints
        $result = $this->repo->callTransactional(function () {
            $this->repo->callExecute(
                "UPDATE `ibl_team_info` SET team_city = 'TxCity' WHERE teamid = ?",
                'i',
                1
            );
            return 'done';
        });

        self::assertSame('done', $result);

        $row = $this->repo->callFetchOne(
            "SELECT team_city FROM `ibl_team_info` WHERE teamid = ?",
            'i',
            1
        );
        self::assertNotNull($row);
        self::assertSame('TxCity', $row['team_city']);
    }

    public function testTransactionalRollsBackOnException(): void
    {
        $originalRow = $this->repo->callFetchOne(
            "SELECT team_city FROM `ibl_team_info` WHERE teamid = ?",
            'i',
            1
        );

        try {
            $this->repo->callTransactional(function (): void {
                $this->repo->callExecute(
                    "UPDATE `ibl_team_info` SET team_city = 'RolledBack' WHERE teamid = ?",
                    'i',
                    1
                );
                throw new \RuntimeException('Intentional rollback');
            });
        } catch (\RuntimeException $e) {
            self::assertSame('Intentional rollback', $e->getMessage());
        }

        $row = $this->repo->callFetchOne(
            "SELECT team_city FROM `ibl_team_info` WHERE teamid = ?",
            'i',
            1
        );
        self::assertNotNull($row);
        self::assertNotNull($originalRow);
        self::assertSame($originalRow['team_city'], $row['team_city']);
    }

    public function testTransactionalUsesSavepointWhenAlreadyInTransaction(): void
    {
        // DatabaseTestCase::setUp() starts a transaction, so we're already in one.
        // This verifies that transactional() doesn't throw when called inside a transaction.
        $result = $this->repo->callTransactional(static fn (): string => 'savepoint_ok');
        self::assertSame('savepoint_ok', $result);
    }

    public function testTransactionalSavepointRollsBackOnException(): void
    {
        $this->repo->callExecute(
            "UPDATE `ibl_team_info` SET team_city = 'Before' WHERE teamid = ?",
            'i',
            1
        );

        try {
            $this->repo->callTransactional(function (): void {
                $this->repo->callExecute(
                    "UPDATE `ibl_team_info` SET team_city = 'Inside' WHERE teamid = ?",
                    'i',
                    1
                );
                throw new \RuntimeException('savepoint rollback');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $row = $this->repo->callFetchOne(
            "SELECT team_city FROM `ibl_team_info` WHERE teamid = ?",
            'i',
            1
        );
        self::assertNotNull($row);
        self::assertSame('Before', $row['team_city']);
    }

    // ==================== setLogger ====================

    public function testSetLoggerOverridesDefaultChannel(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('error');

        $this->repo->setLogger($logger);

        try {
            $this->repo->callExecuteQuery('SELECT 1', 'ii', 1);
        } catch (\RuntimeException) {
            // expected — type mismatch
        }
    }
}

/**
 * @internal Test double exposing protected BaseMysqliRepository methods.
 */
class TestableBaseMysqliRepository extends \BaseMysqliRepository
{
    public function callResolveTable(string $table): string
    {
        return $this->resolveTable($table);
    }

    public function callExecuteQuery(string $query, string $types = '', mixed ...$params): object
    {
        return $this->executeQuery($query, $types, ...$params);
    }

    public function callFetchOne(string $query, string $types = '', mixed ...$params): ?array
    {
        return $this->fetchOne($query, $types, ...$params);
    }

    public function callFetchAll(string $query, string $types = '', mixed ...$params): array
    {
        return $this->fetchAll($query, $types, ...$params);
    }

    public function callExecute(string $query, string $types = '', mixed ...$params): int
    {
        return $this->execute($query, $types, ...$params);
    }

    public function callFetchAllRealTeams(string $orderBy = 'team_name ASC'): array
    {
        return $this->fetchAllRealTeams($orderBy);
    }

    public function callGetLastInsertId(): int
    {
        return $this->getLastInsertId();
    }

    /** @template T
     * @param callable(): T $fn
     * @return T */
    public function callTransactional(callable $fn): mixed
    {
        return $this->transactional($fn);
    }
}
