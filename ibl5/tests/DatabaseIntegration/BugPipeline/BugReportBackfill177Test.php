<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\BugPipeline;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\DatabaseIntegration\DatabaseTestCase;

/**
 * Verifies the three idempotent backfill UPDATE statements in migration 177.
 * Runs the UPDATE SQL directly (no ALTER TABLE — the enum column already carries
 * 'filed' in the test DB) to confirm:
 *   Arm A  — in-flight rows that already hold a GitHub issue number → filed
 *   Arm B  — in-flight rows with no issue number → re-queued, stale fields cleared
 *   Arm C  — queued rows that slipped through with a class but no issue → class cleared
 */
#[Group('database')]
final class BugReportBackfill177Test extends DatabaseTestCase
{
    private const AUTHOR  = '100000000000000001';
    private const CHANNEL = '200000000000000002';

    // ── Arm A ─────────────────────────────────────────────────────────────────

    #[Test]
    public function backfillMovesIssueBearingRowsToFiled(): void
    {
        $id = $this->insertBugReport([
            'original_message_id' => '310000000000000001',
            'status'              => 'hunting',
            'issue_number'        => 123,
            'lease_owner'         => 'tick-abc',
            'lease_expires'       => '2026-12-31 23:59:59',
            'blocked_until'       => '2026-12-31 23:59:59',
        ]);

        // Arm B target in the same test — must NOT be filed (no issue_number)
        $this->insertBugReport([
            'original_message_id' => '310000000000000002',
            'status'              => 'blocked',
        ]);

        $this->runBackfillUpdates();

        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertSame('filed', $row['status'], 'hunting row with issue_number → filed');
        self::assertNull($row['lease_owner'],  'lease_owner cleared');
        self::assertNull($row['lease_expires'], 'lease_expires cleared');
        self::assertNull($row['blocked_until'], 'blocked_until cleared');
    }

    // ── Arm B ─────────────────────────────────────────────────────────────────

    #[Test]
    public function backfillRequeuesIssuelessRowsAndClearsBlockedUntil(): void
    {
        $id = $this->insertBugReport([
            'original_message_id' => '320000000000000001',
            'status'              => 'blocked',
            'class'               => 'bug',
            'approval_message_id' => '600000000000000001',
            'blocked_until'       => '2099-01-01 00:00:00',
            'reminder_sent_at'    => '2026-09-01 00:00:00',
        ]);

        $this->runBackfillUpdates();

        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertSame('queued', $row['status'],   'blocked+no-issue → queued');
        self::assertNull($row['class'],              'class cleared');
        self::assertNull($row['lease_owner'],        'lease_owner cleared');
        self::assertNull($row['lease_expires'],      'lease_expires cleared');
        self::assertNull($row['approval_message_id'], 'approval_message_id cleared');
        self::assertNull($row['blocked_until'],      'blocked_until cleared');
        self::assertNull($row['reminder_sent_at'],   'reminder_sent_at cleared');
    }

    // ── Arm C ─────────────────────────────────────────────────────────────────

    #[Test]
    public function backfillClearsClassOnQueuedRowsWithNoIssue(): void
    {
        $id = $this->insertBugReport([
            'original_message_id' => '330000000000000001',
            'status'              => 'queued',
            'class'               => 'bug',
            // no issue_number
        ]);

        $this->runBackfillUpdates();

        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertSame('queued', $row['status'], 'status stays queued');
        self::assertNull($row['class'],            'class cleared (invisible-row hole closed)');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function runBackfillUpdates(): void
    {
        // Arm A: rows with an issue are terminal.
        $this->db->query(
            "UPDATE `ibl_bug_reports`
               SET `status` = 'filed',
                   `lease_owner` = NULL,
                   `lease_expires` = NULL,
                   `blocked_until` = NULL
             WHERE `status` IN ('planned','hunting','gathering','awaiting_ajay','blocked')
               AND `issue_number` IS NOT NULL"
        );
        // Arm B: rows without an issue go back to queued.
        $this->db->query(
            "UPDATE `ibl_bug_reports`
               SET `status` = 'queued',
                   `class` = NULL,
                   `lease_owner` = NULL,
                   `lease_expires` = NULL,
                   `approval_message_id` = NULL,
                   `blocked_until` = NULL,
                   `reminder_sent_at` = NULL
             WHERE `status` IN ('planned','hunting','gathering','awaiting_ajay','blocked','awaiting_info')
               AND `issue_number` IS NULL"
        );
        // Arm C: close the invisible-row hole.
        $this->db->query(
            "UPDATE `ibl_bug_reports`
               SET `class` = NULL
             WHERE `status` = 'queued'
               AND `class` IS NOT NULL
               AND `issue_number` IS NULL"
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchBugReport(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM `ibl_bug_reports` WHERE `id` = ?'
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        /** @var array<string, float|int|string|null>|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row;
    }

    /**
     * @param array<string, int|string> $overrides
     */
    private function insertBugReport(array $overrides = []): int
    {
        return $this->insertRow('ibl_bug_reports', array_merge([
            'discord_author_id'   => self::AUTHOR,
            'channel_id'          => self::CHANNEL,
            'original_message_id' => '300000000000000001',
            'original_text'       => 'test bug report',
            'status'              => 'queued',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ], $overrides));
    }
}
