<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\Waivers;

use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;
use Waivers\WaiversSubmissionService;
use Waivers\WaiversTeamLockRepository;

/**
 * Two real `WaiversSubmissionService::submit()` calls for one team on two connections
 * (backlog#1754, follows backlog#1570).
 *
 * Connection A runs in-process and holds the team row lock after counting and writing its
 * signing. Connection B is a child PHP process that blocks on `SELECT ... FOR UPDATE` for the
 * same team row. Once B is observed in an InnoDB lock wait, A commits. B's first consistent
 * read (the roster count) then runs after the wait, so its REPEATABLE READ snapshot includes
 * A's signing and B is rejected as roster-full.
 *
 * B only sees committed rows, so the race test commits its fixtures on fresh connections and
 * deletes them itself. `setUp()` pre-cleans the reserved pid range and `tearDown()` asserts it
 * is empty again.
 */
#[Group('database')]
class WaiversTwoConnectionSubmitRaceTest extends DatabaseTestCase
{
    private const TEST_PID_BASE = 200070300;
    private const TEST_PID_LAST = self::TEST_PID_BASE + 99;
    private const TEAM_ID = 1;
    private const ROSTER_FULL_ERROR = "You have a full roster. You can't sign another player at this time!";
    private const FILLER_PLAYER = [
        'teamid' => 1,
        'ordinal' => 500,
        'retired' => 0,
        'injured' => 1,
        'cy' => 1,
        'cyt' => 1,
        'salary_yr1' => 0,
    ];
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
    private const PLAYER_DEFAULTS = [
        'age' => 27,
        'pos' => 'PG',
        'stamina' => 80,
        'exp' => 5,
        'bird' => 3,
        'cy' => 1,
        'cyt' => 3,
        'salary_yr1' => 1500,
        'salary_yr2' => 1600,
        'retired' => 0,
        'ordinal' => 1,
        'droptime' => 0,
    ];

    /** @var list<\mysqli> */
    private array $connections = [];

    private bool $lockWaitObserved = false;

    protected function setUp(): void
    {
        parent::setUp();

        // A crashed earlier run must not poison this one.
        $this->cleanupCommittedFixtures();
    }

    protected function tearDown(): void
    {
        $failure = null;
        try {
            $this->releaseFixturesAndConnections();
        } catch (\Throwable $e) {
            $failure = $e;
        }

        parent::tearDown();

        if ($failure !== null) {
            throw $failure;
        }
    }

    private function releaseFixturesAndConnections(): void
    {
        try {
            // Release this test's own uncommitted rows first, or the cleanup below waits on them.
            $this->db->rollback();
            $this->cleanupCommittedFixtures();

            $check = $this->openConnection();
            $this->assertSame(
                0,
                $this->scalarInt($check, $this->fixtureCountSql()),
                'tearDown must leave no ibl_plr rows in the reserved pid range'
            );
        } finally {
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
        }
    }

    /**
     * Serial boundary: with team 1 at one open slot, the first add is accepted and the second
     * is rejected. Pins the roster-full error string the race test compares against.
     */
    public function testSerialSecondAddAfterLastOpenSlotIsRejected(): void
    {
        $open = $this->openSlots($this->db);
        $this->assertGreaterThanOrEqual(1, $open, 'team 1 needs one open slot for this fixture');

        for ($i = 0; $i < $open - 1; $i++) {
            $this->insertTestPlayer(
                self::TEST_PID_BASE + 50 + $i,
                sprintf('Race Filler %02d', $i),
                self::FILLER_PLAYER
            );
        }
        $this->assertSame(1, $this->openSlots($this->db));

        $pidA = self::TEST_PID_BASE + 70;
        $pidB = self::TEST_PID_BASE + 71;
        $this->insertTestPlayer($pidA, 'Serial Pickup A', self::WAIVED_PLAYER);
        $this->insertTestPlayer($pidB, 'Serial Pickup B', self::WAIVED_PLAYER);

        $teamName = $this->teamName($this->db);
        $service = $this->buildService($this->db);

        $first = $service->submit(['Action' => 'add', 'Player_ID' => (string) $pidA], $teamName);
        $this->assertSame(['success' => true, 'result' => 'player_added'], $first);

        $second = $service->submit(['Action' => 'add', 'Player_ID' => (string) $pidB], $teamName);
        $this->assertSame(['success' => false, 'error' => self::ROSTER_FULL_ERROR], $second);
    }

    /**
     * The child process must work before the race depends on it: autoload, env, connection, and
     * service graph all run, and a failure is reported as JSON with exit code 0.
     */
    public function testWorkerRejectsUnknownTeamAndExitsZero(): void
    {
        [$proc, $pipes] = $this->startWorker('NoSuchRaceTeam', self::TEST_PID_BASE + 99);

        $line = fgets($pipes[1]);
        $this->assertIsString($line);
        $this->assertMatchesRegularExpression('/^\d+$/', trim($line));

        $done = $this->finishWorker($proc, $pipes);

        $this->assertSame('', $done['stderr']);
        $this->assertSame(0, $done['exit']);
        $this->assertSame(
            ['success' => false, 'error' => 'Unable to determine your team.'],
            json_decode(trim($done['stdout']), true)
        );
    }

    /**
     * A signs the last open slot and holds the lock; B (child process) blocks on the team row
     * and, once A commits, must count a roster that already includes A's signing.
     */
    public function testWaitingSubmitCountsRosterIncludingFirstSigning(): void
    {
        $fx = $this->openConnection();
        $seed = $this->seedCommittedRoster($fx);
        $connA = $this->openConnection();
        $obs = $this->openConnection();

        /** @var resource|null $proc */
        $proc = null;
        /** @var array<int, resource> $pipes */
        $pipes = [];
        $this->lockWaitObserved = false;

        $whileHoldingLock = function () use (&$proc, &$pipes, $seed, $obs): void {
            [$proc, $pipes] = $this->startWorker($seed['teamName'], $seed['pidB']);
            $line = fgets($pipes[1]);
            if ($line === false) {
                $done = $this->finishWorker($proc, $pipes);
                self::fail('worker produced no connection id; stderr: ' . $done['stderr']);
            }
            $threadId = (int) trim($line);

            // INNODB_TRX does not list this thread while it waits on the row lock (MariaDB 10.11),
            // so watch its own statement in PROCESSLIST. A lock-free FOR UPDATE finishes in
            // well under a millisecond; the same statement on two polls 50 ms apart is a wait.
            $deadline = hrtime(true) + 10_000_000_000;
            $consecutive = 0;
            while (hrtime(true) < $deadline) {
                $result = $obs->query(
                    'SELECT INFO AS info FROM information_schema.PROCESSLIST WHERE ID = ' . $threadId
                );
                $row = $result instanceof \mysqli_result ? $result->fetch_assoc() : null;
                $blocked = is_array($row)
                    && is_string($row['info'])
                    && str_contains($row['info'], 'FOR UPDATE')
                    && str_contains($row['info'], 'ibl_team_info');
                $consecutive = $blocked ? $consecutive + 1 : 0;
                if ($consecutive >= 2) {
                    $this->lockWaitObserved = true;
                    break;
                }
                if (proc_get_status($proc)['running'] === false) {
                    break;
                }
                usleep(50000);
            }
        };

        $decorated = new class (new WaiversTeamLockRepository($connA), $whileHoldingLock) implements \Waivers\Contracts\WaiversTeamLockInterface {
            public function __construct(
                private readonly WaiversTeamLockRepository $lock,
                private readonly \Closure $after
            ) {
            }

            public function withTeamLock(int $teamId, callable $fn): mixed
            {
                return $this->lock->withTeamLock($teamId, function () use ($fn): mixed {
                    $result = $fn();      // A's real count + signing, uncommitted, team row locked
                    ($this->after)();     // start B and wait until B blocks on the team lock

                    return $result;       // transactional() then COMMITs A
                });
            }
        };

        try {
            $resultA = $this->buildService($connA, $decorated)->submit(
                ['Action' => 'add', 'Player_ID' => (string) $seed['pidA']],
                $seed['teamName']
            );
            $this->assertNotNull($proc, 'connection B was never started');
            $b = $this->finishWorker($proc, $pipes);

            $this->assertSame(['success' => true, 'result' => 'player_added'], $resultA);

            $this->assertSame('', $b['stderr']);
            $this->assertSame(0, $b['exit']);
            $this->assertSame(
                ['success' => false, 'error' => self::ROSTER_FULL_ERROR],
                json_decode(trim($b['stdout']), true)
            );

            $check = $this->openConnection();
            $this->assertSame(self::TEAM_ID, $this->playerTeamId($check, $seed['pidA']));
            $this->assertSame(0, $this->playerTeamId($check, $seed['pidB']));
            $this->assertSame(0, $this->openSlots($check));

            $this->assertTrue($this->lockWaitObserved, 'B never entered a lock wait on the ibl_team_info row');
        } finally {
            if (is_resource($proc)) {
                $status = proc_get_status($proc);
                if ($status['running']) {
                    proc_terminate($proc);
                }
            }
        }
    }

    private function openConnection(): \mysqli
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

    private function fixtureCountSql(): string
    {
        return 'SELECT COUNT(*) AS v FROM ibl_plr WHERE pid BETWEEN ' . self::TEST_PID_BASE . ' AND ' . self::TEST_PID_LAST;
    }

    /**
     * Remove every committed row this test can leave behind, on a fresh connection.
     */
    private function cleanupCommittedFixtures(): void
    {
        $conn = $this->openConnection();
        $conn->query('DELETE FROM ibl_plr WHERE pid BETWEEN ' . self::TEST_PID_BASE . ' AND ' . self::TEST_PID_LAST);
        // A successful add writes a news story only when the 'Waiver Pool Moves' category exists.
        $conn->query("DELETE FROM nuke_stories WHERE hometext LIKE '%Race Pickup%'");
    }

    /**
     * @param array<string, int|string> $columns
     */
    private function insertCommittedPlayer(\mysqli $conn, int $pid, string $name, array $columns): void
    {
        $row = array_merge(
            self::PLAYER_DEFAULTS,
            ['pid' => $pid, 'name' => $name, 'uuid' => sprintf('test-%09d-0000-000000000001', $pid)],
            $columns
        );
        $names = implode(', ', array_map(static fn (string $col): string => "`$col`", array_keys($row)));
        $values = implode(', ', array_map(
            static fn (int|string $v): string => is_int($v) ? (string) $v : "'" . str_replace("'", "''", $v) . "'",
            array_values($row)
        ));
        $conn->query("INSERT INTO ibl_plr ($names) VALUES ($values)");
    }

    /**
     * Commit a roster with exactly one open slot on team 1 plus two waived players.
     *
     * @return array{teamName: string, pidA: int, pidB: int}
     */
    private function seedCommittedRoster(\mysqli $fx): array
    {
        $open = $this->openSlots($fx);
        $this->assertGreaterThanOrEqual(1, $open, 'team 1 needs one open slot for this fixture');

        for ($i = 0; $i < $open - 1; $i++) {
            $this->insertCommittedPlayer($fx, self::TEST_PID_BASE + $i, sprintf('Race Filler %02d', $i), self::FILLER_PLAYER);
        }
        $this->assertSame(1, $this->openSlots($fx));

        $pidA = self::TEST_PID_BASE + 20;
        $pidB = self::TEST_PID_BASE + 21;
        $this->insertCommittedPlayer($fx, $pidA, 'Race Pickup A', self::WAIVED_PLAYER);
        $this->insertCommittedPlayer($fx, $pidB, 'Race Pickup B', self::WAIVED_PLAYER);

        return ['teamName' => $this->teamName($fx), 'pidA' => $pidA, 'pidB' => $pidB];
    }

    private function buildService(
        \mysqli $conn,
        ?\Waivers\Contracts\WaiversTeamLockInterface $lock = null
    ): WaiversSubmissionService {
        $season = $this->season();
        $teamIdentityRepo = new \Repositories\TeamIdentityRepository($conn);

        $processor = new \Waivers\WaiversProcessor(
            new \Waivers\WaiversRepository($conn),
            $teamIdentityRepo,
            new \Repositories\PlayerLookupRepository($conn),
            new \Waivers\WaiversValidator(),
            new \Topics\News\NewsRepository($conn),
            $conn,
            self::createStub(\Psr\Log\LoggerInterface::class),
            $season,
            null,
            static fn (): \Mail\Contracts\MailServiceInterface => self::createStub(\Mail\Contracts\MailServiceInterface::class),
            static function (): void {
            }
        );

        return new WaiversSubmissionService(
            $processor,
            new \Repositories\SalaryCapRepository($conn),
            $season,
            new \Waivers\WaiversRosterSlotCounter(new \Team\TeamQueryRepository($conn)),
            $teamIdentityRepo,
            $lock ?? new WaiversTeamLockRepository($conn)
        );
    }

    private function season(): \Season\Season
    {
        $season = self::createStub(\Season\Season::class);
        $season->method('advancesContractYears')->willReturn(false);
        $season->method('isOffseasonPhase')->willReturn(false);

        return $season;
    }

    /**
     * Open slots from the real slot counter, so the test never restates the roster rule.
     */
    private function openSlots(\mysqli $conn): int
    {
        return (new \Waivers\WaiversRosterSlotCounter(new \Team\TeamQueryRepository($conn)))
            ->countOpenSlots(self::TEAM_ID, $this->season())['open'];
    }

    private function teamName(\mysqli $conn): string
    {
        $result = $conn->query('SELECT team_name AS v FROM ibl_team_info WHERE teamid = ' . self::TEAM_ID);
        $this->assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $this->assertIsArray($row);
        $this->assertIsString($row['v']);

        return $row['v'];
    }

    private function playerTeamId(\mysqli $conn, int $pid): int
    {
        return $this->scalarInt($conn, 'SELECT teamid AS v FROM ibl_plr WHERE pid = ' . $pid);
    }

    private function scalarInt(\mysqli $conn, string $sql): int
    {
        $result = $conn->query($sql);
        $this->assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $this->assertIsArray($row);
        $this->assertTrue(is_scalar($row['v']));

        return (int) $row['v'];
    }

    /**
     * @return array{0: resource, 1: array<int, resource>}
     */
    private function startWorker(string $teamName, int $pid): array
    {
        $envVars = getenv();
        $env = is_array($envVars) ? $envVars : [];
        $worker = __DIR__ . '/Fixtures/waivers-race-submit-worker.php';

        $process = proc_open(
            [PHP_BINARY, $worker, $teamName, (string) $pid],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env
        );
        if ($process === false) {
            self::fail('proc_open failed to launch the worker process');
        }

        return [$process, $pipes];
    }

    /**
     * Drains stdout (after the caller consumed the connection-id line) and stderr, then closes.
     *
     * @param resource $proc
     * @param array<int, resource> $pipes
     * @return array{exit: int, stdout: string, stderr: string}
     */
    private function finishWorker($proc, array $pipes): array
    {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'exit' => proc_close($proc),
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
        ];
    }
}
