<?php

declare(strict_types=1);

namespace OnThisDay;

use League\LeagueContext;
use OnThisDay\Contracts\OnThisDayRepositoryInterface;

/**
 * OnThisDayRepository - reads precomputed moments from ibl_on_this_day_moments.
 *
 * The nightly OnThisDayMomentBuilder writes the table; this read path runs one
 * indexed query per calendar radius and never touches box-score history. An
 * empty or missing table yields no moments, with no fallback to a live scan.
 *
 * @see \Database\BaseMysqliRepository For base class documentation
 */
class OnThisDayRepository extends \Database\BaseMysqliRepository implements OnThisDayRepositoryInterface, Contracts\SimPostClaimInterface, Contracts\ShownMomentHistoryInterface
{
    /** ibl_settings key holding the last sim the Discord step posted for. */
    public const LAST_POSTED_SIM_KEY = 'on_this_day_last_posted_sim';

    /**
     * Widest month/day window: a MAX_SPAN_DAYS sim span plus radius 7 on each side.
     */
    public const MONTH_DAY_LIST_WIDTH = OnThisDayCalendar::MAX_SPAN_DAYS + 14;

    /** MariaDB ER_NO_SUCH_TABLE: the migration has not been applied yet. */
    private const ER_NO_SUCH_TABLE = 1146;

    // @phpstan-ignore ibl.orderByMissingTiebreaker (moment_key is UNIQUE uq_otd_moment_key; not on the rule allowlist)
    private const MOMENTS_QUERY = "SELECT moment_key, kind, game_date, season_year, pid, player_name,
            teamid, team_name, opponent_teamid, opponent_name, facts_json, stat_label, game_of_that_day
          FROM `ibl_on_this_day_moments`
          WHERE season_year < ? AND month_day IN ({IN})
          ORDER BY game_date, moment_key";

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * The table is IBL-only, so an Olympics context reads nothing.
     *
     * @see OnThisDayRepositoryInterface::findMoments()
     */
    public function findMoments(array $monthDays, int $beforeSeasonYear): array
    {
        if ($monthDays === []) {
            return [];
        }

        $count = count($monthDays);
        if ($count > self::MONTH_DAY_LIST_WIDTH) {
            throw new \InvalidArgumentException(
                'month/day window must hold 1-' . self::MONTH_DAY_LIST_WIDTH . ' entries, got ' . $count
            );
        }

        if ($this->leagueContext !== null && $this->leagueContext->isOlympics()) {
            return [];
        }

        try {
            $rows = $this->fetchAllInList(self::MOMENTS_QUERY, 's', array_values($monthDays), 'i', $beforeSeasonYear);
        } catch (\mysqli_sql_exception $e) {
            if ($e->getCode() === self::ER_NO_SUCH_TABLE) {
                return [];
            }
            throw $e;
        }

        $moments = [];
        foreach ($rows as $row) {
            $key = self::scalarToString($row['moment_key'] ?? null);
            try {
                $moments[] = $this->hydrate($row);
            } catch (\InvalidArgumentException | \JsonException $e) {
                error_log('OnThisDay: skipped malformed stored moment ' . $key . ': ' . $e->getMessage());
            }
        }

        return $moments;
    }

    /**
     * Single-statement upsert: only a strictly newer sim changes the stored
     * value, so a re-run or an older sim affects 0 rows.
     *
     * @see Contracts\SimPostClaimInterface::claimSimPost()
     */
    public function claimSimPost(int $sim): bool
    {
        $affected = $this->execute(
            "INSERT INTO `ibl_settings` (setting_key, setting_value, league)
             VALUES (?, ?, 'ibl')
             ON DUPLICATE KEY UPDATE setting_value = IF(CAST(setting_value AS UNSIGNED) < CAST(VALUES(setting_value) AS UNSIGNED),
                                                        VALUES(setting_value), setting_value)",
            'ss',
            self::LAST_POSTED_SIM_KEY,
            (string) $sim,
        );

        return $affected > 0;
    }

    /**
     * One fixed prepared INSERT per key (a selection holds at most 3). The
     * no-op duplicate update reports 0 affected rows, so a re-run returns 0.
     *
     * @see Contracts\ShownMomentHistoryInterface::recordShown()
     */
    public function recordShown(int $sim, int $seasonYear, array $momentKeys): int
    {
        $inserted = 0;
        foreach (array_unique($momentKeys) as $key) {
            $inserted += $this->execute(
                'INSERT INTO `ibl_on_this_day_shown` (moment_key, sim, season_year)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE id = id',
                'sii',
                $key,
                $sim,
                $seasonYear,
            );
        }

        return $inserted;
    }

    /**
     * @see Contracts\ShownMomentHistoryInterface::findShownBefore()
     */
    public function findShownBefore(int $sim): array
    {
        try {
            $rows = $this->fetchAll(
                'SELECT moment_key, MAX(sim) AS last_sim FROM `ibl_on_this_day_shown`
                 WHERE sim < ? GROUP BY moment_key',
                'i',
                $sim,
            );
        } catch (\mysqli_sql_exception $e) {
            if ($e->getCode() === self::ER_NO_SUCH_TABLE) {
                return [];
            }
            throw $e;
        }

        $shown = [];
        foreach ($rows as $row) {
            $key = $row['moment_key'] ?? null;
            $lastSim = $row['last_sim'] ?? null;
            if (is_string($key) && $key !== '' && is_numeric($lastSim)) {
                $shown[$key] = (int) $lastSim;
            }
        }

        return $shown;
    }

    /**
     * @param array<string, mixed> $row
     * @throws \InvalidArgumentException|\JsonException when the stored row is malformed
     */
    private function hydrate(array $row): Moment
    {
        $factsJson = $row['facts_json'] ?? null;
        if (!is_string($factsJson)) {
            throw new \InvalidArgumentException('Missing facts_json');
        }

        $statLabel = $row['stat_label'] ?? null;

        return Moment::fromArray([
            'kind' => $row['kind'] ?? null,
            'key' => $row['moment_key'] ?? null,
            'date' => $row['game_date'] ?? null,
            'seasonYear' => self::intOrNull($row['season_year'] ?? null),
            'pid' => self::intOrNull($row['pid'] ?? null),
            'playerName' => $row['player_name'] ?? null,
            'teamId' => self::intOrNull($row['teamid'] ?? null),
            'teamName' => $row['team_name'] ?? null,
            'opponentTeamId' => self::intOrNull($row['opponent_teamid'] ?? null),
            'opponentName' => $row['opponent_name'] ?? null,
            'facts' => json_decode($factsJson, true, 4, JSON_THROW_ON_ERROR),
            'statLabel' => $statLabel,
            'gameOfThatDay' => self::intOrNull($row['game_of_that_day'] ?? null) ?? 0,
        ]);
    }

    /**
     * Narrow an INT column to int; a non-numeric value stays null so
     * Moment::fromArray() rejects the row.
     */
    private static function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && is_numeric($value) ? (int) $value : null;
    }
}
