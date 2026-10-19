<?php

declare(strict_types=1);

namespace OnThisDay;

use League\LeagueContext;
use OnThisDay\Contracts\OnThisDayRepositoryInterface;

/**
 * OnThisDayRepository - league-history moments read straight from box scores.
 *
 * Every player-game source reads the phantom-guarded derived table
 * (GUARDED_PLAYER_GAMES / GUARDED_WINDOWED_PLAYER_GAMES): one row per (game_date, visitor, home, pid). A repeated
 * row set for the same game is a phantom, and grouping collapses it to one line.
 * Player names come from ibl_plr on pid; box-score `name` is never read.
 *
 * @see \Database\BaseMysqliRepository For base class documentation
 */
class OnThisDayRepository extends \Database\BaseMysqliRepository implements OnThisDayRepositoryInterface
{
    /**
     * Widest month/day window: radius 7 on each side of the center date.
     */
    public const MONTH_DAY_LIST_WIDTH = 15;

    /** 15 comma-separated placeholders; keep in step with MONTH_DAY_LIST_WIDTH. */
    private const MONTH_DAY_LIST = '?,?,?,?,?,?,?,?,?,?,?,?,?,?,?';

    /** 15 `s` bind types; keep in step with MONTH_DAY_LIST_WIDTH. */
    private const MONTH_DAY_LIST_TYPES = 'sssssssssssssss';

    /** Padding value; DATE_FORMAT(..., '%m-%d') never yields an empty string. */
    private const MONTH_DAY_PAD = '';

    /**
     * Record-eligible stats: label => guarded-table column. The column is the
     * only fragment interpolated into SQL, and it comes from this list alone.
     */
    private const RECORD_STATS = [
        'points' => 'pts',
        'rebounds' => 'reb',
        'assists' => 'ast',
        'steals' => 'stl',
        'blocks' => 'blk',
    ];

    /**
     * Phantom-guarded player games: one row per (game_date, visitor, home, pid).
     *
     * `(game_date, visitor, home)` is unique in real data, so a repeated row set
     * for the same pid is a phantom (it carries a different game_of_that_day);
     * grouping collapses it, and MAX keeps the real line because a repeat never
     * adds points. Every player-game source reads one of the two tables below.
     */
    private const GUARDED_SELECT = "SELECT b.game_date, b.visitor_teamid, b.home_teamid, b.pid,
                  MAX(b.teamid) AS teamid, MAX(b.calc_points) AS pts, MAX(b.calc_rebounds) AS reb,
                  MAX(b.game_ast) AS ast, MAX(b.game_stl) AS stl, MAX(b.game_blk) AS blk,
                  MAX(b.season_year) AS season_year, MAX(b.game_type) AS game_type
                FROM `ibl_box_scores` b
                WHERE b.pid > 0";

    private const GUARDED_GROUP_BY = " GROUP BY b.game_date, b.visitor_teamid, b.home_teamid, b.pid";

    /** Whole history, for chronology (records) and team changes (trades). */
    private const GUARDED_PLAYER_GAMES = self::GUARDED_SELECT . self::GUARDED_GROUP_BY;

    /** Binds: beforeSeasonYear, then the MONTH_DAY_LIST_WIDTH month/day values. */
    private const GUARDED_WINDOWED_PLAYER_GAMES = self::GUARDED_SELECT
        . " AND b.season_year < ? AND DATE_FORMAT(b.game_date, '%m-%d') IN (" . self::MONTH_DAY_LIST . ")"
        . self::GUARDED_GROUP_BY;

    /** Minimum old-team scoring average for a mover to count as a trade. */
    private const TRADE_MIN_PPG = 15;

    /** Minimum old-team games before the move. */
    private const TRADE_MIN_GAMES = 5;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
    }

    /**
     * @see OnThisDayRepositoryInterface::findFiftyPointGames()
     */
    public function findFiftyPointGames(array $monthDays, int $beforeSeasonYear): array
    {
        if ($monthDays === []) {
            return [];
        }

        $rows = $this->fetchWindowedPlayerGames(MomentKind::FiftyPointGame, $monthDays, $beforeSeasonYear);

        $moments = [];
        foreach ($rows as $row) {
            $moments[] = $this->playerGameMoment(MomentKind::FiftyPointGame, $row);
        }

        return $moments;
    }

    /**
     * @see OnThisDayRepositoryInterface::findTripleDoubles()
     */
    public function findTripleDoubles(array $monthDays, int $beforeSeasonYear): array
    {
        if ($monthDays === []) {
            return [];
        }

        $rows = $this->fetchWindowedPlayerGames(MomentKind::TripleDouble, $monthDays, $beforeSeasonYear);

        $moments = [];
        foreach ($rows as $row) {
            $moments[] = $this->playerGameMoment(MomentKind::TripleDouble, $row);
        }

        return $moments;
    }

    /**
     * A record game strictly exceeded every earlier regular-season single-game
     * value in league history at the time it was played. Chronology needs every
     * earlier game, so the window and season filters apply only after the
     * running maximum is computed.
     *
     * @see OnThisDayRepositoryInterface::findRecordGames()
     */
    public function findRecordGames(array $monthDays, int $beforeSeasonYear): array
    {
        if ($monthDays === []) {
            return [];
        }

        [$mdTypes, $mdParams] = $this->monthDayPredicate($monthDays);

        $moments = [];
        foreach (self::RECORD_STATS as $label => $column) {
            $this->assertRecordColumn($column);

            $query = "SELECT r.*, p.name AS player_name,
                        COALESCE(CONCAT(t.team_city, ' ', t.team_name), '') AS team_name,
                        COALESCE(CONCAT(o.team_city, ' ', o.team_name), '') AS opponent_name,
                        r.opponent_teamid
                      FROM (
                        SELECT g.*,
                          CASE WHEN g.teamid = g.visitor_teamid THEN g.home_teamid ELSE g.visitor_teamid END AS opponent_teamid,
                          MAX(g." . $column . ") OVER (
                            ORDER BY g.game_date, g.visitor_teamid, g.home_teamid, g.pid
                            ROWS BETWEEN UNBOUNDED PRECEDING AND 1 PRECEDING
                          ) AS prior_max
                        FROM (" . self::GUARDED_PLAYER_GAMES . ") g
                        WHERE g.game_type = 1
                      ) r
                      JOIN `ibl_plr` p ON p.pid = r.pid
                      LEFT JOIN `ibl_team_info` t ON t.teamid = r.teamid
                      LEFT JOIN `ibl_team_info` o ON o.teamid = r.opponent_teamid
                      WHERE r." . $column . " > COALESCE(r.prior_max, 0)
                        AND r.season_year < ?
                        AND DATE_FORMAT(r.game_date, '%m-%d') IN (" . self::MONTH_DAY_LIST . ")";

            $rows = $this->fetchAll($query, 'i' . $mdTypes, $beforeSeasonYear, ...$mdParams);

            foreach ($rows as $row) {
                $date = $this->str($row, 'game_date');
                $pid = $this->int($row, 'pid');
                $moments[] = new Moment(
                    kind: MomentKind::RecordGame,
                    key: MomentKind::RecordGame->value . ':' . $date . ':' . $pid . ':' . $label,
                    date: $date,
                    seasonYear: $this->int($row, 'season_year'),
                    pid: $pid,
                    playerName: $this->str($row, 'player_name'),
                    teamId: $this->int($row, 'teamid'),
                    teamName: $this->str($row, 'team_name'),
                    opponentTeamId: $this->int($row, 'opponent_teamid'),
                    opponentName: $this->str($row, 'opponent_name'),
                    facts: ['value' => $this->int($row, $column)],
                    statLabel: $label,
                );
            }
        }

        return $moments;
    }

    /**
     * Box scores date the clinch (last playoff game between the pair that
     * season); the derived series table supplies only the round and the score.
     *
     * @see OnThisDayRepositoryInterface::findSeriesClinchers()
     */
    public function findSeriesClinchers(array $monthDays, int $beforeSeasonYear): array
    {
        if ($monthDays === []) {
            return [];
        }

        [$mdTypes, $mdParams] = $this->monthDayPredicate($monthDays);

        $query = "SELECT s.year, s.round, s.winner_tid, s.loser_tid, s.winner_games, s.loser_games, c.clinch_date,
                    COALESCE(CONCAT(w.team_city, ' ', w.team_name), '') AS winner_name,
                    COALESCE(CONCAT(l.team_city, ' ', l.team_name), '') AS loser_name
                  FROM `ibl_playoff_series_results` s
                  JOIN (
                    SELECT season_year,
                      LEAST(visitor_teamid, home_teamid) AS t1,
                      GREATEST(visitor_teamid, home_teamid) AS t2,
                      MAX(game_date) AS clinch_date
                    FROM `ibl_box_scores`
                    WHERE game_type = 2
                    GROUP BY season_year, t1, t2
                  ) c ON c.season_year = s.year
                     AND c.t1 = LEAST(s.winner_tid, s.loser_tid)
                     AND c.t2 = GREATEST(s.winner_tid, s.loser_tid)
                  LEFT JOIN `ibl_team_info` w ON w.teamid = s.winner_tid
                  LEFT JOIN `ibl_team_info` l ON l.teamid = s.loser_tid
                  WHERE s.year < ?
                    AND DATE_FORMAT(c.clinch_date, '%m-%d') IN (" . self::MONTH_DAY_LIST . ")";

        $rows = $this->fetchAll($query, 'i' . $mdTypes, $beforeSeasonYear, ...$mdParams);

        $moments = [];
        foreach ($rows as $row) {
            $date = $this->str($row, 'clinch_date');
            $winner = $this->int($row, 'winner_tid');
            $loser = $this->int($row, 'loser_tid');
            $moments[] = new Moment(
                kind: MomentKind::SeriesClincher,
                key: MomentKind::SeriesClincher->value . ':' . $date . ':' . $winner . '-' . $loser,
                date: $date,
                seasonYear: $this->int($row, 'year'),
                pid: 0,
                playerName: '',
                teamId: $winner,
                teamName: $this->str($row, 'winner_name'),
                opponentTeamId: $loser,
                opponentName: $this->str($row, 'loser_name'),
                facts: [
                    'round' => $this->int($row, 'round'),
                    'wins' => $this->int($row, 'winner_games'),
                    'losses' => $this->int($row, 'loser_games'),
                ],
            );
        }

        return $moments;
    }

    /**
     * An in-season team change of a scorer, dated by the first game for the new
     * team. ibl_trade_info only carries real-world timestamps, so box scores
     * are the league-dated evidence. A waiver claim looks the same, which is
     * why the copy says "moved".
     *
     * @see OnThisDayRepositoryInterface::findTrades()
     */
    public function findTrades(array $monthDays, int $beforeSeasonYear): array
    {
        if ($monthDays === []) {
            return [];
        }

        [$mdTypes, $mdParams] = $this->monthDayPredicate($monthDays);

        // The only ORDER BY is the LAG window, partitioned by pid: the guarded table holds one row per pid per game_date.
        // @phpstan-ignore ibl.orderByMissingTiebreaker
        $query = "WITH games AS (
                    SELECT g.pid, g.season_year, g.game_date, g.teamid, g.pts,
                      LAG(g.teamid) OVER (PARTITION BY g.pid, g.season_year ORDER BY g.game_date) AS prev_teamid
                    FROM (" . self::GUARDED_PLAYER_GAMES . ") g
                    WHERE g.game_type = 1
                  ),
                  moves AS (
                    SELECT pid, season_year, game_date AS debut_date, prev_teamid AS from_tid, teamid AS to_tid
                    FROM games
                    WHERE prev_teamid IS NOT NULL AND prev_teamid <> teamid
                  ),
                  movers AS (
                    SELECT m.pid, m.season_year, m.debut_date, m.from_tid, m.to_tid,
                      AVG(old.pts) AS old_ppg, COUNT(*) AS old_games
                    FROM moves m
                    JOIN games old ON old.pid = m.pid
                      AND old.season_year = m.season_year
                      AND old.teamid = m.from_tid
                      AND old.game_date < m.debut_date
                    GROUP BY m.pid, m.season_year, m.debut_date, m.from_tid, m.to_tid
                    HAVING AVG(old.pts) >= ? AND COUNT(*) >= ?
                  ),
                  ranked AS (
                    SELECT mv.*,
                      COUNT(*) OVER (PARTITION BY mv.season_year, mv.from_tid, mv.to_tid, mv.debut_date) AS players_moved,
                      ROW_NUMBER() OVER (
                        PARTITION BY mv.season_year, mv.from_tid, mv.to_tid, mv.debut_date
                        ORDER BY mv.old_ppg DESC, mv.pid ASC
                      ) AS headline_rank
                    FROM movers mv
                  )
                  SELECT r.pid, r.season_year, r.debut_date, r.from_tid, r.to_tid, r.players_moved,
                    p.name AS player_name,
                    COALESCE(CONCAT(t.team_city, ' ', t.team_name), '') AS to_name,
                    COALESCE(CONCAT(f.team_city, ' ', f.team_name), '') AS from_name
                  FROM ranked r
                  JOIN `ibl_plr` p ON p.pid = r.pid
                  LEFT JOIN `ibl_team_info` t ON t.teamid = r.to_tid
                  LEFT JOIN `ibl_team_info` f ON f.teamid = r.from_tid
                  WHERE r.headline_rank = 1
                    AND r.season_year < ?
                    AND DATE_FORMAT(r.debut_date, '%m-%d') IN (" . self::MONTH_DAY_LIST . ")";

        $rows = $this->fetchAll(
            $query,
            'iii' . $mdTypes,
            self::TRADE_MIN_PPG,
            self::TRADE_MIN_GAMES,
            $beforeSeasonYear,
            ...$mdParams,
        );

        $moments = [];
        foreach ($rows as $row) {
            $date = $this->str($row, 'debut_date');
            $from = $this->int($row, 'from_tid');
            $to = $this->int($row, 'to_tid');
            $moments[] = new Moment(
                kind: MomentKind::Trade,
                key: MomentKind::Trade->value . ':' . $date . ':' . $from . '-' . $to,
                date: $date,
                seasonYear: $this->int($row, 'season_year'),
                pid: 0,
                playerName: $this->str($row, 'player_name'),
                teamId: $to,
                teamName: $this->str($row, 'to_name'),
                opponentTeamId: $from,
                opponentName: $this->str($row, 'from_name'),
                facts: ['players_moved' => $this->int($row, 'players_moved')],
            );
        }

        return $moments;
    }

    /**
     * Guarded player games inside the month/day window and before the season,
     * filtered by the kind's literal predicate on the guarded columns (alias `g`).
     *
     * @param list<string> $monthDays
     * @return list<array<string, mixed>>
     */
    private function fetchWindowedPlayerGames(MomentKind $kind, array $monthDays, int $beforeSeasonYear): array
    {
        [$mdTypes, $mdParams] = $this->monthDayPredicate($monthDays);

        $predicate = match ($kind) {
            MomentKind::FiftyPointGame => 'g.game_type IN (1, 2, 3) AND g.pts >= 50',
            MomentKind::TripleDouble => 'g.game_type IN (1, 2, 3)'
                . ' AND ((g.pts >= 10) + (g.reb >= 10) + (g.ast >= 10) + (g.stl >= 10) + (g.blk >= 10)) >= 3',
            default => throw new \InvalidArgumentException('Not a player-game kind: ' . $kind->value),
        };

        $query = "SELECT g.*, p.name AS player_name,
                    CASE WHEN g.teamid = g.visitor_teamid THEN g.home_teamid ELSE g.visitor_teamid END AS opponent_teamid,
                    COALESCE(CONCAT(t.team_city, ' ', t.team_name), '') AS team_name,
                    COALESCE(CONCAT(o.team_city, ' ', o.team_name), '') AS opponent_name
                  FROM (" . self::GUARDED_WINDOWED_PLAYER_GAMES . ") g
                  JOIN `ibl_plr` p ON p.pid = g.pid
                  LEFT JOIN `ibl_team_info` t ON t.teamid = g.teamid
                  LEFT JOIN `ibl_team_info` o
                    ON o.teamid = CASE WHEN g.teamid = g.visitor_teamid THEN g.home_teamid ELSE g.visitor_teamid END
                  WHERE " . $predicate;

        /** @var list<array<string, mixed>> $rows */
        $rows = array_values($this->fetchAll($query, 'i' . $mdTypes, $beforeSeasonYear, ...$mdParams));

        return $rows;
    }

    /**
     * Fixed-width `IN (...)` list for the month/day window, padded so the
     * placeholder string stays a literal.
     *
     * @param list<string> $monthDays 'MM-DD' strings, 1-15 entries
     * @return array{string, list<string>}
     */
    private function monthDayPredicate(array $monthDays): array
    {
        $count = count($monthDays);
        if ($count === 0 || $count > self::MONTH_DAY_LIST_WIDTH) {
            throw new \InvalidArgumentException(
                'month/day window must hold 1-' . self::MONTH_DAY_LIST_WIDTH . ' entries, got ' . $count
            );
        }

        $params = array_merge(
            array_values($monthDays),
            array_fill(0, self::MONTH_DAY_LIST_WIDTH - $count, self::MONTH_DAY_PAD)
        );

        return [self::MONTH_DAY_LIST_TYPES, $params];
    }

    /**
     * Fail closed on any record column outside the whitelist before it is
     * interpolated into SQL.
     */
    private function assertRecordColumn(string $column): void
    {
        if (!in_array($column, self::RECORD_STATS, true)) {
            throw new \InvalidArgumentException('Record column outside the whitelist: ' . $column);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function playerGameMoment(MomentKind $kind, array $row): Moment
    {
        $date = $this->str($row, 'game_date');
        $pid = $this->int($row, 'pid');

        return new Moment(
            kind: $kind,
            key: $kind->value . ':' . $date . ':' . $pid,
            date: $date,
            seasonYear: $this->int($row, 'season_year'),
            pid: $pid,
            playerName: $this->str($row, 'player_name'),
            teamId: $this->int($row, 'teamid'),
            teamName: $this->str($row, 'team_name'),
            opponentTeamId: $this->int($row, 'opponent_teamid'),
            opponentName: $this->str($row, 'opponent_name'),
            facts: [
                'points' => $this->int($row, 'pts'),
                'rebounds' => $this->int($row, 'reb'),
                'assists' => $this->int($row, 'ast'),
                'steals' => $this->int($row, 'stl'),
                'blocks' => $this->int($row, 'blk'),
            ],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function int(array $row, string $column): int
    {
        $value = $row[$column] ?? 0;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function str(array $row, string $column): string
    {
        $value = $row[$column] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }
}
