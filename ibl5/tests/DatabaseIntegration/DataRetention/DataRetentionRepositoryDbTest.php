<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\DataRetention;

use DataRetention\DataRetentionRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Test rows stay below event_at 1_000_000_000, well under the seed's 2026-era audit rows.
 */
#[Group('database')]
class DataRetentionRepositoryDbTest extends DatabaseTestCase
{
    private DataRetentionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new DataRetentionRepository($this->db);
    }

    public function testCountAuditLogRowsOlderThanCountsOnlyRowsBeforeCutoff(): void
    {
        $this->insertAuditRows([100, 200, 300, 900_000_000]);

        self::assertSame(3, $this->repo->countAuditLogRowsOlderThan(1000));
    }

    public function testDeleteAuditLogBatchRespectsLimitAndCutoff(): void
    {
        $this->insertAuditRows([100, 200, 300, 900_000_000]);

        self::assertSame(2, $this->repo->deleteAuditLogBatchOlderThan(1000, 2));
        self::assertSame(1, $this->countAuditRowsBetween(0, 999));
        self::assertSame(1, $this->countAuditRowsBetween(900_000_000, 900_000_000));
    }

    public function testDeleteAuditLogBatchLeavesRowAtExactCutoff(): void
    {
        $this->insertAuditRows([1000]);

        self::assertSame(0, $this->repo->deleteAuditLogBatchOlderThan(1000, 10));
        self::assertSame(1, $this->countAuditRowsBetween(1000, 1000));
    }

    /**
     * @param list<int> $eventTimes
     */
    private function insertAuditRows(array $eventTimes): void
    {
        foreach ($eventTimes as $eventAt) {
            $this->insertRow('auth_users_audit_log', [
                'event_at' => $eventAt,
                'event_type' => 'login',
            ]);
        }
    }

    private function countAuditRowsBetween(int $from, int $to): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS n FROM `auth_users_audit_log` WHERE `event_at` BETWEEN ? AND ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('ii', $from, $to);
        $stmt->execute();
        $row = $stmt->get_result()?->fetch_assoc();
        $stmt->close();

        return is_array($row) && is_int($row['n']) ? $row['n'] : -1;
    }
}
