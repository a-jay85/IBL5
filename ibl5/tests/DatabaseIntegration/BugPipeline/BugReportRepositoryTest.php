<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration\BugPipeline;

use BugPipeline\BugReportRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\DatabaseIntegration\DatabaseTestCase;

#[Group('database')]
class BugReportRepositoryTest extends DatabaseTestCase
{
    private BugReportRepository $repo;

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
        $this->repo = new BugReportRepository($this->db);
    }

    // ── findById ───────────────────────────────────────────────────────────────

    public function testFindByIdReturnsNullForUnknown(): void
    {
        self::assertNull($this->repo->findById(999999));
    }

    public function testFindByIdReturnsCastRow(): void
    {
        $id = $this->insertBugReport(['original_message_id' => self::MSG_ID]);
        $row = $this->repo->findById($id);
        self::assertNotNull($row);
        self::assertSame($id, $row['id']);
        self::assertIsString($row['discord_author_id'], 'snowflake must be cast to string');
        self::assertSame(self::AUTHOR, $row['discord_author_id']);
    }

    // ── findByThreadId ─────────────────────────────────────────────────────────

    public function testFindByThreadIdReturnsNullWhenNoMatch(): void
    {
        self::assertNull($this->repo->findByThreadId('999999999999999999'));
    }

    public function testFindByThreadIdReturnsRowAndCastsSnowflake(): void
    {
        $id = $this->insertBugReport(['thread_id' => self::THREAD]);
        $row = $this->repo->findByThreadId(self::THREAD);
        self::assertNotNull($row);
        self::assertSame($id, $row['id']);
        self::assertIsString($row['thread_id']);
        self::assertSame(self::THREAD, $row['thread_id']);
    }

    // ── findByOriginalMessageId ────────────────────────────────────────────────

    public function testFindByOriginalMessageIdReturnsNullWhenNoMatch(): void
    {
        self::assertNull($this->repo->findByOriginalMessageId('999999999999999999'));
    }

    public function testFindByOriginalMessageIdReturnsRow(): void
    {
        $this->insertBugReport(['original_message_id' => self::MSG_ID]);
        $row = $this->repo->findByOriginalMessageId(self::MSG_ID);
        self::assertNotNull($row);
        self::assertSame(self::MSG_ID, $row['original_message_id']);
    }

    // ── insertQueuedReport ─────────────────────────────────────────────────────

    public function testInsertQueuedReportReturnsNewId(): void
    {
        $id = $this->repo->insertQueuedReport(self::AUTHOR, self::CHANNEL, self::MSG_ID, 'app crashes');
        self::assertGreaterThan(0, $id);
        $row = $this->repo->findById($id);
        self::assertNotNull($row);
        self::assertSame('queued', $row['status']);
        self::assertSame('app crashes', $row['original_text']);
    }

    // ── upsertReporterProfile / getReporterTechLevel ───────────────────────────

    public function testGetReporterTechLevelReturnsNullWhenMissing(): void
    {
        self::assertNull($this->repo->getReporterTechLevel('999999999999999999'));
    }

    public function testUpsertReporterProfileInsertsAndUpdates(): void
    {
        $this->repo->upsertReporterProfile(self::AUTHOR, 'technical');
        self::assertSame('technical', $this->repo->getReporterTechLevel(self::AUTHOR));

        // Idempotent update
        $this->repo->upsertReporterProfile(self::AUTHOR, 'nontechnical');
        self::assertSame('nontechnical', $this->repo->getReporterTechLevel(self::AUTHOR));
    }

    // ── upsertPipelineState / findPipelineState ────────────────────────────────

    public function testFindPipelineStateReturnsNullWhenNoRow(): void
    {
        self::assertNull($this->repo->findPipelineState('999999999999999999'));
    }

    public function testUpsertPipelineStateInsertsAndReturnsString(): void
    {
        $this->repo->upsertPipelineState(self::CHANNEL, self::MSG_ID);
        $cursor = $this->repo->findPipelineState(self::CHANNEL);
        self::assertSame(self::MSG_ID, $cursor);
    }

    public function testUpsertPipelineStateIsMonotonic(): void
    {
        // Lower ID first, then higher — cursor advances
        $lower  = '200000000000000001';
        $higher = '300000000000000002';
        $this->repo->upsertPipelineState(self::CHANNEL, $lower);
        $this->repo->upsertPipelineState(self::CHANNEL, $higher);
        self::assertSame($higher, $this->repo->findPipelineState(self::CHANNEL));

        // Replaying older message must NOT regress the cursor
        $this->repo->upsertPipelineState(self::CHANNEL, $lower);
        self::assertSame($higher, $this->repo->findPipelineState(self::CHANNEL));
    }

    // ── enqueueAuthorizedAndAdvance (crash-safe, replay-safe) ─────────────────

    public function testEnqueueAuthorizedAndAdvanceInsertsRowAndSetsWatermark(): void
    {
        $id = $this->repo->enqueueAuthorizedAndAdvance(self::AUTHOR, self::CHANNEL, self::MSG_ID, 'bug text');
        self::assertGreaterThan(0, $id);
        self::assertNotNull($this->repo->findById($id));
        self::assertSame(self::MSG_ID, $this->repo->findPipelineState(self::CHANNEL));
    }

    public function testEnqueueAuthorizedAndAdvanceIsReplaySafe(): void
    {
        $id1 = $this->repo->enqueueAuthorizedAndAdvance(self::AUTHOR, self::CHANNEL, self::MSG_ID, 'bug text');
        $id2 = $this->repo->enqueueAuthorizedAndAdvance(self::AUTHOR, self::CHANNEL, self::MSG_ID, 'bug text');
        self::assertSame($id1, $id2, 'Replay must return same id without inserting a second row');

        // Confirm only one row exists for this message_id
        $stmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM `ibl_bug_reports` WHERE original_message_id = ?');
        self::assertNotFalse($stmt);
        $msgId = self::MSG_ID;
        $stmt->bind_param('s', $msgId);
        $stmt->execute();
        /** @var array{cnt: int}|null $row */
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertNotNull($row);
        self::assertSame(1, $row['cnt']);
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
        $row = $this->repo->findById($id);
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
        $row = $this->repo->findById($id);
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

    // ── claimQueued ────────────────────────────────────────────────────────────

    public function testClaimQueuedReturnsTrueAndSetsStatusHunting(): void
    {
        $id = $this->insertBugReport();
        $ok = $this->repo->claimQueued($id, 'worker-1', '2099-01-01 00:00:00');
        self::assertTrue($ok);
        $row = $this->repo->findById($id);
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
        self::assertSame('hunting', $this->repo->findById($id)['status'] ?? null);
    }

    public function testTransitionSetsOptionalColumns(): void
    {
        $id = $this->insertBugReport();
        $this->repo->transition($id, 'pr_open', ['pr_number' => 99, 'thread_id' => self::THREAD]);
        $row = $this->repo->findById($id);
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
        $row = $this->repo->findById($id);
        self::assertNotNull($row);
        self::assertSame('needs_human', $row['status']);
        self::assertNull($row['lease_owner']);
        self::assertNull($row['lease_expires']);
    }

    public function testTransitionReturnsFalseForUnknownId(): void
    {
        self::assertFalse($this->repo->transition(999999, 'queued'));
    }

    // ── findThreadIdByPrNumber ─────────────────────────────────────────────────

    public function testFindThreadIdByPrNumberReturnsNullWhenNoMatch(): void
    {
        self::assertNull($this->repo->findThreadIdByPrNumber(99999));
    }

    public function testFindThreadIdByPrNumberReturnsStringSnowflake(): void
    {
        $id = $this->insertBugReport(['thread_id' => self::THREAD]);
        $this->repo->transition($id, 'pr_open', ['pr_number' => 42]);
        $threadId = $this->repo->findThreadIdByPrNumber(42);
        self::assertSame(self::THREAD, $threadId);
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

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
}
