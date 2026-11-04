<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Waivers;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Waivers\WaiversSubmissionService;
use Waivers\WaiversTeamLockRepository;

/**
 * Two-connection proof that the per-team row lock serializes waiver submissions
 * (backlog#1570). Connection A is DatabaseTestCase's `$this->db`; connection B is opened
 * here with a one-second lock wait so a blocked read fails fast with error 1205.
 *
 * Only committed rows are visible to connection B, so these tests lock the pre-existing
 * teams 1 and 2 and never insert into ibl_team_info.
 */
#[Group('database')]
class WaiversTeamRosterLockIntegrationTest extends DatabaseTestCase
{
    private const ER_LOCK_WAIT_TIMEOUT = 1205;
    private const TEST_PID_BASE = 200070100;
    private const WAIVED_PLAYER = [
        'teamid' => 0,
        'cy' => 0,
        'cyt' => 0,
        'salary_yr1' => 0,
        'salary_yr2' => 0,
        'exp' => 5,
        'bird' => 0,
        'ordinal' => 1000,
        'droptime' => 1000000,
    ];

    /** @var list<\mysqli> */
    private array $connections = [];

    protected function tearDown(): void
    {
        foreach ($this->connections as $conn) {
            try {
                $conn->rollback();
            } catch (\mysqli_sql_exception $e) {
                // Connection already gone; nothing left to release, close() below still runs.
                unset($e);
            }
            $conn->close();
        }
        $this->connections = [];

        parent::tearDown();
    }

    public function testPlainRosterCountDoesNotBlockSecondConnection(): void
    {
        $sql = 'SELECT COUNT(*) AS n FROM ibl_plr WHERE teamid = 1 AND retired = 0';

        $resultA = $this->db->query($sql);
        $this->assertInstanceOf(\mysqli_result::class, $resultA);
        $rowA = $resultA->fetch_assoc();
        $this->assertIsArray($rowA);

        $b = $this->openLockedWaitConnection();
        $resultB = $b->query($sql);
        $this->assertInstanceOf(\mysqli_result::class, $resultB);
        $rowB = $resultB->fetch_assoc();
        $this->assertIsArray($rowB);

        $this->assertSame($rowA['n'], $rowB['n']);
    }

    public function testTeamRowForUpdateBlocksSecondConnectionUntilRelease(): void
    {
        $this->db->query('SELECT teamid FROM ibl_team_info WHERE teamid = 1 FOR UPDATE');

        $b = $this->openLockedWaitConnection();
        $this->assertSame(self::ER_LOCK_WAIT_TIMEOUT, $this->lockErrorCode($b, 1));

        $this->db->rollback();
        $this->db->begin_transaction();

        $result = $b->query('SELECT teamid FROM ibl_team_info WHERE teamid = 1 FOR UPDATE');
        $this->assertInstanceOf(\mysqli_result::class, $result);
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $this->assertCount(1, $rows);
        $this->assertSame(1, $rows[0]['teamid']);
    }

    public function testTeamRowLockDoesNotBlockADifferentTeam(): void
    {
        $this->db->query('SELECT teamid FROM ibl_team_info WHERE teamid = 1 FOR UPDATE');

        $b = $this->openLockedWaitConnection();
        $result = $b->query('SELECT teamid FROM ibl_team_info WHERE teamid = 2 FOR UPDATE');
        $this->assertInstanceOf(\mysqli_result::class, $result);
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows[0]['teamid']);
    }

    public function testWithTeamLockHoldsTeamRowLockWhileCallbackRuns(): void
    {
        $b = $this->openLockedWaitConnection();
        $code = null;

        $result = (new WaiversTeamLockRepository($this->db))->withTeamLock(
            1,
            function () use ($b, &$code): string {
                $code = $this->lockErrorCode($b, 1);

                return 'ran';
            }
        );

        $this->assertSame(self::ER_LOCK_WAIT_TIMEOUT, $code);
        $this->assertSame('ran', $result);
    }

    public function testWithTeamLockReturnsCallbackResult(): void
    {
        $result = (new WaiversTeamLockRepository($this->db))->withTeamLock(
            1,
            static fn (): array => ['success' => true, 'result' => 'player_added']
        );

        $this->assertSame(['success' => true, 'result' => 'player_added'], $result);
    }

    public function testWithTeamLockReturnsNullAndSkipsCallbackForUnknownTeam(): void
    {
        $precondition = $this->db->query('SELECT teamid FROM ibl_team_info WHERE teamid = 987654');
        $this->assertInstanceOf(\mysqli_result::class, $precondition);
        $this->assertSame(0, $precondition->num_rows);

        $called = false;
        $result = (new WaiversTeamLockRepository($this->db))->withTeamLock(
            987654,
            function () use (&$called): string {
                $called = true;

                return 'ran';
            }
        );

        $this->assertNull($result);
        $this->assertFalse($called);
    }

    public function testWithTeamLockRollsBackWritesWhenCallbackThrows(): void
    {
        $before = $this->teamCity(1);

        $caught = null;
        try {
            (new WaiversTeamLockRepository($this->db))->withTeamLock(
                1,
                function (): never {
                    $this->db->query("UPDATE ibl_team_info SET team_city = 'LockRollbackCity' WHERE teamid = 1");

                    throw new \RuntimeException('boom');
                }
            );
        } catch (\RuntimeException $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(\RuntimeException::class, $caught);
        $this->assertSame('boom', $caught->getMessage());
        $this->assertSame($before, $this->teamCity(1));
    }

    public function testSubmitHoldsTeamRowLockWhileProcessorRuns(): void
    {
        $nameResult = $this->db->query('SELECT team_name FROM ibl_team_info WHERE teamid = 1');
        $this->assertInstanceOf(\mysqli_result::class, $nameResult);
        $nameRow = $nameResult->fetch_assoc();
        $this->assertIsArray($nameRow);
        $teamName = $nameRow['team_name'];
        $this->assertIsString($teamName);

        $b = $this->openLockedWaitConnection();
        $code = null;

        $processor = $this->createMock(\Waivers\Contracts\WaiversProcessorInterface::class);
        $processor->expects($this->once())->method('processAdd')->willReturnCallback(
            function () use ($b, &$code): array {
                $code = $this->lockErrorCode($b, 1);

                return ['success' => true, 'result' => 'player_added'];
            }
        );

        $season = self::createStub(\Season\Season::class);
        $season->method('advancesContractYears')->willReturn(false);

        $service = new WaiversSubmissionService(
            $processor,
            new \Repositories\SalaryCapRepository($this->db),
            $season,
            new \Waivers\WaiversRosterSlotCounter(new \Team\TeamQueryRepository($this->db)),
            new \Repositories\TeamIdentityRepository($this->db),
            new WaiversTeamLockRepository($this->db)
        );

        $result = $service->submit(['Action' => 'add', 'Player_ID' => '42'], $teamName);

        $this->assertSame(self::ER_LOCK_WAIT_TIMEOUT, $code);
        $this->assertSame(['success' => true, 'result' => 'player_added'], $result);
    }

    /**
     * A mail failure comes after the lock, so the signing and its news story stand. Under the
     * outer test transaction a thrown mail error would roll back to the lock's savepoint and
     * undo both rows, which is what this checks.
     */
    public function testThrowingMailerKeepsSigningAndNewsStoryAndStillPostsDiscord(): void
    {
        $pid = self::TEST_PID_BASE + 1;
        $this->insertTestPlayer($pid, 'Mail Failure Pickup', self::WAIVED_PLAYER);
        $this->insertRow('nuke_stories_cat', ['title' => 'Waiver Pool Moves']);
        $discordPosts = 0;

        $result = $this->submitWithRealProcessor(
            $pid,
            static function (): \Mail\Contracts\MailServiceInterface {
                throw new \RuntimeException('mail down');
            },
            static function () use (&$discordPosts): void {
                $discordPosts++;
            }
        );

        $this->assertSame(['success' => true, 'result' => 'player_added'], $result);
        $this->assertSame(1, $this->playerTeamId($pid));
        $this->assertSame(1, $this->newsStoryCount('Mail Failure Pickup'));
        $this->assertSame(1, $discordPosts);
    }

    /**
     * The signing and news story share the lock's transaction. A failure after both are
     * written undoes both, sends nothing, and leaves no audit entry saying the move happened.
     */
    public function testFailureInsideLockRollsBackSigningAndNewsStoryAndSendsNothing(): void
    {
        $pid = self::TEST_PID_BASE + 2;
        $this->insertTestPlayer($pid, 'Rolled Back Pickup', self::WAIVED_PLAYER);
        $this->insertRow('nuke_stories_cat', ['title' => 'Waiver Pool Moves']);
        $sent = 0;
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $realLock = new WaiversTeamLockRepository($this->db);
        $failingLock = new class ($realLock) implements \Waivers\Contracts\WaiversTeamLockInterface {
            public function __construct(private readonly WaiversTeamLockRepository $lock)
            {
            }

            public function withTeamLock(int $teamId, callable $fn): mixed
            {
                return $this->lock->withTeamLock($teamId, static function () use ($fn): mixed {
                    $fn();

                    throw new \RuntimeException('failed before commit');
                });
            }
        };

        try {
            $this->submitWithRealProcessor(
                $pid,
                static function () use (&$sent): \Mail\Contracts\MailServiceInterface {
                    $sent++;

                    return self::createStub(\Mail\Contracts\MailServiceInterface::class);
                },
                static function () use (&$sent): void {
                    $sent++;
                },
                $logger,
                $failingLock
            );
            self::fail('The failure before commit must reach the caller.');
        } catch (\RuntimeException $e) {
            $this->assertSame('failed before commit', $e->getMessage());
        }

        $this->assertSame(0, $this->playerTeamId($pid));
        $this->assertSame(0, $this->newsStoryCount('Rolled Back Pickup'));
        $this->assertSame(0, $sent);
    }

    /**
     * InnoDB's foreign-key check takes a shared lock on the parent team row, so the team lock
     * also holds up other features' writes that point a row at that team. B inserts its own
     * uncommitted player first, so nothing is seeded and everything rolls back.
     */
    public function testForeignKeyMoveOntoLockedTeamWaits(): void
    {
        $b = $this->openLockedWaitConnection();
        $pid = $this->insertPlayerOnConnection($b, 2);

        $code = (new WaiversTeamLockRepository($this->db))->withTeamLock(
            1,
            fn (): ?int => $this->queryErrorCode($b, "UPDATE ibl_plr SET teamid = 1 WHERE pid = {$pid}")
        );

        $this->assertSame(self::ER_LOCK_WAIT_TIMEOUT, $code);
    }

    public function testForeignKeyChildInsertForLockedTeamWaits(): void
    {
        $b = $this->openLockedWaitConnection();
        $pid = $this->insertPlayerOnConnection($b, 2);

        $code = (new WaiversTeamLockRepository($this->db))->withTeamLock(
            1,
            fn (): ?int => $this->queryErrorCode($b, "INSERT INTO ibl_fa_offers (pid, teamid) VALUES ({$pid}, 1)")
        );

        $this->assertSame(self::ER_LOCK_WAIT_TIMEOUT, $code);
    }

    public function testLockedTeamDoesNotBlockPlainReadOrWritesOntoAnotherTeam(): void
    {
        $b = $this->openLockedWaitConnection();
        $pid = $this->insertPlayerOnConnection($b, 2);

        $codes = (new WaiversTeamLockRepository($this->db))->withTeamLock(
            1,
            fn (): array => [
                $this->queryErrorCode($b, 'SELECT teamid FROM ibl_team_info WHERE teamid = 1'),
                $this->queryErrorCode($b, "UPDATE ibl_plr SET teamid = 2, ordinal = 2 WHERE pid = {$pid}"),
                $this->queryErrorCode($b, "INSERT INTO ibl_fa_offers (pid, teamid) VALUES ({$pid}, 2)"),
            ]
        );

        $this->assertSame([null, null, null], $codes);
    }

    /**
     * @param \Closure(): \Mail\Contracts\MailServiceInterface $mailerFactory
     * @param \Closure(string, string): void $discordPoster
     * @return array{success: bool, result?: string, error?: string}
     */
    private function submitWithRealProcessor(
        int $pid,
        \Closure $mailerFactory,
        \Closure $discordPoster,
        ?\Psr\Log\LoggerInterface $logger = null,
        ?\Waivers\Contracts\WaiversTeamLockInterface $teamLock = null
    ): array {
        $season = self::createStub(\Season\Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $season->method('isOffseasonPhase')->willReturn(false);
        $teamIdentityRepo = new \Repositories\TeamIdentityRepository($this->db);

        $processor = new \Waivers\WaiversProcessor(
            new \Waivers\WaiversRepository($this->db),
            $teamIdentityRepo,
            new \Repositories\PlayerLookupRepository($this->db),
            new \Waivers\WaiversValidator(),
            new \Topics\News\NewsRepository($this->db),
            $this->db,
            $logger ?? self::createStub(\Psr\Log\LoggerInterface::class),
            $season,
            null,
            $mailerFactory,
            $discordPoster
        );

        $service = new WaiversSubmissionService(
            $processor,
            new \Repositories\SalaryCapRepository($this->db),
            $season,
            new \Waivers\WaiversRosterSlotCounter(new \Team\TeamQueryRepository($this->db)),
            $teamIdentityRepo,
            $teamLock ?? new WaiversTeamLockRepository($this->db)
        );

        return $service->submit(['Action' => 'add', 'Player_ID' => (string) $pid], $this->teamName(1));
    }

    private function teamName(int $teamId): string
    {
        return $this->stringColumn('SELECT team_name AS v FROM ibl_team_info WHERE teamid = ' . $teamId);
    }

    private function playerTeamId(int $pid): int
    {
        return (int) $this->stringColumn('SELECT teamid AS v FROM ibl_plr WHERE pid = ' . $pid);
    }

    private function newsStoryCount(string $playerName): int
    {
        return (int) $this->stringColumn(
            "SELECT COUNT(*) AS v FROM nuke_stories WHERE hometext LIKE '%" . $playerName . "%'"
        );
    }

    private function stringColumn(string $sql): string
    {
        $result = $this->db->query($sql);
        $this->assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $this->assertIsArray($row);
        $this->assertTrue(is_scalar($row['v']));

        return (string) $row['v'];
    }

    /**
     * Connection B's own uncommitted player, so the writes under test have a row B can see.
     */
    private function insertPlayerOnConnection(\mysqli $conn, int $teamId): int
    {
        $pid = self::TEST_PID_BASE + 50;
        $uuid = sprintf('test-%09d-0000-000000000001', $pid);
        $conn->query(
            "INSERT INTO ibl_plr (pid, name, teamid, ordinal, uuid) VALUES ({$pid}, 'Lock Probe', {$teamId}, 1, '{$uuid}')"
        );

        return $pid;
    }

    /**
     * Run one statement on $conn. Returns the failure's error code, or null when it succeeded.
     */
    private function queryErrorCode(\mysqli $conn, string $sql): ?int
    {
        try {
            $conn->query($sql);
        } catch (\mysqli_sql_exception $e) {
            return $e->getCode();
        }

        return null;
    }

    /**
     * Connection B: a one-second lock wait and an open transaction.
     */
    private function openLockedWaitConnection(): \mysqli
    {
        $b = $this->openMysqli();
        $b->query('SET SESSION innodb_lock_wait_timeout = 1');
        $b->begin_transaction();

        return $b;
    }

    /**
     * Try to lock a team row on $conn. Returns the error code of the failed attempt, or null
     * when the lock was granted.
     */
    private function lockErrorCode(\mysqli $conn, int $teamId): ?int
    {
        try {
            $conn->query('SELECT teamid FROM ibl_team_info WHERE teamid = ' . $teamId . ' FOR UPDATE');
        } catch (\mysqli_sql_exception $e) {
            return $e->getCode();
        }

        return null;
    }

    private function teamCity(int $teamId): string
    {
        $result = $this->db->query('SELECT team_city FROM ibl_team_info WHERE teamid = ' . $teamId);
        $this->assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $this->assertIsArray($row);
        $city = $row['team_city'];
        $this->assertIsString($city);

        return $city;
    }

    private function openMysqli(): \mysqli
    {
        $conn = new \mysqli();
        $conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        $conn->real_connect(
            self::env('DB_HOST'),
            self::env('DB_USER'),
            self::env('DB_PASS'),
            self::env('DB_NAME')
        );
        if ($conn->connect_errno !== 0) {
            self::fail('Database connection failed: ' . $conn->connect_error);
        }
        $this->connections[] = $conn;

        return $conn;
    }

    private static function env(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            self::fail("Environment variable $name is not set; database integration tests need a real MariaDB connection.");
        }

        return $value;
    }
}
