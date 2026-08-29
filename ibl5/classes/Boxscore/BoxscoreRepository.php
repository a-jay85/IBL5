<?php

declare(strict_types=1);

namespace Boxscore;

use Boxscore\Contracts\BoxscoreRepositoryInterface;
use League\League;
use League\LeagueContext;

use Season\Season;
/**
 * BoxscoreRepository - Data access layer for boxscore management
 *
 * Handles deletion of boxscore records for different season phases.
 * Operates on both ibl_box_scores (player stats) and ibl_box_scores_teams tables.
 *
 * @see BoxscoreRepositoryInterface For the interface contract
 * @see \BaseMysqliRepository For base class documentation
 */
class BoxscoreRepository extends \BaseMysqliRepository implements BoxscoreRepositoryInterface
{
    /** Hard bound on rows written per run — see plan §12.4. */
    public const MAX_RECORDED_REJECTS = 2000;

    /**
     * Constructor
     *
     * @param \mysqli $db Active mysqli connection
     */
    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @see BoxscoreRepositoryInterface::deletePreseasonBoxScores()
     */
    public function deletePreseasonBoxScores(int $seasonBeginningYear): bool
    {
        $startDate = "{$seasonBeginningYear}-09-01";
        $endDate = "{$seasonBeginningYear}-09-30";

        return $this->deleteBoxScoresForDateRange($startDate, $endDate);
    }

    /**
     * @see BoxscoreRepositoryInterface::deleteHeatBoxScores()
     */
    public function deleteHeatBoxScores(int $seasonStartingYear): bool
    {
        $heatMonth = str_pad((string) Season::IBL_HEAT_MONTH, 2, '0', STR_PAD_LEFT);
        $startDate = "{$seasonStartingYear}-{$heatMonth}-01";
        $endDate = "{$seasonStartingYear}-{$heatMonth}-31";

        return $this->deleteBoxScoresForDateRange($startDate, $endDate);
    }

    /**
     * @see BoxscoreRepositoryInterface::deleteRegularSeasonAndPlayoffsBoxScores()
     */
    public function deleteRegularSeasonAndPlayoffsBoxScores(int $seasonStartingYear): bool
    {
        $seasonEndingYear = $seasonStartingYear + 1;
        $regularSeasonMonth = str_pad((string) Season::IBL_REGULAR_SEASON_STARTING_MONTH, 2, '0', STR_PAD_LEFT);
        $playoffMonth = str_pad((string) Season::IBL_PLAYOFF_MONTH, 2, '0', STR_PAD_LEFT);

        $startDate = "{$seasonStartingYear}-{$regularSeasonMonth}-01";
        $endDate = "{$seasonEndingYear}-{$playoffMonth}-30";

        return $this->deleteBoxScoresForDateRange($startDate, $endDate);
    }

    /**
     * Delete boxscores for both players and teams within a date range
     *
     * @param string $startDate Start date in Y-m-d format
     * @param string $endDate End date in Y-m-d format
     * @return true Always returns true since execute() returns int on success
     */
    private function deleteBoxScoresForDateRange(string $startDate, string $endDate): true
    {
        $this->transactional(function () use ($startDate, $endDate): void {
            $this->execute(
                "DELETE FROM `ibl_box_scores` WHERE game_date BETWEEN ? AND ?",
                "ss",
                $startDate,
                $endDate
            );

            $this->execute(
                "DELETE FROM `ibl_box_scores_teams` WHERE game_date BETWEEN ? AND ?",
                "ss",
                $startDate,
                $endDate
            );
        });

        return true;
    }

    /**
     * @see BoxscoreRepositoryInterface::findTeamBoxscore()
     */
    public function findTeamBoxscore(string $date, int $visitor_teamid, int $home_teamid, int $game_of_that_day): ?array
    {
        return $this->fetchOne(
            "SELECT visitor_q1_points, visitor_q2_points, visitor_q3_points, visitor_q4_points, visitor_ot_points,
                    home_q1_points, home_q2_points, home_q3_points, home_q4_points, home_ot_points
             FROM `ibl_box_scores_teams`
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND game_of_that_day = ?
             LIMIT 1",
            "siii",
            $date,
            $visitor_teamid,
            $home_teamid,
            $game_of_that_day
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::deleteTeamBoxscoresByGame()
     */
    public function deleteTeamBoxscoresByGame(string $date, int $visitor_teamid, int $home_teamid, int $game_of_that_day): int
    {
        return $this->execute(
            "DELETE FROM `ibl_box_scores_teams`
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND game_of_that_day = ?",
            "siii",
            $date,
            $visitor_teamid,
            $home_teamid,
            $game_of_that_day
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::fetchScheduledGameIndex()
     */
    public function fetchScheduledGameIndex(int $seasonYear): array
    {
        $rows = $this->fetchAll(
            "SELECT DISTINCT game_date, visitor_teamid, home_teamid FROM `ibl_schedule` WHERE season_year = ?",
            "i",
            $seasonYear
        );

        $index = [];
        foreach ($rows as $row) {
            $index[self::scalarToString($row['game_date'] ?? null)]
                  [self::scalarToInt($row['visitor_teamid'] ?? null)]
                  [self::scalarToInt($row['home_teamid'] ?? null)] = true;
        }
        return $index;
    }

    /**
     * @see BoxscoreRepositoryInterface::fetchBoxscoreGameOfThatDayIndex()
     */
    public function fetchBoxscoreGameOfThatDayIndex(int $seasonYear): array
    {
        $rows = $this->fetchAll(
            "SELECT game_date, visitor_teamid, home_teamid, game_of_that_day FROM `ibl_box_scores_teams` WHERE season_year = ? AND visitor_teamid IS NOT NULL AND home_teamid IS NOT NULL",
            "i",
            $seasonYear
        );

        $index = [];
        foreach ($rows as $row) {
            $index[self::scalarToString($row['game_date'] ?? null)]
                  [self::scalarToInt($row['visitor_teamid'] ?? null)]
                  [self::scalarToInt($row['home_teamid'] ?? null)][] = self::scalarToInt($row['game_of_that_day'] ?? null);
        }
        return $index;
    }

    /**
     * @see BoxscoreRepositoryInterface::deletePlayerBoxscoresByGame()
     */
    public function deletePlayerBoxscoresByGame(string $date, int $visitor_teamid, int $home_teamid, int $game_of_that_day): int
    {
        return $this->execute(
            "DELETE FROM `ibl_box_scores`
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND game_of_that_day = ?",
            "siii",
            $date,
            $visitor_teamid,
            $home_teamid,
            $game_of_that_day
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::hasNullTeamIdPlayerBoxscores()
     */
    public function hasNullTeamIdPlayerBoxscores(string $date, int $visitor_teamid, int $home_teamid, int $game_of_that_day): bool
    {
        /** @var array{cnt: int}|null $row */
        $row = $this->fetchOne(
            "SELECT COUNT(*) AS cnt FROM `ibl_box_scores`
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND game_of_that_day = ? AND pid <> 0 AND teamid IS NULL
             LIMIT 1",
            "siii",
            $date,
            $visitor_teamid,
            $home_teamid,
            $game_of_that_day
        );

        return $row !== null && $row['cnt'] > 0;
    }

    /**
     * @see BoxscoreRepositoryInterface::findAllStarTeamNames()
     */
    public function findAllStarTeamNames(string $date): ?array
    {
        /** @var list<array{name: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT name FROM `ibl_box_scores_teams`
             WHERE game_date = ? AND visitor_teamid = " . League::ALL_STAR_AWAY_TEAMID . " AND home_teamid = " . League::ALL_STAR_HOME_TEAMID . "
             ORDER BY id ASC
             LIMIT 2",
            "s",
            $date
        );

        if (count($rows) < 2) {
            return null;
        }

        return [
            'awayName' => $rows[0]['name'],
            'homeName' => $rows[1]['name'],
        ];
    }

    /**
     * @see BoxscoreRepositoryInterface::findAllStarGamesWithDefaultNames()
     */
    public function findAllStarGamesWithDefaultNames(): array
    {
        /** @var list<array{id: int, game_date: string, name: string, visitor_teamid: int, home_teamid: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT id, game_date, name, visitor_teamid, home_teamid
             FROM `ibl_box_scores_teams`
             WHERE name IN ('Team Away', 'Team Home')
               AND visitor_teamid = " . League::ALL_STAR_AWAY_TEAMID . " AND home_teamid = " . League::ALL_STAR_HOME_TEAMID . "
             ORDER BY game_date ASC, id ASC",
            ""
        );

        return $rows;
    }

    /**
     * @see BoxscoreRepositoryInterface::getPlayersForAllStarTeam()
     */
    public function getPlayersForAllStarTeam(string $date, int $teamid): array
    {
        /** @var list<array{name: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT COALESCE(p.name, bs.name) AS name
             FROM `ibl_box_scores` bs
             LEFT JOIN `ibl_plr` p ON bs.pid = p.pid
             WHERE bs.game_date = ? AND bs.visitor_teamid = " . League::ALL_STAR_AWAY_TEAMID . " AND bs.home_teamid = " . League::ALL_STAR_HOME_TEAMID . " AND bs.teamid = ?
             ORDER BY bs.id ASC",
            "si",
            $date,
            $teamid
        );

        $names = [];
        foreach ($rows as $row) {
            $names[] = $row['name'];
        }

        return $names;
    }

    /**
     * @see BoxscoreRepositoryInterface::renameAllStarTeam()
     */
    public function renameAllStarTeam(int $recordId, string $newName): int
    {
        return $this->execute(
            "UPDATE `ibl_box_scores_teams` SET name = ? WHERE id = ?",
            "si",
            $newName,
            $recordId
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::insertTeamBoxscore()
     *
     * @param array{
     *     game_date: string,
     *     name: string,
     *     game_of_that_day: int,
     *     visitor_teamid: int,
     *     home_teamid: int,
     *     attendance: int,
     *     capacity: int,
     *     visitor_wins: int,
     *     visitor_losses: int,
     *     home_wins: int,
     *     home_losses: int,
     *     visitor_q1_points: int,
     *     visitor_q2_points: int,
     *     visitor_q3_points: int,
     *     visitor_q4_points: int,
     *     visitor_ot_points: int,
     *     home_q1_points: int,
     *     home_q2_points: int,
     *     home_q3_points: int,
     *     home_q4_points: int,
     *     home_ot_points: int,
     *     game_2gm: int,
     *     game_2ga: int,
     *     game_ftm: int,
     *     game_fta: int,
     *     game_3gm: int,
     *     game_3ga: int,
     *     game_orb: int,
     *     game_drb: int,
     *     game_ast: int,
     *     game_stl: int,
     *     game_tov: int,
     *     game_blk: int,
     *     game_pf: int
     * } $row
     */
    public function insertTeamBoxscore(array $row): int
    {
        return $this->execute(
            Boxscore::teamInsertSql('`ibl_box_scores_teams`'),
            "ssiiiiiiiiiiiiiiiiiiiiiiiiiiiiiiii",
            $row['game_date'],
            $row['name'],
            $row['game_of_that_day'],
            $row['visitor_teamid'],
            $row['home_teamid'],
            $row['attendance'],
            $row['capacity'],
            $row['visitor_wins'],
            $row['visitor_losses'],
            $row['home_wins'],
            $row['home_losses'],
            $row['visitor_q1_points'],
            $row['visitor_q2_points'],
            $row['visitor_q3_points'],
            $row['visitor_q4_points'],
            $row['visitor_ot_points'],
            $row['home_q1_points'],
            $row['home_q2_points'],
            $row['home_q3_points'],
            $row['home_q4_points'],
            $row['home_ot_points'],
            $row['game_2gm'],
            $row['game_2ga'],
            $row['game_ftm'],
            $row['game_fta'],
            $row['game_3gm'],
            $row['game_3ga'],
            $row['game_orb'],
            $row['game_drb'],
            $row['game_ast'],
            $row['game_stl'],
            $row['game_tov'],
            $row['game_blk'],
            $row['game_pf'],
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::insertPlayerBoxscore()
     */
    public function insertPlayerBoxscore(
        string $date,
        string $uuid,
        string $name,
        string $position,
        int $playerID,
        int $visitor_teamid,
        int $home_teamid,
        int $game_of_that_day,
        int $attendance,
        int $capacity,
        int $visitor_wins,
        int $visitor_losses,
        int $home_wins,
        int $home_losses,
        int $teamid,
        int $minutesPlayed,
        int $fieldGoalsMade,
        int $fieldGoalsAttempted,
        int $freeThrowsMade,
        int $freeThrowsAttempted,
        int $threePointersMade,
        int $threePointersAttempted,
        int $offensiveRebounds,
        int $defensiveRebounds,
        int $assists,
        int $steals,
        int $turnovers,
        int $blocks,
        int $personalFouls,
    ): int {
        return $this->execute(
            Boxscore::playerInsertSql('`ibl_box_scores`'),
            "ssssiiiiiiiiiiiiiiiiiiiiiiiii",
            $date,
            $uuid,
            $name,
            $position,
            $playerID,
            $visitor_teamid,
            $home_teamid,
            $game_of_that_day,
            $attendance,
            $capacity,
            $visitor_wins,
            $visitor_losses,
            $home_wins,
            $home_losses,
            $teamid,
            $minutesPlayed,
            $fieldGoalsMade,
            $fieldGoalsAttempted,
            $freeThrowsMade,
            $freeThrowsAttempted,
            $threePointersMade,
            $threePointersAttempted,
            $offensiveRebounds,
            $defensiveRebounds,
            $assists,
            $steals,
            $turnovers,
            $blocks,
            $personalFouls,
        );
    }

    /**
     * @see BoxscoreRepositoryInterface::findOrphanBoxscoreGames()
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
     * @see BoxscoreRepositoryInterface::findScheduledGamesWithoutBoxscores()
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
     * @see BoxscoreRepositoryInterface::findDuplicateTripleGames()
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

        $where = $predicates === [] ? '' : 'WHERE ' . implode(' AND ', $predicates) . "\n";

        $sql = "SELECT b.game_date, b.visitor_teamid, b.home_teamid,
       COUNT(DISTINCT b.game_of_that_day) AS occurrences,
       GROUP_CONCAT(DISTINCT b.game_of_that_day ORDER BY b.game_of_that_day) AS gotds
FROM `ibl_box_scores_teams` b
{$where}GROUP BY b.game_date, b.visitor_teamid, b.home_teamid
HAVING occurrences > 1
ORDER BY b.game_date";

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
     * Narrow a mixed fetchAll() column to int for use as an index key.
     *
     * The columns fed here are INT NOT NULL in the schema, so the fallback is
     * defensive only; a non-numeric value yields 0, which simply never matches
     * a real team id rather than producing a malformed key.
     */
    private static function scalarToInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        return 0;
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

    /**
     * Narrow a mixed fetchAll() column to string for use as an index key.
     *
     * `game_date` is a DATE column, so the fallback is defensive only.
     */
    private static function scalarToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return '';
    }
}
