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
