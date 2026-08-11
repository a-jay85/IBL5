<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\BugPipeline;

use BugPipeline\BugReportClaimRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
class BugReportClaimRepositoryTest extends DatabaseTestCase
{
    private BugReportClaimRepository $repo;

    // Representative snowflake fixtures — real Discord IDs are 17–19 digits
    private const AUTHOR   = '100000000000000001';
    private const CHANNEL  = '200000000000000002';
    private const MSG_ID   = '300000000000000003';
    private const THREAD   = '400000000000000004';
    private const REPLY_ID = '500000000000000005';
    private const APPROVAL = '600000000000000006';

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new BugReportClaimRepository($this->db);
    }

    // ── claimQueued ────────────────────────────────────────────────────────────

    public function testClaimQueuedReturnsTrueAndSetsStatusHunting(): void
    {
        $id = $this->insertBugReport();
        $ok = $this->repo->claimQueued($id, 'worker-1', '2099-01-01 00:00:00');
        self::assertTrue($ok);
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertSame('hunting', $row['status']);
        self::assertSame('worker-1', $row['lease_owner']);
    }

    public function testClaimQueuedReturnsFalseWhenAlreadyClaimed(): void
    {
        $id = $this->insertBugReport(['status' => 'hunting']);
        self::assertFalse($this->repo->claimQueued($id, 'worker-2', '2099-01-01 00:00:00'));
    }

    // ── claimNextQueued ────────────────────────────────────────────────────────

    public function testClaimNextQueuedReturnsNullWhenQueueEmpty(): void
    {
        self::assertNull($this->repo->claimNextQueued('worker-1', '2099-01-01 00:00:00'));
    }

    public function testClaimNextQueuedClaimsOldestRow(): void
    {
        $id1 = $this->insertBugReport(['original_message_id' => self::MSG_ID]);
        $id2 = $this->insertBugReport(['original_message_id' => self::REPLY_ID]);
        $row = $this->repo->claimNextQueued('worker-1', '2099-01-01 00:00:00');
        self::assertNotNull($row);
        self::assertSame($id1, $row['id'], 'Oldest row (lowest id) must be claimed first');
        // Second claim gets next
        $row2 = $this->repo->claimNextQueued('worker-2', '2099-01-01 00:00:00');
        self::assertNotNull($row2);
        self::assertSame($id2, $row2['id']);
    }

    // ── reclaimStaleLease ──────────────────────────────────────────────────────

    public function testReclaimStaleLeaseReturnsNullWhenNoExpired(): void
    {
        self::assertNull($this->repo->reclaimStaleLease('worker-2', '2099-01-01 00:00:00'));
    }

    public function testReclaimStaleLeaseReclaimsExpiredRow(): void
    {
        $id = $this->insertBugReport([
            'status'        => 'hunting',
            'lease_owner'   => 'crashed-worker',
            'lease_expires' => '2000-01-01 00:00:00',
        ]);
        $row = $this->repo->reclaimStaleLease('worker-2', '2099-01-01 00:00:00');
        self::assertNotNull($row);
        self::assertSame($id, $row['id']);
        self::assertSame('worker-2', $row['lease_owner']);
    }

    // ── transition ─────────────────────────────────────────────────────────────

    public function testTransitionChangesStatus(): void
    {
        $id = $this->insertBugReport();
        self::assertTrue($this->repo->transition($id, 'hunting'));
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertSame('hunting', $row['status']);
    }

    public function testTransitionSetsOptionalColumns(): void
    {
        $id = $this->insertBugReport();
        $this->repo->transition($id, 'pr_open', ['pr_number' => 99, 'thread_id' => self::THREAD]);
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertSame(99, $row['pr_number']);
        self::assertSame(self::THREAD, $row['thread_id']);
    }

    public function testTransitionWithReleaseLeaseClearsLeaseColumns(): void
    {
        $id = $this->insertBugReport([
            'status'        => 'hunting',
            'lease_owner'   => 'worker-1',
            'lease_expires' => '2099-01-01 00:00:00',
        ]);
        $this->repo->transition($id, 'needs_human', [], true);
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertSame('needs_human', $row['status']);
        self::assertNull($row['lease_owner']);
        self::assertNull($row['lease_expires']);
    }

    public function testTransitionReturnsFalseForUnknownId(): void
    {
        self::assertFalse($this->repo->transition(999999, 'queued'));
    }

    // ── stampThreadReply ───────────────────────────────────────────────────────

    public function testStampThreadReplyReturnsFalseWhenNoMatch(): void
    {
        self::assertFalse($this->repo->stampThreadReply('999999999999999999'));
    }

    public function testStampThreadReplyReturnsTrueAndUpdatesTimestamp(): void
    {
        $id = $this->insertBugReport(['thread_id' => self::THREAD]);
        self::assertTrue($this->repo->stampThreadReply(self::THREAD));
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertNotNull($row['last_gm_reply_at']);
    }

    // ── advanceOnApproval ──────────────────────────────────────────────────────

    public function testAdvanceOnApprovalReturnsFalseWhenNoMatch(): void
    {
        self::assertFalse($this->repo->advanceOnApproval('999999999999999999'));
    }

    public function testAdvanceOnApprovalNullsApprovalMessageAndReturnsTrueOnAwaitingAjay(): void
    {
        $id = $this->insertBugReport([
            'status'              => 'awaiting_ajay',
            'approval_message_id' => self::APPROVAL,
        ]);
        self::assertTrue($this->repo->advanceOnApproval(self::APPROVAL));
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertNull($row['approval_message_id']);
        self::assertSame('awaiting_ajay', $row['status']);
    }

    public function testAdvanceOnApprovalReturnsFalseWhenStatusIsNotAwaitingAjay(): void
    {
        $this->insertBugReport([
            'status'              => 'queued',
            'approval_message_id' => self::APPROVAL,
        ]);
        self::assertFalse($this->repo->advanceOnApproval(self::APPROVAL));
    }

    // ── claimNextHuntable (pre-impl characterization pins) ─────────────────────

    public function testClaimNextHuntableSkipsUnclassifiedRow(): void
    {
        // Unclassified (class IS NULL) row is skipped even though it is the oldest queued row.
        $this->insertBugReport(['original_message_id' => self::MSG_ID]); // class NULL by default
        $classified = $this->insertBugReport(['original_message_id' => self::REPLY_ID, 'class' => 'bug']);

        $row = $this->repo->claimNextHuntable('worker-1', '2099-01-01 00:00:00');
        self::assertNotNull($row);
        self::assertSame($classified, $row['id'], 'Only a classified (class IS NOT NULL) queued row is huntable');
        self::assertSame('hunting', $row['status']);
        self::assertSame('worker-1', $row['lease_owner']);

        // The classified row is now hunting; only the unclassified queued row remains → null.
        self::assertNull($this->repo->claimNextHuntable('worker-2', '2099-01-01 00:00:00'));
    }

    public function testClaimNextHuntableSkipsRowParkedByBackoff(): void
    {
        // Future backoff → parked → not huntable (even though classified + queued).
        $this->insertBugReport([
            'original_message_id' => self::MSG_ID,
            'class'               => 'bug',
            'blocked_until'       => '2099-01-01 00:00:00',
        ]);
        self::assertNull($this->repo->claimNextHuntable('worker-1', '2099-01-01 00:00:00'));

        // A ripe (past) backoff is claimable.
        $ripe = $this->insertBugReport([
            'original_message_id' => self::REPLY_ID,
            'class'               => 'bug',
            'blocked_until'       => '2000-01-01 00:00:00',
        ]);
        // Distinct lease args from the parked-row attempt above: a byte-identical
        // claimNextHuntable() call would make PHPStan carry the earlier assertNull()
        // narrowing forward (it can't see the ripe INSERT between the two calls).
        $row = $this->repo->claimNextHuntable('worker-2', '2099-06-01 00:00:00');
        self::assertNotNull($row);
        self::assertSame($ripe, $row['id']);
        self::assertSame('hunting', $row['status']);
    }

    // ── resumeBlockedHunt (pre-impl characterization pins) ─────────────────────

    public function testResumeBlockedHuntFlipsBlockedToHunting(): void
    {
        $id = $this->insertBugReport([
            'status'        => 'blocked',
            'blocked_until' => '2000-01-01 00:00:00',
        ]);
        self::assertTrue($this->repo->resumeBlockedHunt('worker-1', '2099-01-01 00:00:00', $id));
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertSame('hunting', $row['status']);
        self::assertSame('worker-1', $row['lease_owner']);
        self::assertSame('2099-01-01 00:00:00', $row['lease_expires']);

        // Single-flight: the row is no longer 'blocked', so a second immediate call flips nothing.
        self::assertFalse($this->repo->resumeBlockedHunt('worker-2', '2099-01-01 00:00:00', $id));
    }

    public function testResumeBlockedHuntRejectsUnripeBackoff(): void
    {
        $id = $this->insertBugReport([
            'status'        => 'blocked',
            'blocked_until' => '2099-01-01 00:00:00',
        ]);
        self::assertFalse($this->repo->resumeBlockedHunt('worker-1', '2099-01-01 00:00:00', $id));
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertSame('blocked', $row['status']);
    }

    // ── markReminderSent (pre-impl characterization pin) ───────────────────────

    public function testMarkReminderSentIsAtMostOnce(): void
    {
        $id = $this->insertBugReport();
        self::assertTrue($this->repo->markReminderSent($id));
        $first = $this->fetchBugReport($id);
        self::assertNotNull($first);
        self::assertNotNull($first['reminder_sent_at']);

        // At-most-once: a second call is a no-op and the original stamp is preserved.
        self::assertFalse($this->repo->markReminderSent($id));
        $second = $this->fetchBugReport($id);
        self::assertNotNull($second);
        self::assertSame($first['reminder_sent_at'], $second['reminder_sent_at']);
    }

    // ── stampLastProcessed (pre-impl characterization pin) ─────────────────────

    public function testStampLastProcessedUpdatesTimestamp(): void
    {
        $id = $this->insertBugReport();
        self::assertTrue($this->repo->stampLastProcessed($id));
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertNotNull($row['last_processed_at']);

        self::assertFalse($this->repo->stampLastProcessed(999999));
    }

    // ── clearBlocked (pre-impl characterization pin) ───────────────────────────

    public function testClearBlockedNullsBackoff(): void
    {
        $id = $this->insertBugReport(['blocked_until' => '2099-01-01 00:00:00']);
        self::assertTrue($this->repo->clearBlocked($id));
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertNull($row['blocked_until']);

        self::assertFalse($this->repo->clearBlocked(999999));
    }

    // ── updateSourceText ───────────────────────────────────────────────────────

    public function testUpdateSourceTextReplacesStoredSnapshot(): void
    {
        $id = $this->insertBugReport([
            'original_message_id' => self::MSG_ID,
            'original_text'       => 'old bug text',
        ]);
        $this->repo->updateSourceText(self::MSG_ID, 'new bug text');
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertSame('new bug text', $row['original_text']);
        self::assertSame('queued', $row['status'], 'updateSourceText must not change status');
    }

    // ── reviveForReclassify ────────────────────────────────────────────────────

    public function testReviveForReclassifyQueuesRowAndNullsClass(): void
    {
        // Use a row that genuinely changes: 'gathering' → 'queued', class 'bug' → NULL.
        $id = $this->insertBugReport([
            'original_message_id' => self::MSG_ID,
            'status'              => 'gathering',
            'class'               => 'bug',
        ]);
        $result = $this->repo->reviveForReclassify(self::MSG_ID);
        self::assertTrue($result);
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertSame('queued', $row['status']);
        self::assertNull($row['class']);
    }

    public function testReviveForReclassifyNeverResetsHuntAttempts(): void
    {
        $id = $this->insertBugReport([
            'original_message_id' => self::MSG_ID,
            'status'              => 'gathering',
            'class'               => 'bug',
            'hunt_attempts'       => 3,
        ]);
        $this->repo->reviveForReclassify(self::MSG_ID);
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertSame(3, $row['hunt_attempts'], 'hunt_attempts must survive a revive');
    }

    public function testReviveForReclassifyLeavesHuntingRowUntouched(): void
    {
        $id = $this->insertBugReport([
            'original_message_id' => self::MSG_ID,
            'status'              => 'hunting',
            'class'               => 'bug',
        ]);
        $before = $this->fetchBugReport($id);
        self::assertNotNull($before);
        $result = $this->repo->reviveForReclassify(self::MSG_ID);
        self::assertFalse($result);
        $after = $this->fetchBugReport($id);
        self::assertNotNull($after);
        self::assertSame($before['status'], $after['status']);
        self::assertSame($before['class'], $after['class']);
    }

    // ── markSourceDeleted ──────────────────────────────────────────────────────

    public function testMarkSourceDeletedDropsQueuedRow(): void
    {
        $id = $this->insertBugReport([
            'original_message_id' => self::MSG_ID,
            'status'              => 'queued',
        ]);
        $result = $this->repo->markSourceDeleted(self::MSG_ID);
        self::assertTrue($result);
        $row = $this->fetchBugReport($id);
        self::assertNotNull($row);
        self::assertSame('dropped', $row['status']);
    }

    public function testMarkSourceDeletedLeavesPrOpenRowUntouched(): void
    {
        $id = $this->insertBugReport([
            'original_message_id' => self::MSG_ID,
            'status'              => 'pr_open',
        ]);
        $before = $this->fetchBugReport($id);
        self::assertNotNull($before);
        $result = $this->repo->markSourceDeleted(self::MSG_ID);
        self::assertFalse($result);
        $after = $this->fetchBugReport($id);
        self::assertNotNull($after);
        self::assertSame($before['status'], $after['status']);
    }

    public function testMarkSourceDeletedIsNoOpOnAlreadyDroppedRow(): void
    {
        $id = $this->insertBugReport([
            'original_message_id' => self::MSG_ID,
            'status'              => 'dropped',
        ]);
        $before = $this->fetchBugReport($id);
        self::assertNotNull($before);
        $result = $this->repo->markSourceDeleted(self::MSG_ID);
        self::assertFalse($result);
        $after = $this->fetchBugReport($id);
        self::assertNotNull($after);
        self::assertSame('dropped', $after['status']);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * @param array<string, int|string> $overrides
     */
    private function insertBugReport(array $overrides = []): int
    {
        return $this->insertRow('ibl_bug_reports', array_merge([
            'discord_author_id'   => self::AUTHOR,
            'channel_id'          => self::CHANNEL,
            'original_message_id' => self::MSG_ID,
            'original_text'       => 'test bug report',
            'status'              => 'queued',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ], $overrides));
    }

    /**
     * Direct-SQL read-back for write-verify assertions.
     * BugReportClaimRepository is a writer-only sub-repository and does not expose findById().
     *
     * Applies the same snowflake casting as BugReportRowCasting::castRow() so that
     * BIGINT snowflake columns (which MYSQLI_OPT_INT_AND_FLOAT_NATIVE returns as int)
     * are cast to string — matching the shape the original findById()-based assertions expect.
     *
     * @return array<string, mixed>|null
     */
    private function fetchBugReport(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `ibl_bug_reports` WHERE id = ? LIMIT 1');
        self::assertNotFalse($stmt);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!is_array($row)) {
            return null;
        }

        foreach (['discord_author_id', 'channel_id', 'original_message_id', 'thread_id', 'approval_message_id'] as $col) {
            if (isset($row[$col]) && is_scalar($row[$col])) {
                $row[$col] = (string) $row[$col];
            }
        }

        return $row;
    }
}
