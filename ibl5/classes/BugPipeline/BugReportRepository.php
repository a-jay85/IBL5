<?php

declare(strict_types=1);

namespace BugPipeline;

/**
 * Single source of all queue DB logic for the Discord bug pipeline.
 *
 * All snowflake columns are (string)-cast on read (see castRow()) because
 * db/db.php:100 sets MYSQLI_OPT_INT_AND_FLOAT_NATIVE — BIGINT reads back as
 * PHP int, and json_encode of a bare int loses precision above 2^53.
 *
 * @phpstan-type BugReportRow array{
 *   id: int,
 *   discord_author_id: string,
 *   channel_id: string,
 *   original_message_id: string,
 *   original_text: string,
 *   thread_id: ?string,
 *   class: ?string,
 *   status: string,
 *   lease_owner: ?string,
 *   lease_expires: ?string,
 *   hunt_attempts: int,
 *   pr_number: ?int,
 *   issue_number: ?int,
 *   approval_message_id: ?string,
 *   blocked_until: ?string,
 *   last_gm_reply_at: ?string,
 *   last_processed_at: ?string,
 *   reminder_sent_at: ?string,
 *   created_at: string,
 *   updated_at: string
 * }
 */
class BugReportRepository extends \BaseMysqliRepository
{
    /** Snowflake columns of ibl_bug_reports that must serialize as JSON strings (see db.php:100). */
    private const SNOWFLAKE_COLUMNS = [
        'discord_author_id',
        'channel_id',
        'original_message_id',
        'thread_id',
        'approval_message_id',
    ];

    /**
     * Optional column => mysqli type map for transition(). Column names are compile-time
     * literals from this fixed map (never caller input); values are always bound.
     *
     * @var array<string, string>
     */
    private const OPTIONAL_TRANSITION_COLUMNS = [
        'class'               => 's',
        'pr_number'           => 'i',
        'issue_number'        => 'i',
        'thread_id'           => 's',
        'approval_message_id' => 's',
        'blocked_until'       => 's',
        'hunt_attempts'       => 'i',
    ];

    /**
     * @param array<string, mixed> $row
     * @phpstan-return BugReportRow
     */
    private function castRow(array $row): array
    {
        foreach (self::SNOWFLAKE_COLUMNS as $col) {
            if (isset($row[$col]) && is_scalar($row[$col])) {
                $row[$col] = (string) $row[$col];
            }
        }
        /** @var BugReportRow $row */
        return $row;
    }

    // -------------------------------------------------------------------------
    // Read methods (Phase 2)
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

    // -------------------------------------------------------------------------
    // Write path — insert, upserts, mutators (Phase 3)
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

    public function upsertReporterProfile(string $discordId, string $techLevel): void
    {
        // ON DUPLICATE KEY UPDATE => affected-rows is 0|1|2; success is "no exception", not "=== 1".
        $this->execute(
            'INSERT INTO `ibl_bug_reporter_profile` (discord_author_id, tech_level, created_at, updated_at)
             VALUES (?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE tech_level = VALUES(tech_level), updated_at = NOW()',
            'ss',
            $discordId,
            $techLevel
        );
    }

    public function getReporterTechLevel(string $discordId): ?string
    {
        $row = $this->fetchOne(
            'SELECT tech_level FROM `ibl_bug_reporter_profile` WHERE discord_author_id = ? LIMIT 1',
            's',
            $discordId
        );
        if ($row === null) {
            return null;
        }
        /** @var string $techLevel */
        $techLevel = $row['tech_level'];
        return $techLevel;
    }

    public function upsertPipelineState(string $channelId, string $messageId): void
    {
        // Monotonic: GREATEST() keeps the highest snowflake seen — the cursor only moves forward.
        $this->execute(
            'INSERT INTO `ibl_bug_pipeline_state` (channel_id, last_processed_message_id, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                 last_processed_message_id = GREATEST(last_processed_message_id, VALUES(last_processed_message_id)),
                 updated_at = NOW()',
            'ss',
            $channelId,
            $messageId
        );
    }

    /**
     * Crash-safe, replay-safe enqueue: INSERT + watermark advance run in one transaction.
     * Pre-insert findByOriginalMessageId dedupe makes it replay-safe for PR #4's backfill.
     */
    public function enqueueAuthorizedAndAdvance(string $authorId, string $channelId, string $messageId, string $text): int
    {
        /** @var int $id */
        $id = $this->transactional(function () use ($authorId, $channelId, $messageId, $text): int {
            // Replay-safe: a message already enqueued returns its existing id, no 2nd row.
            $existing = $this->findByOriginalMessageId($messageId);
            if ($existing !== null) {
                $this->upsertPipelineState($channelId, $messageId);
                return $existing['id'];
            }
            // Insert + watermark advance in the SAME transaction => crash between them rolls back both.
            $newId = $this->insertQueuedReport($authorId, $channelId, $messageId, $text);
            $this->upsertPipelineState($channelId, $messageId);
            return $newId;
        });
        return $id;
    }

    public function stampThreadReply(string $threadId): bool
    {
        return $this->execute(
            'UPDATE `ibl_bug_reports` SET last_gm_reply_at = NOW() WHERE thread_id = ?',
            's',
            $threadId
        ) >= 1;
    }

    /**
     * Advance a report from awaiting_ajay to the ready-for-plan sub-state by NULLing
     * approval_message_id. Status stays 'awaiting_ajay' — the cron drives /plan then sets 'planned'.
     */
    public function advanceOnApproval(string $messageId): bool
    {
        // ✅ = "ready-for-plan", NOT "planned". NULL the approval pointer and keep
        // status='awaiting_ajay' so the cron can enumerate awaiting_ajay AND approval_message_id IS NULL.
        return $this->execute(
            "UPDATE `ibl_bug_reports` SET approval_message_id = NULL
             WHERE approval_message_id = ? AND status = 'awaiting_ajay'",
            's',
            $messageId
        ) === 1;
    }

    // -------------------------------------------------------------------------
    // Atomic lease primitives (Phase 4)
    // -------------------------------------------------------------------------

    /**
     * Single-flight claim: the "AND status='queued'" guard makes this atomic.
     * A row already 'hunting' (claimed by another worker) matches 0 rows => returns false.
     */
    public function claimQueued(int $id, string $leaseOwner, string $leaseExpires): bool
    {
        return $this->execute(
            "UPDATE `ibl_bug_reports` SET status = 'hunting', lease_owner = ?, lease_expires = ?
             WHERE id = ? AND status = 'queued'",
            'ssi',
            $leaseOwner,
            $leaseExpires,
            $id
        ) === 1;
    }

    /**
     * Pick oldest queued report and claim it. One attempt — lost-race returns null.
     * @phpstan-return BugReportRow|null
     */
    public function claimNextQueued(string $leaseOwner, string $leaseExpires): ?array
    {
        $row = $this->fetchOne(
            "SELECT id FROM `ibl_bug_reports` WHERE status = 'queued' ORDER BY id ASC LIMIT 1"
        );
        if ($row === null) {
            return null;
        }
        /** @var int $id */
        $id = $row['id'];

        // Lost-race safe: if another worker claimed $id, claimQueued matches 0 rows => null.
        if (!$this->claimQueued($id, $leaseOwner, $leaseExpires)) {
            return null;
        }
        return $this->findById($id);
    }

    /**
     * Reclaim a crashed hunt whose lease expired. Separate primitive — never widens claimQueued.
     * @phpstan-return BugReportRow|null
     */
    public function reclaimStaleLease(string $newLeaseOwner, string $leaseExpires): ?array
    {
        $row = $this->fetchOne(
            "SELECT id FROM `ibl_bug_reports`
             WHERE status = 'hunting' AND lease_expires < NOW()
             ORDER BY id ASC LIMIT 1"
        );
        if ($row === null) {
            return null;
        }
        /** @var int $id */
        $id = $row['id'];

        // Re-assert both predicates in the UPDATE so a concurrent reclaimer can't double-claim.
        $claimed = $this->execute(
            "UPDATE `ibl_bug_reports` SET lease_owner = ?, lease_expires = ?
             WHERE id = ? AND status = 'hunting' AND lease_expires < NOW()",
            'ssi',
            $newLeaseOwner,
            $leaseExpires,
            $id
        ) === 1;
        if (!$claimed) {
            return null;
        }
        return $this->findById($id);
    }

    // -------------------------------------------------------------------------
    // General state/metadata writer (Phase 4b)
    // -------------------------------------------------------------------------

    /**
     * General state-machine writer: set status plus any subset of optional metadata columns.
     * The §3d cron CLI (transition <id> <status> [opts]) is a thin wrapper over this method.
     *
     * Conditional-SQL, NOT null-bind: only columns whose key is present in $opts are written;
     * an absent key keeps the column's current value ("build conditional SQL; bind_param has no
     * NULL type" — core-coding.md). Keys outside OPTIONAL_TRANSITION_COLUMNS are ignored.
     *
     * $setClauses fragments ('status = ?', 'pr_number = ?', …) derive from the fixed
     * OPTIONAL_TRANSITION_COLUMNS constant (compile-time literal column names / bound params) —
     * concatenate literal fragments, do NOT interpolate values.
     *
     * @param array<string, int|string> $opts Accepted keys: pr_number, issue_number, hunt_attempts
     *   (ints); class (ENUM string); thread_id, approval_message_id (snowflakes, bound "s");
     *   blocked_until (DATETIME string). Any other key is ignored.
     * @param bool $releaseLease When true, atomically NULLs lease_owner + lease_expires in the
     *   SAME UPDATE. Required for →needs_human / →queued reset to be a single-flight-safe op.
     * @return bool True when the row (PK $id) was updated.
     */
    public function transition(int $id, string $status, array $opts = [], bool $releaseLease = false): bool
    {
        $setClauses = ['status = ?'];
        $types = 's';
        $values = [$status];

        foreach (self::OPTIONAL_TRANSITION_COLUMNS as $col => $type) {
            if (array_key_exists($col, $opts)) {
                $setClauses[] = $col . ' = ?';
                $types .= $type;
                $values[] = $opts[$col];
            }
        }
        if ($releaseLease) {
            // Literal NULLs (no bound param) — atomic lease-drop for →needs_human / →queued reset.
            $setClauses[] = 'lease_owner = NULL';
            $setClauses[] = 'lease_expires = NULL';
        }
        $setClauses[] = 'updated_at = NOW()';

        // $setClauses elements are literal fragments from the fixed constant map above;
        // no runtime value is interpolated into column names — only ? placeholders for values.
        $query = 'UPDATE `ibl_bug_reports` SET ' . implode(', ', $setClauses) . ' WHERE id = ?';
        $types .= 'i';
        $values[] = $id;

        return $this->execute($query, $types, ...$values) === 1;
    }

    // -------------------------------------------------------------------------
    // Reader methods for PR #4 (Phase 9)
    // -------------------------------------------------------------------------

    /**
     * PR #4 backfill cursor. Returns the watermark snowflake as a STRING, or null on first boot.
     */
    public function findPipelineState(string $channelId): ?string
    {
        $row = $this->fetchOne(
            'SELECT last_processed_message_id FROM `ibl_bug_pipeline_state` WHERE channel_id = ? LIMIT 1',
            's',
            $channelId
        );
        if ($row === null) {
            return null;
        }
        return isset($row['last_processed_message_id']) && is_scalar($row['last_processed_message_id'])
            ? (string) $row['last_processed_message_id']
            : null;
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
}
