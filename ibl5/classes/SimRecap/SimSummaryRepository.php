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
class SimSummaryRepository extends \Database\BaseMysqliRepository
{
    /** PK arm: gr.box_id points at a live ibl_box_scores_teams row. `bst.id = NULL` is never true, so this arm self-disables on legacy NULL rows — no IS NOT NULL guard is needed. */
    private const BOX_MATCH_BY_ID = "EXISTS (SELECT 1 FROM `ibl_box_scores_teams` bst WHERE bst.`id` = gr.`box_id`)";

    /** Natural-key arm: the pre-box_id fallback, kept so a stale pointer (boxscore reimport reassigns AUTO_INCREMENT ids) never suppresses a game whose box score still exists. */
    private const BOX_MATCH_BY_NATURAL_KEY = "EXISTS ("
        . "     SELECT 1 FROM `ibl_box_scores_teams` bst"
        . "     WHERE bst.`game_date` = gr.`game_date`"
        . "       AND bst.`visitor_teamid` = gr.`visitor_teamid`"
        . "       AND bst.`home_teamid` = gr.`home_teamid`"
        . "       AND COALESCE(bst.`game_of_that_day`, 0) = gr.`game_of_that_day`"
        . " )";

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
     * Host-scoped eligibility for claim-next. Picked by `match` on a host that
     * ParkPolicy::assertHost() already validated; no input is ever concatenated.
     * Actions takes a Mac-routed row back after 15 minutes; the Mac backs up a
     * normal pending row after 45 minutes (GitHub outage or skipped run).
     */
    private const ELIGIBLE_FOR_ACTIONS = "(`route` IN ('any','actions')"
        . " OR (`route` = 'mac' AND COALESCE(`routed_at`, `created_at`) <= NOW() - INTERVAL 15 MINUTE))";

    private const ELIGIBLE_FOR_MAC = "(`route` = 'mac'"
        . " OR (`route` IN ('any','actions') AND COALESCE(`routed_at`, `created_at`) <= NOW() - INTERVAL 45 MINUTE))";

    /**
     * Atomic single-flight claim: the WHERE sim=? AND status='pending' guard
     * ensures only one caller proceeds. Returns true on success, false on lost race.
     * Ignores route on purpose: `claim --sim=N` is the human override path.
     */
    public function claimPending(int $sim, string $host): bool
    {
        ParkPolicy::assertHost($host);

        return $this->execute(
            "UPDATE `ibl_sim_summaries`
             SET `status` = 'generating', `claimed_at` = NOW(), `claimed_by` = ?,
                 `attempts` = `attempts` + 1, `blocked_until` = NULL
             WHERE `sim` = ? AND `status` = 'pending'",
            'si',
            $host,
            $sim
        ) === 1;
    }

    /**
     * Select the oldest pending sim this host may claim, and claim it.
     * The SELECT is only a hint; the UPDATE re-asserts every predicate and is
     * the authority (lost race → null).
     */
    public function claimNextPending(string $host): ?int
    {
        ParkPolicy::assertHost($host);
        $eligible = match ($host) {
            'actions' => self::ELIGIBLE_FOR_ACTIONS,
            default => self::ELIGIBLE_FOR_MAC,
        };

        $row = $this->fetchOne(
            "SELECT `sim` FROM `ibl_sim_summaries`"
            . " WHERE `status` = 'pending'"
            . " AND (`blocked_until` IS NULL OR `blocked_until` <= NOW())"
            . " AND " . $eligible
            . " ORDER BY `sim` ASC LIMIT 1"
        );
        if ($row === null) {
            return null;
        }
        /** @var int $sim */
        $sim = $row['sim'];

        $claimed = $this->execute(
            "UPDATE `ibl_sim_summaries`"
            . " SET `status` = 'generating', `claimed_at` = NOW(), `claimed_by` = ?,"
            . " `attempts` = `attempts` + 1, `blocked_until` = NULL"
            . " WHERE `sim` = ? AND `status` = 'pending'"
            . " AND (`blocked_until` IS NULL OR `blocked_until` <= NOW())"
            . " AND " . $eligible,
            'si',
            $host,
            $sim
        ) === 1;

        return $claimed ? $sim : null;
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
     * Park a claimed row after a failed generation, routed by cause.
     *
     * Reads the row, asks ParkPolicy::decide() for the route, and writes the
     * answer in ONE UPDATE guarded by status='generating' AND claimed_by=?.
     * Zero affected rows (another host holds the claim, or the row moved on)
     * returns outcome 'none', the lost-claim signal. `routed_at` records when
     * the row becomes claimable: blocked_until when set, else now.
     *
     * @return array{outcome: string, route: ?string, blocked_until: ?string, prior_notified: ?string}
     */
    public function parkByCause(int $sim, string $host, string $cause, ?int $resetEpoch): array
    {
        ParkPolicy::assertHost($host);
        ParkPolicy::assertCause($cause);
        $none = ['outcome' => 'none', 'route' => null, 'blocked_until' => null, 'prior_notified' => null];

        $row = $this->fetchOne(
            "SELECT `route`, `attempts`, `flaky_failures`, `transport_failures`, `usage_waits`,
                    `auth_failed_hosts`, `notified_state`,
                    UNIX_TIMESTAMP(`created_at`) AS `created_epoch`, UNIX_TIMESTAMP(NOW()) AS `now_epoch`
             FROM `ibl_sim_summaries`
             WHERE `sim` = ? AND `status` = 'generating' AND `claimed_by` = ?",
            'is',
            $sim,
            $host
        );
        if ($row === null || !is_numeric($row['created_epoch']) || !is_numeric($row['now_epoch'])) {
            return $none;
        }

        $decision = ParkPolicy::decide($cause, $host, [
            'route' => is_string($row['route']) ? $row['route'] : 'any',
            'attempts' => is_int($row['attempts']) ? $row['attempts'] : 0,
            'flaky_failures' => is_int($row['flaky_failures']) ? $row['flaky_failures'] : 0,
            'transport_failures' => is_int($row['transport_failures']) ? $row['transport_failures'] : 0,
            'usage_waits' => is_int($row['usage_waits']) ? $row['usage_waits'] : 0,
            'auth_failed_hosts' => is_string($row['auth_failed_hosts']) ? $row['auth_failed_hosts'] : '',
            'created_epoch' => (int) $row['created_epoch'],
        ], $resetEpoch, (int) $row['now_epoch']);

        $status = $decision['outcome'] === 'failed' ? 'failed' : 'pending';
        $blockedEpoch = $decision['blockedUntilEpoch'] ?? 0;
        $counters = $decision['counters'];

        $updated = $this->execute(
            "UPDATE `ibl_sim_summaries`
             SET `status` = ?, `claimed_at` = NULL, `route` = ?, `last_cause` = ?,
                 `flaky_failures` = ?, `transport_failures` = ?, `usage_waits` = ?,
                 `auth_failed_hosts` = ?,
                 `attempts` = `attempts` - LEAST(`attempts`, ?),
                 `blocked_until` = IF(? > 0, FROM_UNIXTIME(?), NULL),
                 `routed_at` = IF(? > 0, FROM_UNIXTIME(?), NOW())
             WHERE `sim` = ? AND `status` = 'generating' AND `claimed_by` = ?",
            'sssiiisiiiiiis',
            $status,
            $decision['route'],
            $cause,
            $counters['flaky_failures'],
            $counters['transport_failures'],
            $counters['usage_waits'],
            $counters['auth_failed_hosts'],
            $counters['attempts_refund'],
            $blockedEpoch,
            $blockedEpoch,
            $blockedEpoch,
            $blockedEpoch,
            $sim,
            $host
        );
        if ($updated !== 1) {
            return $none;
        }

        $after = $this->find($sim);

        return [
            'outcome' => $decision['outcome'],
            'route' => $decision['route'],
            'blocked_until' => $after['blocked_until'] ?? null,
            'prior_notified' => is_string($row['notified_state']) ? $row['notified_state'] : null,
        ];
    }

    /**
     * Compare-and-set the #admin-chat state for a sim. Returns true only for
     * the caller that moved it from $prior to $next — the exactly-once
     * primitive across both hosts.
     */
    public function transitionNotifiedState(int $sim, ?string $prior, string $next): bool
    {
        ParkPolicy::assertNotifyState($next);
        if ($prior === null) {
            return $this->execute(
                "UPDATE `ibl_sim_summaries` SET `notified_state` = ?
                 WHERE `sim` = ? AND `notified_state` IS NULL",
                'si',
                $next,
                $sim
            ) === 1;
        }
        ParkPolicy::assertNotifyState($prior);

        return $this->execute(
            "UPDATE `ibl_sim_summaries` SET `notified_state` = ?
             WHERE `sim` = ? AND `notified_state` <=> ?",
            'sis',
            $next,
            $sim,
            $prior
        ) === 1;
    }

    /**
     * Undo transitionNotifiedState() after the post itself failed, so the next
     * state change (or the same one on another host) can post again.
     */
    public function revertNotifiedState(int $sim, string $current, ?string $prior): bool
    {
        ParkPolicy::assertNotifyState($current);
        if ($prior === null) {
            return $this->execute(
                "UPDATE `ibl_sim_summaries` SET `notified_state` = NULL
                 WHERE `sim` = ? AND `notified_state` <=> ?",
                'is',
                $sim,
                $current
            ) === 1;
        }

        return $this->transitionNotifiedState($sim, $current, $prior);
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
     * @return array{sim: int, status: string, recap_text: ?string, intro_text: ?string, outro_text: ?string, themes_used: ?string, attempts: int, claimed_at: ?string, blocked_until: ?string, generated_at: ?string, route: string, routed_at: ?string, claimed_by: ?string, last_cause: ?string, flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, notified_state: ?string}|null
     */
    public function find(int $sim): ?array
    {
        /** @var array{sim: int, status: string, recap_text: ?string, intro_text: ?string, outro_text: ?string, themes_used: ?string, attempts: int, claimed_at: ?string, blocked_until: ?string, generated_at: ?string, route: string, routed_at: ?string, claimed_by: ?string, last_cause: ?string, flaky_failures: int, transport_failures: int, usage_waits: int, auth_failed_hosts: string, notified_state: ?string}|null */
        return $this->fetchOne(
            "SELECT `sim`, `status`, `recap_text`, `intro_text`, `outro_text`, `themes_used`,
                    `attempts`, `claimed_at`, `blocked_until`, `generated_at`,
                    `route`, `routed_at`, `claimed_by`, `last_cause`, `flaky_failures`,
                    `transport_failures`, `usage_waits`, `auth_failed_hosts`, `notified_state`
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
     * sorted by sort_order ascending.
     *
     * Not the source of the postable document: a bad `game_of_that_day` index makes
     * this filter drop games silently, so RecapBox reads every row via findGameRecaps().
     *
     * Distinct from findGameRecaps(), which is the unfiltered write-verification
     * read. Join partner is `ibl_box_scores_teams`, NOT `ibl_schedule`
     * (shared-context decision 51 — `ibl_schedule` is churned on every sim
     * upload and is an unreliable archive source). Returns an empty array when
     * no per-game rows qualify; the View must handle that case.
     *
     * @return list<array<string, mixed>>
     */
    public function findDisplayableGameRecaps(int $sim): array
    {
        // EXISTS, not INNER JOIN: ibl_box_scores_teams stores one row per TEAM
        // side, so a join on the game's natural key matches twice and would
        // render every per-game recap twice. The OR combines a PK pointer arm
        // (fast, tolerant of NULL self-disabling) with the natural-key fallback
        // (reimport-tolerant: AUTO_INCREMENT ids change on reimport, stale
        // pointer stays matched via the natural key).
        $sql = "SELECT gr.`game_date`, gr.`visitor_teamid`, gr.`home_teamid`,"
            . " gr.`game_of_that_day`, gr.`box_id`, gr.`sort_order`, gr.`recap_text`"
            . " FROM `ibl_sim_game_recaps` gr"
            . " WHERE gr.`sim` = ?"
            . " AND (" . self::BOX_MATCH_BY_ID . " OR " . self::BOX_MATCH_BY_NATURAL_KEY . ")"
            . " ORDER BY gr.`sort_order` ASC, gr.`id` ASC";

        /** @var list<array<string, mixed>> */
        return $this->fetchAll($sql, 'i', $sim);
    }

    /**
     * Exact complement of findDisplayableGameRecaps() over the same sim: every row
     * from findGameRecaps() appears in exactly one of the two. Callers render these
     * as a warning. The rows still go in the postable document (RecapBox).
     *
     * @return list<array<string, mixed>>
     */
    public function findOrphanedGameRecaps(int $sim): array
    {
        $sql = "SELECT gr.`game_date`, gr.`visitor_teamid`, gr.`home_teamid`,"
            . " gr.`game_of_that_day`, gr.`box_id`, gr.`sort_order`"
            . " FROM `ibl_sim_game_recaps` gr"
            . " WHERE gr.`sim` = ?"
            . " AND (NOT " . self::BOX_MATCH_BY_ID . " AND NOT " . self::BOX_MATCH_BY_NATURAL_KEY . ")"
            . " ORDER BY gr.`sort_order` ASC, gr.`id` ASC";

        /** @var list<array<string, mixed>> */
        return $this->fetchAll($sql, 'i', $sim);
    }

    /**
     * Resolve the ibl_box_scores_teams.id pointer for one game from its natural key.
     * Returns MIN(id) of the two per-team-side rows, or null when no archived box
     * score matches. Callers must persist null rather than reject the game — a missing
     * box score is a warning at read time, never a reason to discard the sim recap.
     *
     * @return int|null ibl_box_scores_teams.id, or null when no archived box score matches
     */
    public function resolveBoxId(string $gameDate, int $visitorTeamid, int $homeTeamid, int $gameOfThatDay): ?int
    {
        $sql = "SELECT MIN(`id`) AS `box_id`"
            . " FROM `ibl_box_scores_teams`"
            . " WHERE `game_date` = ?"
            . "   AND `visitor_teamid` = ?"
            . "   AND `home_teamid` = ?"
            . "   AND COALESCE(`game_of_that_day`, 0) = ?";

        $row   = $this->fetchOne($sql, 'siii', $gameDate, $visitorTeamid, $homeTeamid, $gameOfThatDay);
        $boxId = $row['box_id'] ?? null;

        return is_numeric($boxId) ? (int) $boxId : null;
    }

}
