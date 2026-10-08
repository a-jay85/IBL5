<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\DataRetention;

use DataRetention\DataRetentionRepository;
use DataRetention\DataRetentionService;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * now = 1_000_000_000 and retention = 180 days give a cutoff of 984_448_000.
 * Test rows stay below 1_000_000_000, well under the seed's 2026-era audit rows.
 */
#[Group('database')]
class DataRetentionServiceDbTest extends DatabaseTestCase
{
    private const NOW = 1_000_000_000;
    private const CUTOFF = 984_448_000;

    private DataRetentionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DataRetentionService(new DataRetentionRepository($this->db));
    }

    public function testPurgeAuditLogDeletesAllRowsOlderThanRetentionAcrossBatches(): void
    {
        $this->insertAuditRows([900_000_000, 900_000_001, 900_000_002, 900_000_003, 900_000_004]);

        // Batch size 2 forces three round-trips (2, 2, 1).
        $result = $this->service->purgeAuditLog(180, false, self::NOW, 2);

        self::assertSame(self::CUTOFF, $result->cutoffEpoch);
        self::assertSame(5, $result->eligible);
        self::assertSame(5, $result->deleted);
        self::assertSame(0, $this->countAuditRowsBelow(self::CUTOFF));
    }

    public function testPurgeAuditLogKeepsRowsInsideRetentionWindow(): void
    {
        $this->insertAuditRows([self::CUTOFF, 999_000_000]);

        $this->service->purgeAuditLog(180, false, self::NOW);

        self::assertSame(2, $this->countAuditRowsBelow(self::NOW) - $this->countAuditRowsBelow(self::CUTOFF));
    }

    public function testPurgeAuditLogDryRunWritesNothing(): void
    {
        $this->insertAuditRows([900_000_000, 900_000_001, 900_000_002, 900_000_003, 900_000_004]);
        $before = $this->countAuditRowsBelow(PHP_INT_MAX);

        $result = $this->service->purgeAuditLog(180, true, self::NOW);

        self::assertSame(5, $result->eligible);
        self::assertSame(0, $result->deleted);
        self::assertTrue($result->dryRun);
        self::assertSame($before, $this->countAuditRowsBelow(PHP_INT_MAX));
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

    private function countAuditRowsBelow(int $epoch): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) AS n FROM `auth_users_audit_log` WHERE `event_at` < ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $epoch);
        $stmt->execute();
        $row = $stmt->get_result()?->fetch_assoc();
        $stmt->close();

        return is_array($row) && is_int($row['n']) ? $row['n'] : -1;
    }
}
