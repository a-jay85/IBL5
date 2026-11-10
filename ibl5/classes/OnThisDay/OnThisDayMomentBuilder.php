<?php

declare(strict_types=1);

namespace OnThisDay;

use League\League;
use OnThisDay\Contracts\OnThisDayMomentBuilderInterface;

/**
 * OnThisDayMomentBuilder - computes every league-history moment from box scores
 * and stores them in ibl_on_this_day_moments. Runs only inside the nightly
 * updater (RebuildOnThisDayMomentsStep); this is the only OnThisDay class that
 * reads ibl_box_scores.
 *
 * Every player-game source reads the phantom-guarded temp table
 * (built once per collect() from GUARDED_PLAYER_GAMES): one row per (game_date, visitor, home, pid). A repeated
 * row set for the same game is a phantom, and grouping collapses it to one line.
 * Only games between real teams count: All-Star and Rookie/Sophomore box scores
 * share game_type 1 with the regular season, so the team-id filter drops them.
 * Player names come from ibl_plr on pid; box-score `name` is never read.
 *
 * IBL-only: the table is not in LeagueContext::TABLE_MAP.
 *
 * @see \Database\BaseMysqliRepository For base class documentation
 */
class OnThisDayMomentBuilder extends \Database\BaseMysqliRepository implements OnThisDayMomentBuilderInterface
{
    /**
     * Record-eligible stats: label => guarded-table column.
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
     * adds points. Every player-game source reads this table.
     * The real-team filter (same as RecordHolders\TeamRecordRepository) drops the
     * All-Star and Rookie/Sophomore games, which carry game_type 1.
     */
    private const GUARDED_SELECT = "SELECT b.game_date, b.visitor_teamid, b.home_teamid, b.pid,
                  MAX(b.teamid) AS teamid, MAX(b.calc_points) AS pts, MAX(b.calc_rebounds) AS reb,
                  MAX(b.game_ast) AS ast, MAX(b.game_stl) AS stl, MAX(b.game_blk) AS blk,
                  MAX(b.season_year) AS season_year, MAX(b.game_type) AS game_type
                FROM `ibl_box_scores` b
                WHERE b.pid > 0
                  AND b.visitor_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                  AND b.home_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID;

    private const GUARDED_GROUP_BY = " GROUP BY b.game_date, b.visitor_teamid, b.home_teamid, b.pid";

    /** Body of the guarded temp table, built once per collect(). */
    private const GUARDED_PLAYER_GAMES = self::GUARDED_SELECT . self::GUARDED_GROUP_BY;

    /** Session temp tables. Each collector names one of them once, so no statement reopens a temp table. */
    private const DROP_GUARDED_SQL = 'DROP TEMPORARY TABLE IF EXISTS `tmp_otd_guarded_games`';

    private const DROP_OLD_GAMES_SQL = 'DROP TEMPORARY TABLE IF EXISTS `tmp_otd_trade_old_games`';

    /** Every game type; collectors keep filtering game_type after the aggregate, as before. */
    private const CREATE_GUARDED_SQL = "CREATE TEMPORARY TABLE `tmp_otd_guarded_games` ENGINE=InnoDB AS "
        . self::GUARDED_PLAYER_GAMES;

    /** Regular-season copy for the trades `old` join, indexed for its lookup. */
    private const CREATE_OLD_GAMES_SQL = "CREATE TEMPORARY TABLE `tmp_otd_trade_old_games`
            (KEY `idx_otd_old_games` (pid, season_year, teamid, game_date)) ENGINE=InnoDB AS
          SELECT g.pid, g.season_year, g.teamid, g.game_date, g.pts
          FROM `tmp_otd_guarded_games` g
          WHERE g.game_type = 1";

    /**
     * Regular-season guarded lines in the order the old running-MAX window used,
     * streamed once unbuffered into RecordGameTracker. No binds.
     */
    private const RECORD_STREAM_SQL = "SELECT g.game_date, g.visitor_teamid, g.home_teamid, g.pid, g.teamid,
            g.season_year, g.pts, g.reb, g.ast, g.stl, g.blk
          FROM `tmp_otd_guarded_games` g
          WHERE g.game_type = 1
          ORDER BY g.game_date, g.visitor_teamid, g.home_teamid, g.pid";

    // LAG orders by game_date inside (pid, season_year), unique in the guarded table; the final ORDER BY is the ranked partition key, unique at headline_rank = 1.
    // @phpstan-ignore ibl.orderByMissingTiebreaker
    private const TRADES_SQL = "WITH games AS (
                    SELECT g.pid, g.season_year, g.game_date, g.teamid, g.pts,
                      LAG(g.teamid) OVER (PARTITION BY g.pid, g.season_year ORDER BY g.game_date) AS prev_teamid
                    FROM `tmp_otd_guarded_games` g
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
                    JOIN `tmp_otd_trade_old_games` old ON old.pid = m.pid
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
                  ORDER BY r.season_year, r.from_tid, r.to_tid, r.debut_date";

    private const INSERT_SQL = "INSERT INTO `ibl_on_this_day_moments`
            (moment_key, kind, game_date, month_day, season_year, pid, player_name,
             teamid, team_name, opponent_teamid, opponent_name, facts_json, stat_label, game_of_that_day)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    /** Minimum old-team scoring average for a mover to count as a trade. */
    private const TRADE_MIN_PPG = 15;

    /** Minimum old-team games before the move. */
    private const TRADE_MIN_GAMES = 5;

    /**
     * @see OnThisDayMomentBuilderInterface::collect()
     */
    public function collect(): array
    {
        try {
            $this->buildGuardedGames();
            $moments = array_merge(
                $this->collectPlayerGames(MomentKind::FiftyPointGame),
                $this->collectPlayerGames(MomentKind::TripleDouble),
                $this->collectRecordGames(),
                $this->collectSeriesClinchers(),
                $this->collectTrades(),
            );
        } finally {
            $this->dropGuardedGames(); // also runs when a collector throws
        }

        return $this->stampOrdinals($moments);
    }

    /**
     * Aggregates ibl_box_scores once per collect(). Runs before the unbuffered
     * record stream opens, because an open stream blocks every other query.
     */
    private function buildGuardedGames(): void
    {
        $this->dropGuardedGames(); // a leftover table from an earlier run on this session
        $this->execute(self::CREATE_GUARDED_SQL);
        $this->execute(self::CREATE_OLD_GAMES_SQL);
    }

    private function dropGuardedGames(): void
    {
        $this->execute(self::DROP_OLD_GAMES_SQL);
        $this->execute(self::DROP_GUARDED_SQL);
    }

    /**
     * Stamp each moment with its game's league-wide ordinal, the box-score link
     * key. GAME_OF_THAT_DAY_SUBQUERY takes MIN over (date, visitor, home), the
     * rule the schedule page links by, so a phantom copy never supplies the
     * ordinal. No doubleheaders: date|teamId names one game. A missing key or an
     * out-of-range value stamps 0 (no link). Runs after every collector, so the
     * unbuffered record stream is already freed.
     *
     * @param list<Moment> $moments
     * @return list<Moment>
     */
    private function stampOrdinals(array $moments): array
    {
        $ordinals = [];
        $sql = 'SELECT gotd.game_date, gotd.visitor_teamid, gotd.home_teamid, gotd.game_of_that_day FROM '
            . self::GAME_OF_THAT_DAY_SUBQUERY . ' gotd';
        foreach ($this->fetchAll($sql) as $row) {
            $date = $this->str($row, 'game_date');
            $ordinal = $this->int($row, 'game_of_that_day');
            $ordinals[$date . '|' . $this->int($row, 'visitor_teamid')] = $ordinal;
            $ordinals[$date . '|' . $this->int($row, 'home_teamid')] = $ordinal;
        }

        return array_map(static function (Moment $m) use ($ordinals): Moment {
            $ordinal = $ordinals[$m->date . '|' . $m->teamId] ?? 0;

            return $m->withGameOfThatDay($ordinal >= 1 && $ordinal <= 255 ? $ordinal : 0);
        }, $moments);
    }

    /**
     * Every source query finishes before the first write. DELETE (never
     * TRUNCATE, which commits implicitly) and the inserts share one
     * transaction, so a failed rebuild rolls back and readers keep the previous
     * rows until COMMIT.
     *
     * @see OnThisDayMomentBuilderInterface::rebuild()
     */
    public function rebuild(): int
    {
        $moments = $this->collect();

        return $this->transactional(function () use ($moments): int {
            $this->execute('DELETE FROM `ibl_on_this_day_moments`');
            foreach ($moments as $moment) {
                $this->execute(
                    self::INSERT_SQL,
                    'ssssiisisisssi',
                    $moment->key,
                    $moment->kind->value,
                    $moment->date,
                    substr($moment->date, 5, 5),
                    $moment->seasonYear,
                    $moment->pid,
                    $moment->playerName,
                    $moment->teamId,
                    $moment->teamName,
                    $moment->opponentTeamId,
                    $moment->opponentName,
                    json_encode($moment->facts, JSON_THROW_ON_ERROR),
                    $moment->statLabel,
                    $moment->gameOfThatDay,
                );
            }

            return count($moments);
        });
    }

    /**
     * Fifty-point games and triple-doubles across all history, filtered by the
     * kind's literal predicate on the guarded columns (alias `g`).
     *
     * @return list<Moment>
     */
    private function collectPlayerGames(MomentKind $kind): array
    {
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
                  FROM `tmp_otd_guarded_games` g
                  JOIN `ibl_plr` p ON p.pid = g.pid
                  LEFT JOIN `ibl_team_info` t ON t.teamid = g.teamid
                  LEFT JOIN `ibl_team_info` o
                    ON o.teamid = CASE WHEN g.teamid = g.visitor_teamid THEN g.home_teamid ELSE g.visitor_teamid END
                  WHERE " . $predicate . ' ORDER BY g.game_date, g.visitor_teamid, g.home_teamid, g.pid';

        $moments = [];
        foreach ($this->fetchAll($query) as $row) {
            $moments[] = $this->playerGameMoment($kind, $row);
        }

        return $moments;
    }

    /**
     * A record game strictly exceeded every earlier regular-season single-game
     * value in league history. Games in the league's first box-score season never
     * count. One unbuffered pass in chronological order feeds RecordGameTracker,
     * so time is linear in box-score rows and memory stays flat.
     *
     * Order matters: an open unbuffered result blocks every other query on the
     * connection, so the first-season and name lookups run before the stream.
     *
     * @return list<Moment>
     */
    private function collectRecordGames(): array
    {
        $first = $this->fetchOne('SELECT MIN(season_year) AS first_season FROM `ibl_box_scores` WHERE game_type = 1');
        $firstSeason = $first['first_season'] ?? null;
        if (!is_numeric($firstSeason)) {
            return [];
        }

        $playerNames = [];
        foreach ($this->fetchAll('SELECT pid, name FROM `ibl_plr`') as $row) {
            $playerNames[self::scalarToInt($row['pid'] ?? null)] = self::scalarToString($row['name'] ?? null);
        }

        $teamNames = [];
        foreach ($this->fetchAll('SELECT teamid, team_city, team_name FROM `ibl_team_info`') as $row) {
            $teamNames[self::scalarToInt($row['teamid'] ?? null)] = self::scalarToString($row['team_city'] ?? null)
                . ' ' . self::scalarToString($row['team_name'] ?? null);
        }

        $tracker = new RecordGameTracker((int) $firstSeason);
        $moments = [];

        // Unbuffered stream over literal SQL with no binds; prepared statements would buffer the result.
        $result = $this->db->query(self::RECORD_STREAM_SQL, MYSQLI_USE_RESULT); // @phpstan-ignore ibl.directMysqliQuery (unbuffered stream over literal SQL, no binds)
        if (!$result instanceof \mysqli_result) {
            throw new \RuntimeException('On This Day record stream failed: ' . $this->db->error);
        }

        try {
            while (($row = $result->fetch_assoc()) !== null && $row !== false) {
                $stats = [];
                foreach (self::RECORD_STATS as $label => $column) {
                    $stats[$label] = $this->int($row, $column);
                }

                $labels = $tracker->observe($this->int($row, 'season_year'), $stats);
                $pid = $this->int($row, 'pid');
                if ($labels === [] || !array_key_exists($pid, $playerNames)) {
                    continue;
                }

                $date = $this->str($row, 'game_date');
                $teamId = $this->int($row, 'teamid');
                $opponentTeamId = $teamId === $this->int($row, 'visitor_teamid')
                    ? $this->int($row, 'home_teamid')
                    : $this->int($row, 'visitor_teamid');

                foreach ($labels as $label) {
                    $moments[] = new Moment(
                        kind: MomentKind::RecordGame,
                        key: MomentKind::RecordGame->value . ':' . $date . ':' . $pid . ':' . $label,
                        date: $date,
                        seasonYear: $this->int($row, 'season_year'),
                        pid: $pid,
                        playerName: $playerNames[$pid],
                        teamId: $teamId,
                        teamName: $teamNames[$teamId] ?? '',
                        opponentTeamId: $opponentTeamId,
                        opponentName: $teamNames[$opponentTeamId] ?? '',
                        facts: ['value' => $stats[$label]],
                        statLabel: $label,
                    );
                }
            }
        } finally {
            $result->free();
        }

        return $moments;
    }

    /**
     * Box scores date the clinch (last playoff game between the pair that
     * season); the derived series table supplies only the round and the score.
     *
     * @return list<Moment>
     */
    private function collectSeriesClinchers(): array
    {
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
                  LEFT JOIN `ibl_team_info` l ON l.teamid = s.loser_tid";

        $moments = [];
        foreach ($this->fetchAll($query) as $row) {
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
     * @return list<Moment>
     */
    private function collectTrades(): array
    {
        $rows = $this->fetchAll(self::TRADES_SQL, 'ii', self::TRADE_MIN_PPG, self::TRADE_MIN_GAMES);

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
                facts: ['players_moved' => $this->int($row, 'players_moved'), 'mover_pid' => $this->int($row, 'pid')],
            );
        }

        return $moments;
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
