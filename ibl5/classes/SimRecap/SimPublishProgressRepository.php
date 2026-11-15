<?php

declare(strict_types=1);

namespace SimRecap;

/**
 * Per-sim progress of the Discord recap drip.
 *
 * One row per sim in ibl_sim_publish_progress. IBLbot claims a row through a
 * service-key route and reports each posted step back. Claim and advance are
 * conditional UPDATEs: execute(...) === 1 is the single-flight primitive, so a
 * duplicate trigger, a second process, or an out-of-order step affects 0 rows.
 * Every value is a bound parameter, including the time and attempt constants.
 */
class SimPublishProgressRepository extends \Database\BaseMysqliRepository
{
    /** A claim whose heartbeat is older than this is abandoned and may be taken over. The longest planned gap between steps is 120 s. */
    public const STALE_SECONDS = 300;

    /** A row that has been claimed this many times is never claimed again until reset. */
    public const MAX_ATTEMPTS = 5;

    /** An unstarted row enqueued longer ago than this is never claimed, so a sim enqueued while the publisher was off does not post later. */
    public const FRESH_SECONDS = 21600;

    /**
     * Mark $sim ready to publish. A repeat call never resets progress.
     */
    public function enqueue(int $sim): void
    {
        $this->execute(
            "INSERT INTO `ibl_sim_publish_progress` (`sim`, `enqueued_at`) VALUES (?, NOW())
             ON DUPLICATE KEY UPDATE `enqueued_at` = COALESCE(`enqueued_at`, VALUES(`enqueued_at`))",
            'i',
            $sim
        );
    }

    /**
     * Store a manual thread title. Touches only title_override, so setting one
     * before the sim is done never makes the row claimable.
     */
    public function setTitleOverride(int $sim, ?string $title): void
    {
        $this->execute(
            "INSERT INTO `ibl_sim_publish_progress` (`sim`, `title_override`) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE `title_override` = VALUES(`title_override`)",
            'is',
            $sim,
            $title
        );
    }

    /**
     * Claim the oldest publishable sim for $token, or null when none is claimable.
     * The UPDATE is the authority: it re-asserts every predicate in one statement.
     *
     * @return array{sim: int, title_override: ?string, thread_id: ?string, last_step: int}|null
     */
    public function claimNext(string $token): ?array
    {
        $claimed = $this->execute(
            "UPDATE `ibl_sim_publish_progress`
             SET `attempts` = `attempts` + 1, `claim_token` = ?, `heartbeat_at` = NOW()
             WHERE `enqueued_at` IS NOT NULL
               AND `finished_at` IS NULL
               AND `attempts` < ?
               AND (`claim_token` IS NULL OR `claim_token` = ? OR `heartbeat_at` < NOW() - INTERVAL ? SECOND)
               AND (`last_step` >= ? OR `enqueued_at` >= NOW() - INTERVAL ? SECOND)
             ORDER BY `sim` ASC
             LIMIT 1",
            'sisiii',
            $token,
            self::MAX_ATTEMPTS,
            $token,
            self::STALE_SECONDS,
            0,
            self::FRESH_SECONDS
        );
        if ($claimed !== 1) {
            return null;
        }

        $row = $this->fetchOne(
            "SELECT `sim`, `title_override`, `thread_id`, `last_step`
             FROM `ibl_sim_publish_progress`
             WHERE `claim_token` = ? AND `finished_at` IS NULL
             ORDER BY `sim` ASC
             LIMIT 1",
            's',
            $token
        );
        if ($row === null) {
            return null;
        }

        $titleOverride = $row['title_override'] ?? null;
        $threadId = $row['thread_id'] ?? null;

        return [
            'sim'            => self::scalarToInt($row['sim'] ?? null),
            'title_override' => is_string($titleOverride) ? $titleOverride : null,
            'thread_id'      => is_string($threadId) ? $threadId : null,
            'last_step'      => self::scalarToInt($row['last_step'] ?? null),
        ];
    }

    /**
     * Record that step $step posted. Succeeds only for the claim holder when
     * $step directly follows the saved last_step; anything else affects 0 rows.
     */
    public function advance(int $sim, string $token, int $step, ?string $threadId): bool
    {
        return $this->execute(
            "UPDATE `ibl_sim_publish_progress`
             SET `last_step` = ?, `thread_id` = COALESCE(?, `thread_id`), `heartbeat_at` = NOW()
             WHERE `sim` = ? AND `claim_token` = ? AND `finished_at` IS NULL AND `last_step` = ?",
            'isisi',
            $step,
            $threadId,
            $sim,
            $token,
            $step - 1
        ) === 1;
    }

    /**
     * Mark the whole drip posted. Only the claim holder can finish a row.
     */
    public function finish(int $sim, string $token): bool
    {
        return $this->execute(
            "UPDATE `ibl_sim_publish_progress`
             SET `finished_at` = NOW()
             WHERE `sim` = ? AND `claim_token` = ? AND `finished_at` IS NULL",
            'is',
            $sim,
            $token
        ) === 1;
    }

    /**
     * Make an exhausted or stuck unfinished row claimable again, as freshly enqueued.
     * Returns false when the row does not exist or is already finished.
     */
    public function resetForRetry(int $sim): bool
    {
        return $this->execute(
            "UPDATE `ibl_sim_publish_progress`
             SET `attempts` = ?, `claim_token` = NULL, `enqueued_at` = NOW()
             WHERE `sim` = ? AND `finished_at` IS NULL",
            'ii',
            0,
            $sim
        ) === 1;
    }

    /**
     * @return array{sim: int, title_override: ?string, enqueued_at: ?string, claim_token: ?string, heartbeat_at: ?string, attempts: int, thread_id: ?string, last_step: int, finished_at: ?string}|null
     */
    public function findForSim(int $sim): ?array
    {
        $row = $this->fetchOne(
            "SELECT `sim`, `title_override`, `enqueued_at`, `claim_token`, `heartbeat_at`,
                    `attempts`, `thread_id`, `last_step`, `finished_at`
             FROM `ibl_sim_publish_progress`
             WHERE `sim` = ?",
            'i',
            $sim
        );
        if ($row === null) {
            return null;
        }

        return [
            'sim'            => self::scalarToInt($row['sim'] ?? null),
            'title_override' => self::nullableString($row['title_override'] ?? null),
            'enqueued_at'    => self::nullableString($row['enqueued_at'] ?? null),
            'claim_token'    => self::nullableString($row['claim_token'] ?? null),
            'heartbeat_at'   => self::nullableString($row['heartbeat_at'] ?? null),
            'attempts'       => self::scalarToInt($row['attempts'] ?? null),
            'thread_id'      => self::nullableString($row['thread_id'] ?? null),
            'last_step'      => self::scalarToInt($row['last_step'] ?? null),
            'finished_at'    => self::nullableString($row['finished_at'] ?? null),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
