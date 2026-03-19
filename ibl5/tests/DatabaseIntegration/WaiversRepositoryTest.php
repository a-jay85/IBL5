<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Waivers\WaiversRepository;

/**
 * Tests WaiversRepository write methods against real MariaDB.
 * These are impossible to test with mocked mysqli because $stmt->affected_rows is readonly.
 */
class WaiversRepositoryTest extends DatabaseTestCase
{
    private WaiversRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new WaiversRepository($this->db);
    }

    public function testDropPlayerToWaiversUpdatesRow(): void
    {
        $timestamp = 1700000000;
        $result = $this->repo->dropPlayerToWaivers(1, $timestamp);

        self::assertTrue($result);

        // Verify actual DB state via follow-up SELECT
        $stmt = $this->db->prepare("SELECT ordinal, droptime FROM ibl_plr WHERE pid = ?");
        self::assertNotFalse($stmt);
        $pid = 1;
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(1000, $row['ordinal']);
        self::assertSame($timestamp, $row['droptime']);
    }

    public function testDropPlayerToWaiversReturnsFalseForUnknownPlayer(): void
    {
        $result = $this->repo->dropPlayerToWaivers(99999, time());

        self::assertFalse($result);
    }

    public function testSignPlayerFromWaiversUpdatesRow(): void
    {
        // First drop player 2 (free agent) to waivers
        $this->repo->dropPlayerToWaivers(2, 1700000000);

        // Now sign player 2 to Sharks (tid=2)
        $result = $this->repo->signPlayerFromWaivers(
            2,
            ['team_name' => 'Sharks', 'teamid' => 2],
            ['hasExistingContract' => false, 'salary' => 500]
        );

        self::assertTrue($result);

        // Verify DB state
        $stmt = $this->db->prepare("SELECT tid, ordinal, droptime, cy1, cyt FROM ibl_plr WHERE pid = ?");
        self::assertNotFalse($stmt);
        $pid = 2;
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertNotNull($row);
        self::assertSame(2, $row['tid']);
        self::assertSame(800, $row['ordinal']);
        self::assertSame(0, $row['droptime']);
        self::assertSame(500, $row['cy1']);
        self::assertSame(1, $row['cyt']);
    }
}
