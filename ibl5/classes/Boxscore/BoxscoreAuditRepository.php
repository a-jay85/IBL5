<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\BoxscoreAuditRepositoryInterface;
use League\LeagueContext;

/**
 * BoxscoreAuditRepository - Data-integrity checks over box scores and the schedule
 *
 * Read-only finders for orphan box scores, played-but-unscored schedule rows and
 * duplicate game triples, plus the best-effort schedule_guard_rejects audit write.
 *
 * @see BoxscoreAuditRepositoryInterface For the interface contract
 * @see \Database\BaseMysqliRepository For base class documentation
 */
class BoxscoreAuditRepository extends \Database\BaseMysqliRepository implements BoxscoreAuditRepositoryInterface
{
    /** Hard bound on rows written per run — see plan §12.4. */
    public const MAX_RECORDED_REJECTS = 2000;

    /**
     * @param \mysqli $db Active mysqli connection
     */
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @see BoxscoreAuditRepositoryInterface::findOrphanBoxscoreGames()
     */
    public function findOrphanBoxscoreGames(int $seasonYear): array
    {
        $months  = ScheduleMembershipGuard::OFF_SCHEDULE_MONTHS;
        $teamIds = ScheduleMembershipGuard::EXEMPT_TEAMIDS;

        // Both runs below are count-derived strings of bound `?` markers built from
        // in-class constants — no user input reaches the SQL text. Concatenate the
        // validated fragments rather than interpolating, and bind every exemption
        // value so the constants stay the single source of truth.
        $monthPlaceholders = implode(', ', array_fill(0, count($months), '?'));
        $teamPlaceholders  = implode(', ', array_fill(0, count($teamIds), '?'));

        // One finding per GAME, not per row. ibl_box_scores_teams stores two rows per
        // game (one per side), so grouping by b.name would emit every orphan twice and
        // an operator would read 1236 phantom games where 618 exist. The uniqueness key
        // is (game_date, visitor_teamid, home_teamid, game_of_that_day); the two team
        // names are aggregated into the single `name` field the finding detail renders.
        // @phpstan-ignore ibl.orderByMissingTiebreaker (GROUP_CONCAT's ORDER BY is misidentified as the outer sort; outer ORDER BY is game_date+game_of_that_day, which is unique within the GROUP BY set)
        $sql = "SELECT b.game_date, b.visitor_teamid, b.home_teamid, b.game_of_that_day,
       GROUP_CONCAT(b.name ORDER BY b.name SEPARATOR ', ') AS name
FROM `ibl_box_scores_teams` b
WHERE b.season_year = ?
  AND MONTH(b.game_date) NOT IN (" . $monthPlaceholders . ")
  AND b.visitor_teamid NOT IN (" . $teamPlaceholders . ")
  AND b.home_teamid NOT IN (" . $teamPlaceholders . ")
  AND NOT EXISTS (
      SELECT 1 FROM `ibl_schedule` s
      WHERE s.season_year = b.season_year
        AND s.game_date = b.game_date
        AND s.visitor_teamid = b.visitor_teamid
        AND s.home_teamid = b.home_teamid
  )
GROUP BY b.game_date, b.visitor_teamid, b.home_teamid, b.game_of_that_day
ORDER BY b.game_date, b.game_of_that_day";

        $types  = 'i' . str_repeat('i', count($months)) . str_repeat('i', count($teamIds) * 2);
        $params = array_merge([$seasonYear], $months, $teamIds, $teamIds);

        $rows = $this->fetchAll($sql, $types, ...$params);

        return array_map(
            static fn (array $row): array => [
                'game_date'        => self::scalarToString($row['game_date'] ?? ''),
                'visitor_teamid'   => self::scalarToInt($row['visitor_teamid'] ?? 0),
                'home_teamid'      => self::scalarToInt($row['home_teamid'] ?? 0),
                'game_of_that_day' => self::scalarToInt($row['game_of_that_day'] ?? 0),
                'name'             => self::scalarToString($row['name'] ?? ''),
            ],
            array_values($rows),
        );
    }

    /**
     * @see BoxscoreAuditRepositoryInterface::findScheduledGamesWithoutBoxscores()
     */
    public function findScheduledGamesWithoutBoxscores(int $seasonYear): array
    {
        $sql = "SELECT s.game_date, s.visitor_teamid, s.home_teamid, s.visitor_score, s.home_score
FROM `ibl_schedule` s
WHERE s.season_year = ?
  AND NOT (s.visitor_score = 0 AND s.home_score = 0)
  AND NOT EXISTS (
      SELECT 1 FROM `ibl_box_scores_teams` b
      WHERE b.game_date = s.game_date
        AND b.visitor_teamid = s.visitor_teamid
        AND b.home_teamid = s.home_teamid
  )
ORDER BY s.game_date, s.id";

        $rows = $this->fetchAll($sql, 'i', $seasonYear);

        return array_map(
            static fn (array $row): array => [
                'game_date'      => self::scalarToString($row['game_date'] ?? ''),
                'visitor_teamid' => self::scalarToInt($row['visitor_teamid'] ?? 0),
                'home_teamid'    => self::scalarToInt($row['home_teamid'] ?? 0),
                'visitor_score'  => self::scalarToInt($row['visitor_score'] ?? 0),
                'home_score'     => self::scalarToInt($row['home_score'] ?? 0),
            ],
            array_values($rows),
        );
    }

    /**
     * @see BoxscoreAuditRepositoryInterface::findDuplicateTripleGames()
     */
    public function findDuplicateTripleGames(?int $seasonYear = null, ?int $gameType = null): array
    {
        // Both filters are optional and compose independently: null means "do not
        // scope on this axis". With both null the query is an all-seasons,
        // all-game-type scan, and the WHERE clause is omitted entirely.
        $predicates = [];
        $types = '';
        $values = [];

        if ($seasonYear !== null) {
            $predicates[] = 'b.season_year = ?';
            $types .= 'i';
            $values[] = $seasonYear;
        }
        if ($gameType !== null) {
            $predicates[] = 'b.game_type = ?';
            $types .= 'i';
            $values[] = $gameType;
        }

        $whereClause = $predicates === [] ? '' : 'WHERE ' . implode(' AND ', $predicates);

        // IDENTIFIER (already-validated): $whereClause is built only from the two
        // hardcoded fragments above; every value is bound.
        $sql = sprintf(
            "SELECT b.game_date, b.visitor_teamid, b.home_teamid,
       COUNT(DISTINCT b.game_of_that_day) AS occurrences,
       GROUP_CONCAT(DISTINCT b.game_of_that_day ORDER BY b.game_of_that_day) AS gotds
FROM `ibl_box_scores_teams` b
%s
GROUP BY b.game_date, b.visitor_teamid, b.home_teamid
HAVING occurrences > 1
ORDER BY b.game_date",
            $whereClause,
        );

        // fetchAll() forwards to bind_param() only when the type string is
        // non-empty; calling bind_param('') is a fatal, so the unscoped call
        // must pass no types and no values.
        $rows = $this->fetchAll($sql, $types, ...$values);

        return array_map(
            static fn (array $row): array => [
                'game_date'      => self::scalarToString($row['game_date'] ?? ''),
                'visitor_teamid' => self::scalarToInt($row['visitor_teamid'] ?? 0),
                'home_teamid'    => self::scalarToInt($row['home_teamid'] ?? 0),
                'occurrences'    => self::scalarToInt($row['occurrences'] ?? 0),
                'gotds'          => self::scalarToString($row['gotds'] ?? ''),
            ],
            array_values($rows),
        );
    }

    /**
     * Best-effort audit write. Never throws; returns the number of rows recorded.
     *
     * Records up to MAX_RECORDED_REJECTS rows in a single transaction. On any DB
     * failure the exception is caught, a warning is logged to the 'audit' channel,
     * and 0 is returned — the import pipeline must never be aborted by an audit write.
     *
     * @param list<RejectedGame> $rejects
     */
    public function recordRejectedGames(int $seasonYear, array $rejects, ?string $sourceArchive): int
    {
        if ($rejects === []) {
            return 0;
        }

        $toRecord = $rejects;
        if (count($toRecord) > self::MAX_RECORDED_REJECTS) {
            $dropped  = count($toRecord) - self::MAX_RECORDED_REJECTS;
            $toRecord = array_slice($toRecord, 0, self::MAX_RECORDED_REJECTS);
            \Logging\LoggerFactory::getChannel('audit')->warning(
                'schedule_guard_rejects: truncating to MAX_RECORDED_REJECTS',
                ['max' => self::MAX_RECORDED_REJECTS, 'dropped' => $dropped, 'season_year' => $seasonYear],
            );
        }

        try {
            $this->transactional(function () use ($seasonYear, $toRecord, $sourceArchive): void {
                $archive = $sourceArchive ?? '';
                foreach ($toRecord as $reject) {
                    $this->execute(
                        'INSERT INTO `schedule_guard_rejects`'
                        . ' (season_year, game_date, visitor_teamid, home_teamid, game_of_that_day, reason, stored_game_of_that_day, source_archive)'
                        . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                        'isiiisss',
                        $seasonYear,
                        $reject->gameDate,
                        $reject->visitorTeamid,
                        $reject->homeTeamid,
                        $reject->gameOfThatDay,
                        $reject->reason,
                        implode(',', $reject->storedGameOfThatDay),
                        $archive,
                    );
                }
            });
            return count($toRecord);
        } catch (\Throwable $e) {
            \Logging\LoggerFactory::getChannel('audit')->warning(
                'schedule_guard_rejects write failed — rejection audit skipped',
                ['error' => $e->getMessage(), 'season_year' => $seasonYear, 'rejects' => count($rejects)],
            );
            return 0;
        }
    }
}
