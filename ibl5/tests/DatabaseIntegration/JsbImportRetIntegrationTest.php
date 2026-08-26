<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use JsbParser\JsbImportRepository;
use JsbParser\JsbImportService;
use JsbParser\PlayerIdResolver;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class JsbImportRetIntegrationTest extends DatabaseTestCase
{
    private JsbImportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = new JsbImportRepository($this->db);
        $resolver = new PlayerIdResolver($this->db);
        $this->service = new JsbImportService($repository, $resolver);
    }

    public function testRetImportSetsRetiredFlagOnMatchedPlayer(): void
    {
        $this->insertTestPlayer(200000201, 'Ret Wired');

        $stmt = $this->db->prepare('SELECT retired FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $pid = 200000201;
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();
        self::assertSame(0, $row['retired']);

        $importResult = $this->service->processRetData("Ret Wired 200000201\n", 2026);

        self::assertSame(0, $importResult->errors);

        $stmt2 = $this->db->prepare('SELECT retired FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt2);
        $stmt2->bind_param('i', $pid);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        self::assertNotFalse($result2);
        $row2 = $result2->fetch_assoc();
        $stmt2->close();
        self::assertSame(1, $row2['retired']);

        $stmt3 = $this->db->prepare(
            'SELECT jsb_pid, retirement_year, pid FROM ibl_jsb_retired_players WHERE jsb_pid = ?'
        );
        self::assertNotFalse($stmt3);
        $stmt3->bind_param('i', $pid);
        $stmt3->execute();
        $result3 = $stmt3->get_result();
        self::assertNotFalse($result3);
        $auditRow = $result3->fetch_assoc();
        $stmt3->close();
        self::assertNotNull($auditRow);
        self::assertSame(200000201, $auditRow['jsb_pid']);
        self::assertSame(2026, $auditRow['retirement_year']);
        self::assertSame(200000201, $auditRow['pid']);
    }

    public function testRetImportSetsRetiredFlagOnPlayerWithNullRetired(): void
    {
        // `ibl_plr.retired` is `tinyint(1) DEFAULT NULL`, and nothing wrote the
        // column before this PR, so a NULL row is the realistic legacy state.
        // `AND retired = 0` alone is a silent no-op against it, because
        // `NULL = 0` evaluates to NULL in MySQL.
        $this->insertTestPlayer(200000205, 'Null Retired', ['retired' => null]);

        $stmt = $this->db->prepare('SELECT retired FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $pid = 200000205;
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();
        self::assertNull($row['retired'], 'fixture must seed a genuine SQL NULL, not 0');

        $importResult = $this->service->processRetData("Null Retired 200000205\n", 2026);

        self::assertSame(0, $importResult->errors);

        $stmt2 = $this->db->prepare('SELECT retired FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt2);
        $stmt2->bind_param('i', $pid);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        self::assertNotFalse($result2);
        $row2 = $result2->fetch_assoc();
        $stmt2->close();
        self::assertSame(1, $row2['retired']);

        $stmt3 = $this->db->prepare(
            'SELECT jsb_pid, retirement_year, pid FROM ibl_jsb_retired_players WHERE jsb_pid = ?'
        );
        self::assertNotFalse($stmt3);
        $stmt3->bind_param('i', $pid);
        $stmt3->execute();
        $result3 = $stmt3->get_result();
        self::assertNotFalse($result3);
        $auditRow = $result3->fetch_assoc();
        $stmt3->close();
        self::assertNotNull($auditRow);
        self::assertSame(200000205, $auditRow['pid']);
    }

    public function testRetImportIsIdempotentAcrossRepeatedImports(): void
    {
        $this->insertTestPlayer(200000201, 'Ret Wired');
        $pid = 200000201;

        $result1 = $this->service->processRetData("Ret Wired 200000201\n", 2026);
        self::assertSame(0, $result1->errors);

        $result2 = $this->service->processRetData("Ret Wired 200000201\n", 2026);
        self::assertSame(0, $result2->errors);

        $stmt = $this->db->prepare('SELECT retired FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();
        self::assertSame(1, $row['retired']);

        $stmt2 = $this->db->prepare(
            'SELECT COUNT(*) AS cnt FROM ibl_jsb_retired_players WHERE jsb_pid = ?'
        );
        self::assertNotFalse($stmt2);
        $stmt2->bind_param('i', $pid);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        self::assertNotFalse($result2);
        $countRow = $result2->fetch_assoc();
        $stmt2->close();
        self::assertSame(1, (int) $countRow['cnt']);
    }

    public function testRetImportSkipsUnmatchedJsbPid(): void
    {
        $this->insertTestPlayer(200000202, 'Present Player');
        $pid202 = 200000202;

        $result = $this->service->processRetData("Ghost Player 200000999\n", 2026);

        self::assertSame(0, $result->errors);

        $stmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $ghost = 200000999;
        $stmt->bind_param('i', $ghost);
        $stmt->execute();
        $r = $stmt->get_result();
        self::assertNotFalse($r);
        $countRow = $r->fetch_assoc();
        $stmt->close();
        self::assertSame(0, (int) $countRow['cnt']);

        $stmt2 = $this->db->prepare('SELECT retired FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt2);
        $stmt2->bind_param('i', $pid202);
        $stmt2->execute();
        $r2 = $stmt2->get_result();
        self::assertNotFalse($r2);
        $row = $r2->fetch_assoc();
        $stmt2->close();
        self::assertSame(0, $row['retired']);

        $stmt3 = $this->db->prepare(
            'SELECT pid FROM ibl_jsb_retired_players WHERE jsb_pid = ?'
        );
        self::assertNotFalse($stmt3);
        $stmt3->bind_param('i', $ghost);
        $stmt3->execute();
        $r3 = $stmt3->get_result();
        self::assertNotFalse($r3);
        $auditRow = $r3->fetch_assoc();
        $stmt3->close();
        self::assertNotNull($auditRow);
        self::assertNull($auditRow['pid']);
    }

    public function testRetImportLeavesUnrelatedPlayersUntouched(): void
    {
        $this->insertTestPlayer(200000203, 'First Player');
        $this->insertTestPlayer(200000204, 'Second Player');
        $pid203 = 200000203;
        $pid204 = 200000204;

        $result = $this->service->processRetData("First Player 200000203\n", 2026);
        self::assertSame(0, $result->errors);

        $stmt = $this->db->prepare('SELECT retired, teamid, age FROM ibl_plr WHERE pid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $pid204);
        $stmt->execute();
        $r = $stmt->get_result();
        self::assertNotFalse($r);
        $row = $r->fetch_assoc();
        $stmt->close();
        self::assertSame(0, $row['retired']);
        self::assertSame(1, $row['teamid']);
        self::assertSame(27, $row['age']);
    }
}
