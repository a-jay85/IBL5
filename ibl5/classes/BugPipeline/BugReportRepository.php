<?php

declare(strict_types=1);

namespace BugPipeline;

use BugPipeline\Contracts\BugPipelineStateRepositoryInterface;
use BugPipeline\Contracts\BugReportClaimRepositoryInterface;
use BugPipeline\Contracts\BugReporterProfileRepositoryInterface;

/**
 * Facade over the Discord bug-pipeline queue DB logic (backlog 1.26 split).
 *
 * The read path + crash-safe enqueue live here; the lease/claim + state-machine
 * writers, reporter-profile store, and per-channel watermark store are delegated
 * to three sub-repositories behind {@see BugReportClaimRepositoryInterface},
 * {@see BugReporterProfileRepositoryInterface}, and
 * {@see BugPipelineStateRepositoryInterface}. All 14 call sites keep calling this
 * facade unchanged — the split is behavior-preserving.
 *
 * The sub-repositories are built in the constructor from the facade's OWN mysqli
 * handle, so a call the facade wraps in {@see \BaseMysqliRepository::transactional()}
 * (enqueueAuthorizedAndAdvance) and the delegated write it makes share one
 * connection and one transaction/SAVEPOINT — the enqueue stays atomic.
 *
 * Snowflake casting (castRow / SNOWFLAKE_COLUMNS) and the BugReportRow row shape
 * live in {@see BugReportRowCasting}, shared with the sub-repositories. The
 * `ibl_bug_report_attachments` child table stays on the facade alongside its
 * parent's read path — see the attachment methods below.
 *
 * @phpstan-import-type BugReportRow from \BugPipeline\BugReportRowCasting
 * @phpstan-type BugAttachmentRow array{
 *   id: int,
 *   report_id: int,
 *   attachment_id: string,
 *   original_url: string,
 *   local_path: ?string,
 *   filename: string,
 *   content_type: string,
 *   file_size: ?int,
 *   created_at: string
 * }
 * @phpstan-type BugAttachmentInput array{
 *   attachment_id: string,
 *   original_url: string,
 *   local_path: ?string,
 *   filename: string,
 *   content_type: string,
 *   file_size: ?int
 * }
 */
class BugReportRepository extends \BaseMysqliRepository
{
    use BugReportRowCasting;

    private readonly BugReportClaimRepositoryInterface $claims;
    private readonly BugReporterProfileRepositoryInterface $reporterProfile;
    private readonly BugPipelineStateRepositoryInterface $pipelineState;

    public function __construct(\mysqli $db, ?\League\LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        // Share the facade's own $db handle so a delegated write inside
        // enqueueAuthorizedAndAdvance()'s transaction runs on the same connection.
        $this->claims = new BugReportClaimRepository($db, $leagueContext);
        $this->reporterProfile = new BugReporterProfileRepository($db, $leagueContext);
        $this->pipelineState = new BugPipelineStateRepository($db, $leagueContext);
    }

    // -------------------------------------------------------------------------
    // Read methods
    // -------------------------------------------------------------------------

    /**
     * @phpstan-return BugReportRow|null
     */
    public function findById(int $id): ?array
    {
        $row = $this->fetchOne('SELECT * FROM `ibl_bug_reports` WHERE id = ? LIMIT 1', 'i', $id);
        return $row === null ? null : $this->castRow($row);
    }

    /**
     * Snowflake bound as STRING ("s") — never (int)-cast a snowflake.
     * @phpstan-return BugReportRow|null
     */
    public function findByThreadId(string $threadId): ?array
    {
        $row = $this->fetchOne('SELECT * FROM `ibl_bug_reports` WHERE thread_id = ? LIMIT 1', 's', $threadId);
        return $row === null ? null : $this->castRow($row);
    }

    /**
     * Enqueue idempotency lookup — replay-safe dedupe. Snowflake bound as STRING ("s").
     * @phpstan-return BugReportRow|null
     */
    public function findByOriginalMessageId(string $messageId): ?array
    {
        $row = $this->fetchOne('SELECT * FROM `ibl_bug_reports` WHERE original_message_id = ? LIMIT 1', 's', $messageId);
        return $row === null ? null : $this->castRow($row);
    }

    /**
     * Every row currently in the `pr_open` terminal-ish state, for the cron's async reconcile
     * pass (PR #5b Phase 5 Fork B). The hunter leaves a shipped row at `pr_open` immediately
     * (before the PR number is known); the trusted cron later fills `pr_number` from `gh` and,
     * on merge, advances `pr_open` → `fixed`. Read-only enumerator — no lease, no state change.
     *
     * @phpstan-return list<BugReportRow>
     */
    public function listPrOpen(): array
    {
        $rows = $this->fetchAll(
            "SELECT * FROM `ibl_bug_reports` WHERE status = 'pr_open' ORDER BY id ASC"
        );
        return array_values(array_map(fn (array $row): array => $this->castRow($row), $rows));
    }

    /**
     * The tick's actionable set — every row the poll-only driver must inspect this tick.
     *
     * Union (see discord-bug-pipeline-shared-context.md §3d + PR #5a Phase 3/5):
     *   (a) queued AND class IS NULL          — needs first-classification
     *   (b) awaiting_info / gathering          — GM reply re-assessment OR idle/park candidate
     *                                            (the 1h idle POLICY lives in the bash driver, not here)
     *   (c) awaiting_ajay AND approval_message_id IS NULL — ready-for-plan (A-Jay reacted ✅)
     *   (d) blocked                              — a usage-limit-parked hunt whose backoff has
     *                                              elapsed; the outer blocked_until gate only lets
     *                                              a RIPE one through, so the bash driver resumes it
     *                                              (blocked → hunting) via resumeBlockedHunt (#5b Phase 7)
     *
     * Global gates: excludes `hunting` (an in-flight hunt is invisible — the single-flight
     * constraint) and all terminal states, and excludes any row still parked by a future
     * `blocked_until` (usage-limit backoff). A row whose `blocked_until` has passed re-surfaces in
     * its real status and retries — so "skip while parked" and "auto-resume" both fall out of the
     * one blocked-until gate.
     *
     * @phpstan-return list<BugReportRow>
     */
    public function listActiveConversations(): array
    {
        $rows = $this->fetchAll(
            "SELECT * FROM `ibl_bug_reports`
             WHERE status NOT IN ('hunting','dropped','fixed','needs_human','parked_idle','pr_open')
               AND (blocked_until IS NULL OR blocked_until <= NOW())
               AND (
                     (status = 'queued' AND class IS NULL)
                  OR (status IN ('awaiting_info','gathering'))
                  OR (status = 'awaiting_ajay' AND approval_message_id IS NULL)
                  OR (status = 'blocked')
               )
             ORDER BY id ASC"
        );
        return array_values(array_map(fn (array $row): array => $this->castRow($row), $rows));
    }

    /**
     * PR #4 /prMerged resolver. pr_number is a PR number (small INT, bound "i"), NOT a snowflake.
     * Returns the thread_id snowflake as a STRING, or null if unresolved.
     */
    public function findThreadIdByPrNumber(int $prNumber): ?string
    {
        $row = $this->fetchOne(
            'SELECT thread_id FROM `ibl_bug_reports` WHERE pr_number = ? LIMIT 1',
            'i',
            $prNumber
        );
        if ($row === null) {
            return null;
        }
        return isset($row['thread_id']) && is_scalar($row['thread_id'])
            ? (string) $row['thread_id']
            : null;
    }

    // -------------------------------------------------------------------------
    // Crash-safe enqueue (owns its own transaction; delegates the watermark write)
    // -------------------------------------------------------------------------

    public function insertQueuedReport(string $authorId, string $channelId, string $messageId, string $text): int
    {
        $this->execute(
            'INSERT INTO `ibl_bug_reports`
                (discord_author_id, channel_id, original_message_id, original_text, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, \'queued\', NOW(), NOW())',
            'ssss',
            $authorId,
            $channelId,
            $messageId,
            $text
        );
        return $this->getLastInsertId();
    }

    /**
     * Crash-safe, replay-safe enqueue: INSERT + watermark advance run in one transaction.
     * Pre-insert findByOriginalMessageId dedupe makes it replay-safe for PR #4's backfill.
     *
     * The watermark write is delegated to the pipeline-state sub-repository, which shares
     * this facade's mysqli handle — so it runs inside the same transaction opened here.
     */
    public function enqueueAuthorizedAndAdvance(string $authorId, string $channelId, string $messageId, string $text): int
    {
        /** @var int $id */
        $id = $this->transactional(function () use ($authorId, $channelId, $messageId, $text): int {
            // Replay-safe: a message already enqueued returns its existing id, no 2nd row.
            $existing = $this->findByOriginalMessageId($messageId);
            if ($existing !== null) {
                $this->pipelineState->upsertPipelineState($channelId, $messageId);
                return $existing['id'];
            }
            // Insert + watermark advance in the SAME transaction => crash between them rolls back both.
            $newId = $this->insertQueuedReport($authorId, $channelId, $messageId, $text);
            $this->pipelineState->upsertPipelineState($channelId, $messageId);
            return $newId;
        });
        return $id;
    }

    // -------------------------------------------------------------------------
    // Attachment child table (ibl_bug_report_attachments)
    //
    // Stays on the facade alongside the parent row it hangs off: the child table carries no
    // lease/claim, reporter-profile, or watermark concern, and insertAttachments() runs AFTER
    // the enqueue transaction above commits — so it needs no shared-transaction sub-repository.
    // -------------------------------------------------------------------------

    /**
     * Persist attachment metadata for a report. Best-effort, replay-safe, storage-idempotent.
     *
     * INSERT IGNORE against UNIQUE (report_id, attachment_id): a Phase-6 backfill replay, a bot
     * restart, or a retried HTTP call reaching this method a second time with the SAME report_id
     * inserts nothing on the duplicate rows rather than duplicating every attachment. Idempotency
     * lives at the storage layer so a future caller cannot forget an "only on the new-row branch"
     * guard. Called AFTER enqueueAuthorizedAndAdvance() commits — the FK needs a committed parent,
     * and the idempotency means a crash between commit and this insert is repaired by the next replay.
     *
     * report_id binds 'i' — it is the local AUTO_INCREMENT ibl_bug_reports.id (INT UNSIGNED), NOT a
     * Discord snowflake, so the snowflake-is-a-string rule does not apply here. attachment_id binds
     * 's' — that one IS a snowflake. local_path/file_size accept null (download failed → degrade-to-URL).
     *
     * @phpstan-param list<BugAttachmentInput> $attachments
     */
    public function insertAttachments(int $reportId, array $attachments): void
    {
        foreach ($attachments as $att) {
            $this->execute(
                'INSERT IGNORE INTO `ibl_bug_report_attachments`
                    (report_id, attachment_id, original_url, local_path, filename, content_type, file_size)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                'isssssi',
                $reportId,
                $att['attachment_id'],
                $att['original_url'],
                $att['local_path'],
                $att['filename'],
                $att['content_type'],
                $att['file_size']
            );
        }
    }

    /**
     * All attachments for one report, oldest-first. Consumer: claim-next.php (the hunter's row).
     * attachment_id is VARCHAR(20) in the DB so it reads back as a native PHP string — no castRow entry.
     *
     * @phpstan-return list<BugAttachmentRow>
     */
    public function findAttachmentsByReportId(int $reportId): array
    {
        /** @var list<BugAttachmentRow> $rows */
        $rows = $this->fetchAll(
            'SELECT * FROM `ibl_bug_report_attachments` WHERE report_id = ? ORDER BY id ASC',
            'i',
            $reportId
        );
        return array_values($rows);
    }

    /**
     * Attachments for many reports in ONE batched query, keyed by report_id. Consumer:
     * list-active-conversations.php (N rows per tick). fetchAllInList short-circuits on an empty
     * list WITHOUT touching the database, so an empty actionable set issues zero attachment queries
     * — this is what preserves the empty-tick zero-cost guard and avoids an N+1 every 180s tick.
     *
     * The placeholder count derives from count($reportIds), never from input text, and every value
     * is bound — a parameterized query despite the dynamic placeholder list.
     *
     * @param list<int> $reportIds
     * @phpstan-return array<int, list<BugAttachmentRow>>
     */
    public function findAttachmentsForReportIds(array $reportIds): array
    {
        /** @var list<BugAttachmentRow> $rows */
        $rows = $this->fetchAllInList(
            'SELECT * FROM `ibl_bug_report_attachments` WHERE report_id IN ({IN}) ORDER BY report_id ASC, id ASC',
            'i',
            $reportIds
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['report_id']][] = $row;
        }
        return $grouped;
    }

    // -------------------------------------------------------------------------
    // Reporter-profile delegations
    // -------------------------------------------------------------------------

    /**
     * @see BugReporterProfileRepository::upsertReporterProfile()
     */
    public function upsertReporterProfile(string $discordId, string $techLevel): void
    {
        $this->reporterProfile->upsertReporterProfile($discordId, $techLevel);
    }

    /**
     * @see BugReporterProfileRepository::getReporterTechLevel()
     */
    public function getReporterTechLevel(string $discordId): ?string
    {
        return $this->reporterProfile->getReporterTechLevel($discordId);
    }

    // -------------------------------------------------------------------------
    // Pipeline-state (watermark) delegations
    // -------------------------------------------------------------------------

    /**
     * @see BugPipelineStateRepository::upsertPipelineState()
     */
    public function upsertPipelineState(string $channelId, string $messageId): void
    {
        $this->pipelineState->upsertPipelineState($channelId, $messageId);
    }

    /**
     * @see BugPipelineStateRepository::findPipelineState()
     */
    public function findPipelineState(string $channelId): ?string
    {
        return $this->pipelineState->findPipelineState($channelId);
    }

    // -------------------------------------------------------------------------
    // Lease/claim + state-machine writer delegations
    // -------------------------------------------------------------------------

    /**
     * @see BugReportClaimRepository::claimQueued()
     */
    public function claimQueued(int $id, string $leaseOwner, string $leaseExpires): bool
    {
        return $this->claims->claimQueued($id, $leaseOwner, $leaseExpires);
    }

    /**
     * @see BugReportClaimRepository::claimNextQueued()
     * @phpstan-return BugReportRow|null
     */
    public function claimNextQueued(string $leaseOwner, string $leaseExpires): ?array
    {
        return $this->claims->claimNextQueued($leaseOwner, $leaseExpires);
    }

    /**
     * @see BugReportClaimRepository::reclaimStaleLease()
     * @phpstan-return BugReportRow|null
     */
    public function reclaimStaleLease(string $newLeaseOwner, string $leaseExpires): ?array
    {
        return $this->claims->reclaimStaleLease($newLeaseOwner, $leaseExpires);
    }

    /**
     * @see BugReportClaimRepository::claimNextHuntable()
     * @phpstan-return BugReportRow|null
     */
    public function claimNextHuntable(string $leaseOwner, string $leaseExpires): ?array
    {
        return $this->claims->claimNextHuntable($leaseOwner, $leaseExpires);
    }

    /**
     * @see BugReportClaimRepository::resumeBlockedHunt()
     */
    public function resumeBlockedHunt(string $leaseOwner, string $leaseExpires, int $id): bool
    {
        return $this->claims->resumeBlockedHunt($leaseOwner, $leaseExpires, $id);
    }

    /**
     * @see BugReportClaimRepository::transition()
     * @param array<string, int|string> $opts
     */
    public function transition(int $id, string $status, array $opts = [], bool $releaseLease = false): bool
    {
        return $this->claims->transition($id, $status, $opts, $releaseLease);
    }

    /**
     * @see BugReportClaimRepository::advanceOnApproval()
     */
    public function advanceOnApproval(string $messageId): bool
    {
        return $this->claims->advanceOnApproval($messageId);
    }

    /**
     * @see BugReportClaimRepository::stampThreadReply()
     */
    public function stampThreadReply(string $threadId): bool
    {
        return $this->claims->stampThreadReply($threadId);
    }

    /**
     * @see BugReportClaimRepository::markReminderSent()
     */
    public function markReminderSent(int $id): bool
    {
        return $this->claims->markReminderSent($id);
    }

    /**
     * @see BugReportClaimRepository::stampLastProcessed()
     */
    public function stampLastProcessed(int $id): bool
    {
        return $this->claims->stampLastProcessed($id);
    }

    /**
     * @see BugReportClaimRepository::clearBlocked()
     */
    public function clearBlocked(int $id): bool
    {
        return $this->claims->clearBlocked($id);
    }
}
