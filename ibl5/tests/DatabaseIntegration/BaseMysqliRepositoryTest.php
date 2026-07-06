<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\LeagueContext;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\LoggerInterface;

#[Group('database')]
class BaseMysqliRepositoryTest extends DatabaseTestCase
{
    private TestableBaseMysqliRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        \BaseMysqliRepository::clearSharedLeagueContext();
        $this->repo = new TestableBaseMysqliRepository($this->db);
    }

    // ==================== Constructor ====================

    public function testConstructorThrowsOnInvalidConnection(): void
    {
        $badDb = new \mysqli();
        try {
            // Deliberate bad connection: host 'db' is unresolvable on the CI
            // runner (DB_HOST=127.0.0.1) and emits a real_connect() warning we
            // expect — suppress it so failOnWarning gates only genuine warnings.
            @$badDb->real_connect('db', 'nonexistent_user_xyz', 'wrong', 'x');
        } catch (\mysqli_sql_exception) {
            // connect_errno is now set
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1002);

        new TestableBaseMysqliRepository($badDb);
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
        $teams = $this->repo->callFetchAllRealTeams(\TeamOrderBy::TeamId);
        self::assertNotEmpty($teams);

        $ids = array_column($teams, 'teamid');
        $sorted = $ids;
        sort($sorted, SORT_NUMERIC);
        self::assertSame($sorted, $ids);
    }

    public function testGetAllRealTeamsRejectsNonWhitelistedOrderBy(): void
    {
        $repo = new \Repositories\TeamIdentityRepository($this->db);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains("Invalid orderBy 'team_city DESC'");

        $repo->getAllRealTeams('team_city DESC');
    }

    public function testGetAllRealTeamsAcceptsWhitelistedOrderBy(): void
    {
        $repo = new \Repositories\TeamIdentityRepository($this->db);
        $teams = $repo->getAllRealTeams('teamid ASC');

        self::assertNotEmpty($teams);
        $ids = array_column($teams, 'teamid');
        $sorted = $ids;
        sort($sorted, SORT_NUMERIC);
        self::assertSame($sorted, $ids);
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

    // ==================== rewriteTableNames ====================

    public function testRewriteTableNamesNoOpWithoutLeagueContext(): void
    {
        $query = "SELECT * FROM `ibl_plr` WHERE pid = ?";
        self::assertSame($query, $this->repo->callRewriteTableNames($query));
    }

    public function testRewriteTableNamesNoOpForIblContext(): void
    {
        $context = self::createStub(LeagueContext::class);
        $context->method('isOlympics')->willReturn(false);

        $repo = new TestableBaseMysqliRepository($this->db, $context);
        $query = "SELECT * FROM `ibl_plr` WHERE pid = ?";
        self::assertSame($query, $repo->callRewriteTableNames($query));
    }

    public function testRewriteTableNamesRewritesAllSixteenTablesForOlympics(): void
    {
        $context = self::createStub(LeagueContext::class);
        $context->method('isOlympics')->willReturn(true);

        $repo = new TestableBaseMysqliRepository($this->db, $context);

        $tables = [
            'ibl_saved_depth_chart_players',
            'ibl_saved_depth_charts',
            'ibl_box_scores_teams',
            'ibl_box_scores',
            'ibl_rcb_alltime_records',
            'ibl_rcb_season_records',
            'ibl_jsb_transactions',
            'ibl_jsb_history',
            'ibl_plr_snapshots',
            'ibl_league_config',
            'ibl_team_info',
            'ibl_standings',
            'ibl_schedule',
            'ibl_power',
            'ibl_hist',
            'ibl_plr',
        ];

        $parts = array_map(static fn (string $t): string => "SELECT * FROM `$t`", $tables);
        $query = implode(' UNION ALL ', $parts);
        $result = $repo->callRewriteTableNames($query);

        foreach ($tables as $table) {
            $olympicsTable = str_replace('ibl_', 'ibl_olympics_', $table);
            self::assertStringContainsString("`$olympicsTable`", $result, "Failed to rewrite $table");
            self::assertStringNotContainsString("`$table`", $result, "$table should not remain");
        }
    }

    public function testRewriteTableNamesHandlesSubstringCollisions(): void
    {
        $context = self::createStub(LeagueContext::class);
        $context->method('isOlympics')->willReturn(true);

        $repo = new TestableBaseMysqliRepository($this->db, $context);

        $query = "SELECT bt.* FROM `ibl_box_scores_teams` bt "
            . "JOIN `ibl_box_scores` bs ON bt.game_id = bs.game_id "
            . "JOIN `ibl_saved_depth_chart_players` sdcp ON sdcp.pid = bs.pid "
            . "JOIN `ibl_saved_depth_charts` sdc ON sdc.id = sdcp.chart_id";

        $result = $repo->callRewriteTableNames($query);

        self::assertStringContainsString('`ibl_olympics_box_scores_teams`', $result);
        self::assertStringContainsString('`ibl_olympics_box_scores`', $result);
        self::assertStringContainsString('`ibl_olympics_saved_depth_chart_players`', $result);
        self::assertStringContainsString('`ibl_olympics_saved_depth_charts`', $result);
    }

    public function testRewriteTableNamesRewritesColumnQualifiedReferences(): void
    {
        // Regression: a table-qualified column reference (ibl_plr.name) MUST be
        // rewritten alongside its FROM clause. The previous backtick-only
        // str_replace left it dangling, so rewriting `FROM `ibl_plr`` while
        // leaving `ibl_plr.name` produced "Unknown table 'ibl_plr'".
        $context = self::createStub(LeagueContext::class);
        $context->method('isOlympics')->willReturn(true);

        $repo = new TestableBaseMysqliRepository($this->db, $context);

        $query = "SELECT ibl_plr.name AS ibl_plr_name FROM `ibl_plr`";
        $result = $repo->callRewriteTableNames($query);

        self::assertStringContainsString('`ibl_olympics_plr`', $result);
        self::assertStringContainsString('ibl_olympics_plr.name', $result);
        // The qualified column ref is rewritten; the column alias, which merely
        // contains the table name as a substring, is left untouched.
        self::assertStringContainsString('AS ibl_plr_name', $result);
        self::assertDoesNotMatchRegularExpression('/(?<![A-Za-z0-9_])ibl_plr\./', $result);
    }

    public function testRewriteTableNamesLeavesBareReferencesOnIbl(): void
    {
        // Regression guard for the NavigationRepository::resolveTeamId fatal:
        // identity/GM lookups reference the table BARE (no backticks) precisely
        // so they stay on IBL even in Olympics context — the IBL-only
        // `gm_username` column does not exist on ibl_olympics_team_info.
        // Backtick-quoting is the opt-in signal; bare references must NOT be
        // rewritten.
        $context = self::createStub(LeagueContext::class);
        $context->method('isOlympics')->willReturn(true);

        $repo = new TestableBaseMysqliRepository($this->db, $context);

        $query = "SELECT teamid FROM ibl_team_info WHERE gm_username = ?";
        $result = $repo->callRewriteTableNames($query);

        self::assertSame($query, $result, 'bare (non-backticked) table refs must stay on IBL');

        // This bare query must remain executable against the live IBL schema.
        $stmt = $this->db->prepare($result);
        self::assertNotFalse($stmt, 'bare IBL query failed to prepare: ' . $result);
        $stmt->close();
    }

    public function testRewriteTableNamesIsIdempotent(): void
    {
        $context = self::createStub(LeagueContext::class);
        $context->method('isOlympics')->willReturn(true);

        $repo = new TestableBaseMysqliRepository($this->db, $context);

        $query = "SELECT ibl_team_info.* FROM `ibl_team_info` "
            . "LEFT JOIN `ibl_standings` ON ibl_team_info.teamid = ibl_standings.teamid";
        $once = $repo->callRewriteTableNames($query);
        $twice = $repo->callRewriteTableNames($once);

        self::assertSame($once, $twice, 'rewriteTableNames must be idempotent');
        self::assertNoUnprefixedIblTable($once);
    }

    public function testRewriteTableNamesProducesExecutableQueryForColumnQualifiedJoin(): void
    {
        // Gold-standard regression: the exact Team::load() shape that fataled in
        // CI ("Unknown table 'ibl5.ibl_team_info'"). After rewriting it must run.
        $context = self::createStub(LeagueContext::class);
        $context->method('isOlympics')->willReturn(true);

        $repo = new TestableBaseMysqliRepository($this->db, $context);

        $query = "SELECT ibl_team_info.*, ibl_standings.league_record "
            . "FROM `ibl_team_info` "
            . "LEFT JOIN `ibl_standings` ON ibl_team_info.teamid = ibl_standings.teamid "
            . "WHERE ibl_team_info.teamid = ? LIMIT 1";
        $rewritten = $repo->callRewriteTableNames($query);

        self::assertNoUnprefixedIblTable($rewritten);

        // Executing the rewritten query against the live schema must not throw.
        $stmt = $this->db->prepare($rewritten);
        self::assertNotFalse($stmt, 'rewritten query failed to prepare: ' . $rewritten);
        $teamid = 1;
        $stmt->bind_param('i', $teamid);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Assert no mapped IBL table name survives as a standalone identifier token.
     */
    private static function assertNoUnprefixedIblTable(string $query): void
    {
        foreach (array_keys(LeagueContext::TABLE_MAP) as $iblTable) {
            $pattern = '/(?<![A-Za-z0-9_])' . preg_quote($iblTable, '/') . '(?![A-Za-z0-9_])/';
            self::assertDoesNotMatchRegularExpression(
                $pattern,
                $query,
                "Un-rewritten IBL table '$iblTable' survived: $query"
            );
        }
    }

    public function testRewriteTableNamesUsesSharedContextFallback(): void
    {
        $context = self::createStub(LeagueContext::class);
        $context->method('isOlympics')->willReturn(true);

        \BaseMysqliRepository::setSharedLeagueContext($context);

        $repo = new TestableBaseMysqliRepository($this->db);
        $query = "SELECT * FROM `ibl_plr` WHERE pid = ?";
        $result = $repo->callRewriteTableNames($query);

        self::assertStringContainsString('`ibl_olympics_plr`', $result);
    }

    public function testNonMappedTablesAreNeverRewrittenUnderOlympicsContext(): void
    {
        // Tables with no LeagueContext::TABLE_MAP entry have no Olympics
        // equivalent (ibl_sim_dates, ibl_jsb_draft_results, ibl_jsb_retired_players,
        // ibl_jsb_hall_of_fame, ibl_plb_snapshots). They must pass through the
        // rewrite untouched even when backtick-quoted under Olympics, or queries
        // would fatal on a non-existent table.
        $context = self::createStub(LeagueContext::class);
        $context->method('isOlympics')->willReturn(true);
        \BaseMysqliRepository::setSharedLeagueContext($context);

        $repo = new TestableBaseMysqliRepository($this->db);

        foreach (['ibl_sim_dates', 'ibl_jsb_draft_results', 'ibl_jsb_retired_players', 'ibl_jsb_hall_of_fame', 'ibl_plb_snapshots'] as $nonMapped) {
            $query = "SELECT * FROM `{$nonMapped}` LIMIT 1";
            $result = $repo->callRewriteTableNames($query);
            self::assertSame($query, $result, "Non-mapped table '{$nonMapped}' must not be rewritten");
        }
    }

    public function testFetchAllRealTeamsUsesOlympicsTableWithOlympicsContext(): void
    {
        $context = self::createStub(LeagueContext::class);
        $context->method('isOlympics')->willReturn(true);

        $repo = new TestableBaseMysqliRepository($this->db, $context);
        $repo->callFetchAllRealTeams();

        self::assertNotNull($repo->lastPreparedQuery);
        self::assertStringContainsString('`ibl_olympics_team_info`', $repo->lastPreparedQuery);
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

    protected function tearDown(): void
    {
        \Logging\LoggerFactory::reset();
        parent::tearDown();
    }

    // ==================== Perf Logger (slow-query seam) ====================

    public function testInjectedPerfLoggerReceivesSlowQueryRecord(): void
    {
        \Logging\LoggerFactory::reset();
        $handler = new TestHandler();
        $spy = new Logger('perf_spy', [$handler]);
        $this->repo->setPerfLogger($spy);

        $stmt = $this->repo->callExecuteQuery('SELECT SLEEP(0.25)', '');
        $stmt->close();

        $warnings = array_filter(
            $handler->getRecords(),
            static fn ($r): bool => $r->message === 'slow_query',
        );
        $this->assertCount(1, $warnings, 'Expected exactly one slow_query warning');
        $record = array_values($warnings)[0];
        $context = $record->context;
        $this->assertArrayHasKey('action', $context);
        $this->assertArrayHasKey('elapsed_ms', $context);
        $this->assertArrayHasKey('query', $context);
        $this->assertArrayHasKey('repository', $context);
    }

    public function testNoInjectedPerfLoggerStillLogsViaFallbackWithoutFatal(): void
    {
        \Logging\LoggerFactory::reset();

        // No exception thrown — the ?? fallback resolved a real logger; the log was NOT silently dropped.
        $stmt = $this->repo->callExecuteQuery('SELECT SLEEP(0.25)', '');
        $this->assertInstanceOf(\mysqli_stmt::class, $stmt);
        $stmt->close();
    }

    public function testFastQueryDoesNotLogSlowQuery(): void
    {
        \Logging\LoggerFactory::reset();
        $handler = new TestHandler();
        $spy = new Logger('perf_spy', [$handler]);
        $this->repo->setPerfLogger($spy);

        $stmt = $this->repo->callExecuteQuery('SELECT 1', '');
        $stmt->close();

        $warnings = array_filter(
            $handler->getRecords(),
            static fn ($r): bool => $r->message === 'slow_query',
        );
        $this->assertCount(0, $warnings, 'Fast query must not trigger slow_query log');
    }
}

/**
 * @internal Test double exposing protected BaseMysqliRepository methods.
 */
class TestableBaseMysqliRepository extends \BaseMysqliRepository
{
    public ?string $lastPreparedQuery = null;

    protected function executeQuery(string $query, string $types = '', mixed ...$params): \mysqli_stmt
    {
        $this->lastPreparedQuery = $this->rewriteTableNames($query);
        return parent::executeQuery($query, $types, ...$params);
    }

    public function callRewriteTableNames(string $query): string
    {
        return $this->rewriteTableNames($query);
    }

    public function callExecuteQuery(string $query, string $types = '', mixed ...$params): object
    {
        return $this->executeQuery($query, $types, ...$params);
    }

    /** @return array<string, mixed>|null */
    public function callFetchOne(string $query, string $types = '', mixed ...$params): ?array
    {
        return $this->fetchOne($query, $types, ...$params);
    }

    /** @return array<int, array<string, mixed>> */
    public function callFetchAll(string $query, string $types = '', mixed ...$params): array
    {
        return $this->fetchAll($query, $types, ...$params);
    }

    public function callExecute(string $query, string $types = '', mixed ...$params): int
    {
        return $this->execute($query, $types, ...$params);
    }

    /** @return list<array<string, mixed>> */
    public function callFetchAllRealTeams(\TeamOrderBy $orderBy = \TeamOrderBy::TeamName): array
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
