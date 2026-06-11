<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

/**
 * Migration 144 (maintenance-43 — backlog 15.8): ibl_demands gains a foreign key
 * fk_demands_player (pid) -> ibl_plr(pid) ON DELETE CASCADE ON UPDATE CASCADE, and
 * its PRIMARY KEY is rebuilt from the legacy name varchar to pid. The pid column
 * already exists (migration 038) but carried no FK and the table keyed on name.
 *
 * Proves: valid pid insert succeeds, orphan pid is rejected (FK, errno 1452),
 * duplicate pid is rejected (PK, errno 1062), and deleting a player cascades to
 * remove its demand row.
 */
#[Group('database')]
class DemandsPidFkTest extends DatabaseTestCase
{
    public function testValidPidDemandInsertSucceeds(): void
    {
        $this->insertTestPlayer(9001, 'FK Valid');
        $this->insertDemandRow('FK Valid', 9001);

        $row = $this->db->query('SELECT pid FROM ibl_demands WHERE pid = 9001')->fetch_assoc();
        self::assertNotNull($row, 'valid demand row was not inserted');
        self::assertSame(9001, (int) $row['pid']);
    }

    public function testOrphanPidInsertIsRejected(): void
    {
        // No parent ibl_plr row for pid 999999, so the FK fk_demands_player is the
        // only violated constraint.
        try {
            $this->db->query(
                "INSERT INTO ibl_demands (name, pid, dem1) VALUES ('FK Orphan', 999999, 1500)"
            );
            self::fail('orphan-pid demand insert was not rejected');
        } catch (\mysqli_sql_exception $e) {
            self::assertSame(1452, $e->getCode(), 'expected FK violation errno 1452');
        }
    }

    public function testDuplicatePidInsertIsRejected(): void
    {
        $this->insertTestPlayer(9002, 'FK Dup');
        $this->insertDemandRow('FK Dup', 9002);

        // Second row with the same pid violates the rebuilt PRIMARY KEY (pid).
        try {
            $this->db->query(
                "INSERT INTO ibl_demands (name, pid, dem1) VALUES ('FK Dup Two', 9002, 1500)"
            );
            self::fail('duplicate-pid demand insert was not rejected');
        } catch (\mysqli_sql_exception $e) {
            self::assertSame(1062, $e->getCode(), 'expected duplicate-PK errno 1062');
        }
    }

    public function testDeletingPlayerCascadesDemand(): void
    {
        $this->insertTestPlayer(9003, 'FK Cascade');
        $this->insertDemandRow('FK Cascade', 9003);

        $this->db->query('DELETE FROM ibl_plr WHERE pid = 9003');

        $row = $this->db->query('SELECT COUNT(*) AS c FROM ibl_demands WHERE pid = 9003')->fetch_assoc();
        self::assertNotNull($row);
        self::assertSame(0, (int) $row['c'], 'deleting the player must cascade-delete its demand row');
    }
}
