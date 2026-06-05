<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Migration 139 (maintenance-28 — backlog 15.14): ibl_one_on_one gains nullable
 * surrogate keys winner_pid / loser_pid -> ibl_plr.pid, with ON DELETE SET NULL
 * foreign keys. Display strings winner/loser are retained.
 */
#[Group('database')]
class OneOnOneSurrogatePidTest extends DatabaseTestCase
{
    public function testSurrogateColumnsAreNullableInt(): void
    {
        foreach (['winner_pid', 'loser_pid'] as $col) {
            $stmt = $this->db->prepare(
                "SELECT DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ibl_one_on_one' AND COLUMN_NAME = ?"
            );
            self::assertNotFalse($stmt);
            $stmt->bind_param('s', $col);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            self::assertNotNull($row, "ibl_one_on_one.$col not found");
            self::assertSame('int', $row['DATA_TYPE']);
            self::assertSame('YES', $row['IS_NULLABLE'], "$col must be nullable (ON DELETE SET NULL)");
        }
    }

    public function testForeignKeysPresentWithSetNull(): void
    {
        $result = $this->db->query(
            "SELECT rc.CONSTRAINT_NAME, rc.DELETE_RULE, kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME
             FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc
             JOIN INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
               ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
              AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
             WHERE rc.CONSTRAINT_SCHEMA = DATABASE() AND rc.TABLE_NAME = 'ibl_one_on_one'"
        );
        self::assertNotFalse($result);

        $fks = [];
        while ($row = $result->fetch_assoc()) {
            $fks[$row['COLUMN_NAME']] = $row;
        }

        foreach (['winner_pid', 'loser_pid'] as $col) {
            self::assertArrayHasKey($col, $fks, "Missing FK on $col");
            self::assertSame('ibl_plr', $fks[$col]['REFERENCED_TABLE_NAME']);
            self::assertSame('pid', $fks[$col]['REFERENCED_COLUMN_NAME']);
            self::assertSame('SET NULL', $fks[$col]['DELETE_RULE']);
        }
    }

    public function testValidPidAccepted(): void
    {
        $row = $this->db->query('SELECT pid FROM ibl_plr ORDER BY pid LIMIT 1')->fetch_assoc();
        self::assertNotNull($row, 'seed has no ibl_plr rows');
        $pid = (int) $row['pid'];

        $this->insertRow('ibl_one_on_one', [
            'gameid' => 999000001,
            'playbyplay' => '',
            'winner' => 'Seed Winner',
            'winner_pid' => $pid,
            'loser' => 'Seed Loser',
            'loser_pid' => $pid,
        ]);

        $check = $this->db->query('SELECT winner_pid FROM ibl_one_on_one WHERE gameid = 999000001')->fetch_assoc();
        self::assertSame($pid, (int) $check['winner_pid']);
    }

    public function testInvalidPidRejectedByForeignKey(): void
    {
        $this->expectException(\mysqli_sql_exception::class);
        // playbyplay supplied so the ONLY constraint violation is the FK.
        $this->insertRow('ibl_one_on_one', [
            'gameid' => 999000002,
            'playbyplay' => '',
            'winner' => 'Bogus',
            'winner_pid' => 2147483647, // no such pid
        ]);
    }

    public function testBackfillCoverageNoUnmatchedNonEmptyNames(): void
    {
        // Every non-empty winner/loser name should resolve to a pid unless the name
        // is genuinely ambiguous in ibl_plr. The CI seed has zero ibl_one_on_one
        // rows, so coverage is trivially complete; this guards against a future
        // backfill regression that silently leaves matchable names NULL.
        $sql = "SELECT COUNT(*) AS n FROM ibl_one_on_one o
                WHERE (o.winner_pid IS NULL AND o.winner <> ''
                       AND (SELECT COUNT(DISTINCT pid) FROM ibl_plr WHERE name = o.winner) = 1)
                   OR (o.loser_pid IS NULL AND o.loser <> ''
                       AND (SELECT COUNT(DISTINCT pid) FROM ibl_plr WHERE name = o.loser) = 1)";
        $row = $this->db->query($sql)->fetch_assoc();
        self::assertSame(0, (int) $row['n'], 'unambiguous names left without a surrogate pid');
    }
}
