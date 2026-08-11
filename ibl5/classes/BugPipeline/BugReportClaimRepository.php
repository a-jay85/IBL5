<?php

declare(strict_types=1);

namespace BugPipeline;

use BugPipeline\Contracts\BugReportClaimRepositoryInterface;

/**
 * Lease/claim + state-machine writer for the Discord bug pipeline.
 *
 * Owns the atomic lease primitives (single-flight claims, stale-lease reclaim,
 * blocked-hunt resume), the general transition() writer, and the conditional
 * writers transition()'s value-bind cannot express. Split out of
 * {@see BugReportRepository} (backlog 1.26); the facade delegates to it.
 *
 * @phpstan-import-type BugReportRow from \BugPipeline\BugReportRowCasting
 */
class BugReportClaimRepository extends \BaseMysqliRepository implements BugReportClaimRepositoryInterface
{
    use BugReportRowCasting;

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
     * Fetch a single report by PK, snowflake-cast. Private helper so the claim
     * primitives can return the freshly-claimed row without depending on the
     * facade's read methods.
     *
     * @phpstan-return BugReportRow|null
     */
    private function fetchReportById(int $id): ?array
    {
        $row = $this->fetchOne('SELECT * FROM `ibl_bug_reports` WHERE id = ? LIMIT 1', 'i', $id);
        return $row === null ? null : $this->castRow($row);
    }

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
        return $this->fetchReportById($id);
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
        return $this->fetchReportById($id);
    }

    /**
     * Pick the oldest queued report that is READY TO HUNT and claim it into `hunting`.
     * One attempt — lost-race returns null (PR #5b Phase 2 — the deadlock-closing claim).
     *
     * Narrower than claimNextQueued() by design: a hunt may only start on a row that has
     * already been classified (`class IS NOT NULL`) and is not parked by a usage-limit
     * backoff (`blocked_until` in the future). claimNextQueued() stays as-is for any caller
     * that wants the raw oldest-queued primitive; this method never widens it.
     *
     * @phpstan-return BugReportRow|null
     */
    public function claimNextHuntable(string $leaseOwner, string $leaseExpires): ?array
    {
        $row = $this->fetchOne(
            "SELECT id FROM `ibl_bug_reports`
             WHERE status = 'queued'
               AND class IS NOT NULL
               AND (blocked_until IS NULL OR blocked_until <= NOW())
             ORDER BY id ASC LIMIT 1"
        );
        if ($row === null) {
            return null;
        }
        /** @var int $id */
        $id = $row['id'];

        // Lost-race safe: claimQueued re-asserts `status='queued'` in the UPDATE (0 rows => null).
        if (!$this->claimQueued($id, $leaseOwner, $leaseExpires)) {
            return null;
        }
        return $this->fetchReportById($id);
    }

    /**
     * Atomically resume a usage-limit-parked hunt: `blocked` → `hunting`, re-stamping the lease.
     * Returns true only when THIS call flipped the row (PR #5b Phase 7 — the resume guard).
     *
     * transition() is an unconditional `WHERE id = ?` write, so it cannot express the
     * single-flight predicate two overlapping ticks need: both surface the ripe `blocked`
     * row, both call this, only the one whose UPDATE still sees `status='blocked'` wins
     * (affected_rows == 1); the loser gets 0 and skips. Re-stamping lease_expires closes the
     * window where reclaimStaleLease() could immediately steal the freshly-resumed hunt.
     */
    public function resumeBlockedHunt(string $leaseOwner, string $leaseExpires, int $id): bool
    {
        return $this->execute(
            "UPDATE `ibl_bug_reports`
                SET status = 'hunting', lease_owner = ?, lease_expires = ?, updated_at = NOW()
             WHERE id = ?
               AND status = 'blocked'
               AND (blocked_until IS NULL OR blocked_until <= NOW())",
            'ssi',
            $leaseOwner,
            $leaseExpires,
            $id
        ) === 1;
    }

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

    public function stampThreadReply(string $threadId): bool
    {
        return $this->execute(
            'UPDATE `ibl_bug_reports` SET last_gm_reply_at = NOW() WHERE thread_id = ?',
            's',
            $threadId
        ) >= 1;
    }

    /**
     * At-most-once idle reminder stamp. The `AND reminder_sent_at IS NULL` guard makes a repeat
     * call a no-op, so a row can receive at most one reminder over its lifetime (PR #5a Phase 6).
     * transition()'s value-bind cannot express this conditional WHERE, hence a dedicated method.
     */
    public function markReminderSent(int $id): bool
    {
        return $this->execute(
            "UPDATE `ibl_bug_reports` SET reminder_sent_at = NOW(), updated_at = NOW()
             WHERE id = ? AND reminder_sent_at IS NULL",
            'i',
            $id
        ) === 1;
    }

    /** Stamp last_processed_at = NOW() so the same GM reply is not re-processed next tick. */
    public function stampLastProcessed(int $id): bool
    {
        return $this->execute(
            'UPDATE `ibl_bug_reports` SET last_processed_at = NOW(), updated_at = NOW() WHERE id = ?',
            'i',
            $id
        ) === 1;
    }

    /**
     * Clear a usage-limit park stamp (blocked_until = NULL). Literal NULL, no bound value —
     * mirrors transition()'s $releaseLease idiom (bind_param has no NULL type). PR #5a Phase 6 resume.
     */
    public function clearBlocked(int $id): bool
    {
        return $this->execute(
            'UPDATE `ibl_bug_reports` SET blocked_until = NULL, updated_at = NOW() WHERE id = ?',
            'i',
            $id
        ) === 1;
    }

    /** Replace the stored snapshot after the GM edits the source message. Text only — no state. */
    public function updateSourceText(string $originalMessageId, string $text): bool
    {
        return $this->execute(
            'UPDATE `ibl_bug_reports` SET original_text = ?, updated_at = NOW()
             WHERE original_message_id = ?',
            'ss',
            $text,
            $originalMessageId
        ) >= 1;
    }

    /**
     * Re-open an edited row for reclassification. hunt_attempts is deliberately ABSENT from the
     * SET list — an edit must never buy a GM extra Opus hunts. class = NULL keeps
     * claimNextHuntable() (status='queued' AND class IS NOT NULL) from firing before reclassify.
     */
    public function reviveForReclassify(string $originalMessageId): bool
    {
        // Literal fragments only: the IN list is built from a constant of compile-time literals,
        // never from input — same idiom as transition()'s OPTIONAL_TRANSITION_COLUMNS.
        $in = "'" . implode("','", self::RECLASSIFIABLE_ON_EDIT) . "'";
        return $this->execute(
            "UPDATE `ibl_bug_reports` SET status = 'queued', class = NULL, updated_at = NOW()
              WHERE original_message_id = ? AND status IN ($in)",
            's',
            $originalMessageId
        ) === 1;
    }

    /** Source message deleted: drop only rows no human/hunter is mid-flight on. */
    public function markSourceDeleted(string $originalMessageId): bool
    {
        return $this->execute(
            "UPDATE `ibl_bug_reports` SET status = 'dropped', updated_at = NOW()
              WHERE original_message_id = ?
                AND status IN ('queued','gathering','awaiting_info')",
            's',
            $originalMessageId
        ) === 1;
    }
}
