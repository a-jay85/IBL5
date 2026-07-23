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
     * Store a completed recap: the envelope row plus every per-game child row,
     * in ONE transaction. A mid-write failure rolls the whole thing back, so a
     * partial recap can never surface.
     *
     * Upserts so a manually-backfilled sim with no queued row still stores.
     * themes_used=null uses the two-statement branch (bind_param has no NULL type);
     * the child INSERT uses the same two-variant idiom for a null box_id.
     *
     * Returns void — an idempotent re-store of identical text legitimately yields
     * affected_rows=0, so callers confirm with find() / findGameRecaps().
     *
     * @param list<array{season_year: int, game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, box_id: ?int, sort_order: int, recap_text: string}> $gameRecaps
     */
    public function markDone(
        int $sim,
        string $introText,
        string $outroText,
        string $recapText,
        array $gameRecaps,
        ?string $themesJson
    ): void {
        $this->transactional(function () use (
            $sim,
            $introText,
            $outroText,
            $recapText,
            $gameRecaps,
            $themesJson
        ): void {
            $this->upsertEnvelope($sim, $introText, $outroText, $recapText, $themesJson);

            foreach ($gameRecaps as $game) {
                $this->upsertGameRecap($sim, $game);
            }
        });
    }

    /**
     * Envelope upsert. Two branches because bind_param has no NULL type.
     */
    private function upsertEnvelope(
        int $sim,
        string $introText,
        string $outroText,
        string $recapText,
        ?string $themesJson
    ): void {
        if ($themesJson === null) {
            $this->execute(
                "INSERT INTO `ibl_sim_summaries`
                     (`sim`, `status`, `recap_text`, `intro_text`, `outro_text`, `generated_at`)
                 VALUES (?, 'done', ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE
                     `status` = 'done', `recap_text` = ?, `intro_text` = ?, `outro_text` = ?,
                     `generated_at` = NOW(), `blocked_until` = NULL",
                'issssss',
                $sim,
                $recapText,
                $introText,
                $outroText,
                $recapText,
                $introText,
                $outroText
            );

            return;
        }

        $this->execute(
            "INSERT INTO `ibl_sim_summaries`
                 (`sim`, `status`, `recap_text`, `intro_text`, `outro_text`, `themes_used`, `generated_at`)
             VALUES (?, 'done', ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                 `status` = 'done', `recap_text` = ?, `intro_text` = ?, `outro_text` = ?,
                 `themes_used` = ?, `generated_at` = NOW(), `blocked_until` = NULL",
            'issssssss',
            $sim,
            $recapText,
            $introText,
            $outroText,
            $themesJson,
            $recapText,
            $introText,
            $outroText,
            $themesJson
        );
    }

    /**
     * Child upsert, keyed on uniq_game so a re-store updates prose in place.
     * Two variants: a null box_id omits the column (defaults NULL) rather than
     * binding null, and sets box_id = NULL in the UPDATE clause.
     *
     * @param array{season_year: int, game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, box_id: ?int, sort_order: int, recap_text: string} $game
     */
    private function upsertGameRecap(int $sim, array $game): void
    {
        if ($game['box_id'] === null) {
            $this->execute(
                "INSERT INTO `ibl_sim_game_recaps`
                     (`sim`, `season_year`, `game_date`, `visitor_teamid`, `home_teamid`,
                      `game_of_that_day`, `sort_order`, `recap_text`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     `sim` = ?, `box_id` = NULL, `sort_order` = ?, `recap_text` = ?",
                'iisiiiisiis',
                $sim,
                $game['season_year'],
                $game['game_date'],
                $game['visitor_teamid'],
                $game['home_teamid'],
                $game['game_of_that_day'],
                $game['sort_order'],
                $game['recap_text'],
                $sim,
                $game['sort_order'],
                $game['recap_text']
            );

            return;
        }

        $this->execute(
            "INSERT INTO `ibl_sim_game_recaps`
                 (`sim`, `season_year`, `game_date`, `visitor_teamid`, `home_teamid`,
                  `game_of_that_day`, `box_id`, `sort_order`, `recap_text`)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 `sim` = ?, `box_id` = ?, `sort_order` = ?, `recap_text` = ?",
            'iisiiiiisiiis',
            $sim,
            $game['season_year'],
            $game['game_date'],
            $game['visitor_teamid'],
            $game['home_teamid'],
            $game['game_of_that_day'],
            $game['box_id'],
            $game['sort_order'],
            $game['recap_text'],
            $sim,
            $game['box_id'],
            $game['sort_order'],
            $game['recap_text']
        );
    }

    /**
     * Read back a sim summary row, or null if absent.
     *
     * @return array{sim: int, status: string, recap_text: ?string, intro_text: ?string, outro_text: ?string, themes_used: ?string, attempts: int, claimed_at: ?string, blocked_until: ?string, generated_at: ?string}|null
     */
    public function find(int $sim): ?array
    {
        /** @var array{sim: int, status: string, recap_text: ?string, intro_text: ?string, outro_text: ?string, themes_used: ?string, attempts: int, claimed_at: ?string, blocked_until: ?string, generated_at: ?string}|null */
        return $this->fetchOne(
            "SELECT `sim`, `status`, `recap_text`, `intro_text`, `outro_text`, `themes_used`,
                    `attempts`, `claimed_at`, `blocked_until`, `generated_at`
             FROM `ibl_sim_summaries`
             WHERE `sim` = ?",
            'i',
            $sim
        );
    }

    /**
     * Read back a sim's per-game recaps in presentation order.
     *
     * @return list<array{id: int, sim: int, season_year: int, game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, box_id: ?int, sort_order: int, recap_text: string, created_at: string}>
     */
    public function findGameRecaps(int $sim): array
    {
        /** @var list<array{id: int, sim: int, season_year: int, game_date: string, visitor_teamid: int, home_teamid: int, game_of_that_day: int, box_id: ?int, sort_order: int, recap_text: string, created_at: string}> */
        return $this->fetchAll(
            "SELECT `id`, `sim`, `season_year`, `game_date`, `visitor_teamid`, `home_teamid`,
                    `game_of_that_day`, `box_id`, `sort_order`, `recap_text`, `created_at`
             FROM `ibl_sim_game_recaps`
             WHERE `sim` = ?
             ORDER BY `sort_order` ASC, `id` ASC",
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

    /**
     * Every recap row, newest sim first, for the admin viewer index.
     *
     * `recap_text` is deliberately excluded: the index renders metadata only,
     * and the bodies are MEDIUMTEXT.
     *
     * @return list<array<string, mixed>>
     */
    public function listAll(): array
    {
        $sql = "SELECT `sim`, `status`, `attempts`, `generated_at`, `created_at`,"
            . " CHAR_LENGTH(`recap_text`) AS `recap_length`"
            . " FROM `ibl_sim_summaries`"
            . " ORDER BY `sim` DESC"; // @phpstan-ignore ibl.orderByMissingTiebreaker (sim is the PK of ibl_sim_summaries — inherently unique)
        /** @var list<array<string, mixed>> */
        return $this->fetchAll($sql);
    }

    /**
     * Per-game recap rows for one sim that have a verified archived box score,
     * sorted by sort_order ascending — the admin viewer's display read.
     *
     * Distinct from findGameRecaps(), which is the unfiltered write-verification
     * read. The INNER JOIN here is an existence filter: only games with an
     * archived box-score record are displayed. Join partner is
     * `ibl_box_scores_teams`, NOT `ibl_schedule` (shared-context decision 51 —
     * `ibl_schedule` is churned on every sim upload and is an unreliable archive
     * source). Returns an empty array when no per-game rows qualify; the View
     * must handle that case.
     *
     * @return list<array<string, mixed>>
     */
    public function findDisplayableGameRecaps(int $sim): array
    {
        // EXISTS, not INNER JOIN: ibl_box_scores_teams stores one row per TEAM
        // side, so a join on the game's natural key matches twice and would
        // render every per-game recap twice.
        $sql = "SELECT gr.`game_date`, gr.`visitor_teamid`, gr.`home_teamid`,"
            . " gr.`game_of_that_day`, gr.`sort_order`, gr.`recap_text`"
            . " FROM `ibl_sim_game_recaps` gr"
            . " WHERE gr.`sim` = ?"
            . " AND EXISTS ("
            . "     SELECT 1 FROM `ibl_box_scores_teams` bst"
            . "     WHERE bst.`game_date` = gr.`game_date`"
            . "       AND bst.`visitor_teamid` = gr.`visitor_teamid`"
            . "       AND bst.`home_teamid` = gr.`home_teamid`"
            . "       AND COALESCE(bst.`game_of_that_day`, 0) = gr.`game_of_that_day`"
            . " )"
            . " ORDER BY gr.`sort_order` ASC, gr.`id` ASC";

        /** @var list<array<string, mixed>> */
        return $this->fetchAll($sql, 'i', $sim);
    }
}
