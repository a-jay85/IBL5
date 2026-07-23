<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Single source of all queue DB logic for the sim recap pipeline.
 *
 * Mirrors BugPipeline\BugReportRepository's atomic conditional-UPDATE idiom:
 * execute(...) === 1 is the single-flight primitive — a losing racer gets 0.
 * No caller anywhere composes SQL against ibl_sim_summaries directly.
 */
class SimSummaryRepository extends \BaseMysqliRepository
{
    /**
     * Insert a pending row for $sim if none exists. Returns true when a row was
     * created, false when one already existed (idempotent by PK).
     */
    public function queuePendingIfAbsent(int $sim): bool
    {
        return $this->execute(
            "INSERT IGNORE INTO `ibl_sim_summaries` (`sim`, `status`) VALUES (?, 'pending')",
            'i',
            $sim
        ) === 1;
    }

    /**
     * Atomic single-flight claim: the WHERE sim=? AND status='pending' guard
     * ensures only one caller proceeds. Returns true on success, false on lost race.
     */
    public function claimPending(int $sim): bool
    {
        return $this->execute(
            "UPDATE `ibl_sim_summaries`
             SET `status` = 'generating', `claimed_at` = NOW(),
                 `attempts` = `attempts` + 1, `blocked_until` = NULL
             WHERE `sim` = ? AND `status` = 'pending'",
            'i',
            $sim
        ) === 1;
    }

    /**
     * Select the oldest eligible pending sim and claim it.
     * The SELECT is only a hint; the UPDATE is the authority (lost race → null).
     */
    public function claimNextPending(): ?int
    {
        $row = $this->fetchOne(
            "SELECT `sim` FROM `ibl_sim_summaries`
             WHERE `status` = 'pending'
               AND (`blocked_until` IS NULL OR `blocked_until` <= NOW())
             ORDER BY `sim` ASC LIMIT 1"
        );
        if ($row === null) {
            return null;
        }
        /** @var int $sim */
        $sim = $row['sim'];

        if (!$this->claimPending($sim)) {
            return null;
        }
        return $sim;
    }

    /**
     * Recover an abandoned generating row whose claim is older than $staleMinutes.
     * Re-asserts both predicates in the UPDATE to stop double-reclaim.
     * Returns the sim on success, null if none found or lost race.
     */
    public function reclaimStaleClaim(int $staleMinutes = 45): ?int
    {
        $row = $this->fetchOne(
            "SELECT `sim` FROM `ibl_sim_summaries`
             WHERE `status` = 'generating'
               AND `claimed_at` < NOW() - INTERVAL ? MINUTE
             ORDER BY `sim` ASC LIMIT 1",
            'i',
            $staleMinutes
        );
        if ($row === null) {
            return null;
        }
        /** @var int $sim */
        $sim = $row['sim'];

        $claimed = $this->execute(
            "UPDATE `ibl_sim_summaries`
             SET `status` = 'pending', `claimed_at` = NULL
             WHERE `sim` = ? AND `status` = 'generating'
               AND `claimed_at` < NOW() - INTERVAL ? MINUTE",
            'ii',
            $sim,
            $staleMinutes
        ) === 1;

        return $claimed ? $sim : null;
    }

    /**
     * Park or fail a claimed row after a generation attempt.
     *
     * If attempts >= $maxAttempts → mark failed, return 'failed'.
     * Otherwise → park as pending with backoff, return 'pending'.
     * If the row is not in generating state → return 'none' (lost claim).
     *
     * The ceiling is checked first so an at-ceiling row can never be parked
     * for another retry.
     */
    public function parkOrFail(int $sim, int $maxAttempts = 2, int $backoffMinutes = 30): string
    {
        $failed = $this->execute(
            "UPDATE `ibl_sim_summaries`
             SET `status` = 'failed', `claimed_at` = NULL
             WHERE `sim` = ? AND `status` = 'generating' AND `attempts` >= ?",
            'ii',
            $sim,
            $maxAttempts
        );
        if ($failed === 1) {
            return 'failed';
        }

        $parked = $this->execute(
            "UPDATE `ibl_sim_summaries`
             SET `status` = 'pending', `claimed_at` = NULL,
                 `blocked_until` = NOW() + INTERVAL ? MINUTE
             WHERE `sim` = ? AND `status` = 'generating'",
            'ii',
            $backoffMinutes,
            $sim
        );
        if ($parked === 1) {
            return 'pending';
        }

        return 'none';
    }

    /**
     * Store a completed recap. Upserts so a manually-backfilled sim with no
     * queued row still stores. themes_used=null uses the two-statement branch
     * (bind_param has no NULL type).
     *
     * Returns void — an idempotent re-store of identical text legitimately yields
     * affected_rows=0, so callers confirm with find().
     */
    public function markDone(int $sim, string $recapText, ?string $themesJson): void
    {
        if ($themesJson === null) {
            $this->execute(
                "INSERT INTO `ibl_sim_summaries` (`sim`, `status`, `recap_text`, `generated_at`)
                 VALUES (?, 'done', ?, NOW())
                 ON DUPLICATE KEY UPDATE
                     `status` = 'done', `recap_text` = ?,
                     `generated_at` = NOW(), `blocked_until` = NULL",
                'iss',
                $sim,
                $recapText,
                $recapText
            );
        } else {
            $this->execute(
                "INSERT INTO `ibl_sim_summaries`
                     (`sim`, `status`, `recap_text`, `themes_used`, `generated_at`)
                 VALUES (?, 'done', ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                     `status` = 'done', `recap_text` = ?, `themes_used` = ?,
                     `generated_at` = NOW(), `blocked_until` = NULL",
                'issss',
                $sim,
                $recapText,
                $themesJson,
                $recapText,
                $themesJson
            );
        }
    }

    /**
     * Read back a sim summary row, or null if absent.
     *
     * @return array{sim: int, status: string, recap_text: ?string, themes_used: ?string, attempts: int, claimed_at: ?string, blocked_until: ?string, generated_at: ?string}|null
     */
    public function find(int $sim): ?array
    {
        /** @var array{sim: int, status: string, recap_text: ?string, themes_used: ?string, attempts: int, claimed_at: ?string, blocked_until: ?string, generated_at: ?string}|null */
        return $this->fetchOne(
            "SELECT `sim`, `status`, `recap_text`, `themes_used`,
                    `attempts`, `claimed_at`, `blocked_until`, `generated_at`
             FROM `ibl_sim_summaries`
             WHERE `sim` = ?",
            'i',
            $sim
        );
    }

    /**
     * Return raw themes_used JSON strings from the most recent $limit done sims,
     * newest first. Decoding is the caller's job. Malformed JSON is returned as-is.
     *
     * @return list<array{themes_used: string}>
     */
    public function recentThemes(int $limit = 5): array
    {
        $sql = "SELECT `themes_used` FROM `ibl_sim_summaries`"
            . " WHERE `status` = 'done' AND `themes_used` IS NOT NULL"
            . " ORDER BY `sim` DESC LIMIT ?"; // @phpstan-ignore ibl.orderByMissingTiebreaker (sim is the PK of ibl_sim_summaries — inherently unique)
        /** @var list<array{themes_used: string}> */
        return $this->fetchAll(
            $sql,
            'i',
            $limit
        );
    }
}
