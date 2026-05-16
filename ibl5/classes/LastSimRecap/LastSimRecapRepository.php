<?php

declare(strict_types=1);

namespace LastSimRecap;

use LastSimRecap\Contracts\LastSimRecapRepositoryInterface;
use League\LeagueContext;

/**
 * @phpstan-import-type LastSimWindow from Contracts\LastSimRecapRepositoryInterface
 * @phpstan-import-type RecapGameRow from Contracts\LastSimRecapRepositoryInterface
 * @phpstan-import-type TeamBoxscoreLines from Contracts\LastSimRecapRepositoryInterface
 * @phpstan-import-type InjuryRow from Contracts\LastSimRecapRepositoryInterface
 * @phpstan-import-type StarterMap from Contracts\LastSimRecapRepositoryInterface
 * @phpstan-import-type PlayerLine from Contracts\LastSimRecapRepositoryInterface
 * @phpstan-import-type TeamRecord from Contracts\LastSimRecapRepositoryInterface
 *
 * @see LastSimRecapRepositoryInterface
 */
class LastSimRecapRepository extends \BaseMysqliRepository implements LastSimRecapRepositoryInterface
{
    private string $simDatesTable;
    private string $scheduleTable;
    private string $boxScoresTable;
    private string $boxScoresTeamsTable;
    private string $transactionsTable;
    private string $plrTable;
    private string $teamInfoTable;

    public function __construct(\mysqli $db, ?LeagueContext $leagueContext = null)
    {
        parent::__construct($db, $leagueContext);
        $this->simDatesTable = $this->resolveTable('ibl_sim_dates');
        $this->scheduleTable = $this->resolveTable('ibl_schedule');
        $this->boxScoresTable = $this->resolveTable('ibl_box_scores');
        $this->boxScoresTeamsTable = $this->resolveTable('ibl_box_scores_teams');
        $this->transactionsTable = $this->resolveTable('ibl_jsb_transactions');
        $this->plrTable = $this->resolveTable('ibl_plr');
        $this->teamInfoTable = $this->resolveTable('ibl_team_info');
    }

    /**
     * @return LastSimWindow|null
     */
    public function getLastSimWindow(): ?array
    {
        /** @var array{sim:int,start_date:string|null,end_date:string|null}|null $row */
        $row = $this->fetchOne(
            "SELECT sim, start_date, end_date FROM {$this->simDatesTable} ORDER BY sim DESC LIMIT 1"
        );

        if ($row === null || $row['start_date'] === null || $row['end_date'] === null) {
            return null;
        }

        return [
            'sim' => $row['sim'],
            'startDate' => $row['start_date'],
            'endDate' => $row['end_date'],
        ];
    }

    /**
     * @return list<RecapGameRow>
     */
    public function getGamesForTeamInWindow(int $tid, string $startDate, string $endDate): array
    {
        /** @var list<array{id:int,box_id:int,game_date:string,visitor_teamid:int,visitor_score:int,home_teamid:int,home_score:int,season_year:int}> $rows */
        $rows = $this->fetchAll(
            "SELECT id, box_id, game_date, visitor_teamid, visitor_score, home_teamid, home_score, season_year
             FROM {$this->scheduleTable}
             WHERE game_date BETWEEN ? AND ?
               AND (visitor_teamid = ? OR home_teamid = ?)
             ORDER BY game_date DESC, id DESC",
            "ssii",
            $startDate,
            $endDate,
            $tid,
            $tid
        );

        $games = [];
        foreach ($rows as $row) {
            $games[] = [
                'schedId' => $row['id'],
                'boxId' => $row['box_id'],
                'date' => $row['game_date'],
                'visitor' => $row['visitor_teamid'],
                'vScore' => $row['visitor_score'],
                'home' => $row['home_teamid'],
                'hScore' => $row['home_score'],
                'year' => $row['season_year'],
            ];
        }

        return $games;
    }

    /**
     * @return TeamBoxscoreLines|null
     */
    public function getTeamBoxscoreLines(int $visitor, int $home, string $date): ?array
    {
        /** @var array{
         *   visitor_q1_points:int|null,visitor_q2_points:int|null,visitor_q3_points:int|null,visitor_q4_points:int|null,visitor_ot_points:int|null,
         *   home_q1_points:int|null,home_q2_points:int|null,home_q3_points:int|null,home_q4_points:int|null,home_ot_points:int|null,
         *   visitor_wins:int|null,visitor_losses:int|null,home_wins:int|null,home_losses:int|null
         * }|null $row
         */
        $row = $this->fetchOne(
            "SELECT visitor_q1_points, visitor_q2_points, visitor_q3_points, visitor_q4_points, visitor_ot_points,
                    home_q1_points, home_q2_points, home_q3_points, home_q4_points, home_ot_points,
                    visitor_wins, visitor_losses, home_wins, home_losses
             FROM {$this->boxScoresTeamsTable}
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
             ORDER BY id ASC
             LIMIT 1",
            "sii",
            $date,
            $visitor,
            $home
        );

        if ($row === null) {
            return null;
        }

        return [
            'visQ' => [
                (int) ($row['visitor_q1_points'] ?? 0),
                (int) ($row['visitor_q2_points'] ?? 0),
                (int) ($row['visitor_q3_points'] ?? 0),
                (int) ($row['visitor_q4_points'] ?? 0),
            ],
            'homeQ' => [
                (int) ($row['home_q1_points'] ?? 0),
                (int) ($row['home_q2_points'] ?? 0),
                (int) ($row['home_q3_points'] ?? 0),
                (int) ($row['home_q4_points'] ?? 0),
            ],
            'visOT' => (int) ($row['visitor_ot_points'] ?? 0),
            'homeOT' => (int) ($row['home_ot_points'] ?? 0),
            'visitorPreWins' => (int) ($row['visitor_wins'] ?? 0),
            'visitorPreLosses' => (int) ($row['visitor_losses'] ?? 0),
            'homePreWins' => (int) ($row['home_wins'] ?? 0),
            'homePreLosses' => (int) ($row['home_losses'] ?? 0),
        ];
    }

    /**
     * @param list<int> $playerIds
     * @return list<InjuryRow>
     */
    public function getActiveInjuriesForPlayers(array $playerIds, string $date): array
    {
        if ($playerIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($playerIds), '?'));
        // Construct injury date from split year/month/day. Calendar year of
        // an October-December transaction = season_year - 1 (season_year is
        // the ending year per migration 106). Otherwise calendar year =
        // season_year.
        $dateExpr = "STR_TO_DATE(CONCAT("
            . "CASE WHEN t.transaction_month >= 9 THEN t.season_year - 1 ELSE t.season_year END,"
            . " '-', t.transaction_month, '-', t.transaction_day"
            . "), '%Y-%c-%e')";

        $sql = "SELECT t.pid,
                       p.name,
                       p.pos,
                       {$dateExpr} AS injury_date,
                       t.injury_description,
                       t.injury_games_missed,
                       DATEDIFF(DATE_ADD({$dateExpr}, INTERVAL t.injury_games_missed DAY), ?) AS days_remaining,
                       ({$dateExpr} = ?) AS is_new
                FROM {$this->transactionsTable} t
                JOIN {$this->plrTable} p ON p.pid = t.pid
                WHERE t.transaction_type = 1
                  AND t.pid IN ($placeholders)
                  AND t.injury_games_missed IS NOT NULL
                  AND {$dateExpr} <= ?
                  AND DATE_ADD({$dateExpr}, INTERVAL t.injury_games_missed DAY) > ?
                ORDER BY is_new DESC, injury_date DESC";

        // Build types/params: ss (date for days_remaining + is_new), pids (i...), ss (date <= + date <)
        $types = 'ss' . str_repeat('i', count($playerIds)) . 'ss';
        $params = array_merge([$date, $date], $playerIds, [$date, $date]);

        /** @var list<array{pid:int,name:string,pos:string,injury_date:string,injury_description:string|null,injury_games_missed:int,days_remaining:int,is_new:int}> $rows */
        $rows = $this->fetchAll($sql, $types, ...$params);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'pid' => $row['pid'],
                'name' => $row['name'],
                'pos' => $row['pos'],
                'date' => $row['injury_date'],
                'injuryDescription' => $row['injury_description'] ?? '',
                'injuryGamesMissed' => $row['injury_games_missed'],
                'daysRemaining' => max(0, $row['days_remaining']),
                'isNew' => $row['is_new'] === 1,
            ];
        }

        return $out;
    }

    /**
     * Returns the player IDs on the team as of $date. We don't have
     * per-day roster history, so this uses current `ibl_plr.teamid`. Good
     * enough for the recap card since the last-sim window is the most
     * recent week of games.
     *
     * @return list<int>
     */
    public function getTeamRosterPids(int $tid): array
    {
        /** @var list<array{pid:int}> $rows */
        $rows = $this->fetchAll(
            "SELECT pid FROM {$this->plrTable} WHERE teamid = ? AND retired = 0",
            "i",
            $tid
        );

        return array_map(static fn (array $r): int => $r['pid'], $rows);
    }

    /**
     * @return StarterMap|null
     */
    public function getStarterPidsFromSnapshot(int $tid, string $date): ?array
    {
        // Use the SavedDepthChart repo to find a chart whose window covers
        // the date. This duplicates the lookup but keeps the modules
        // independent of each other's internal table names.
        $depthRepo = new \SavedDepthChart\SavedDepthChartRepository($this->db);
        $result = $depthRepo->findActiveChartForTeamOnDate($tid, $date);
        if ($result === null) {
            return null;
        }

        $starters = $result['starters'];
        // Require all five positions filled before returning a snapshot.
        if ($starters['PG'] === null || $starters['SG'] === null
            || $starters['SF'] === null || $starters['PF'] === null
            || $starters['C']  === null
        ) {
            return null;
        }

        return [
            'PG' => $starters['PG'],
            'SG' => $starters['SG'],
            'SF' => $starters['SF'],
            'PF' => $starters['PF'],
            'C'  => $starters['C'],
        ];
    }

    /**
     * Fallback when no depth-chart snapshot covers the game: pick the
     * top-game_min player per position from the team's box-score lines for
     * that game. DNP rows (`game_min = 0`) are excluded.
     *
     * @return StarterMap
     */
    public function getStarterPidsFromBoxScores(int $schedId, int $tid): array
    {
        /** @var array{game_date:string|null,visitor_teamid:int|null,home_teamid:int|null}|null $game */
        $game = $this->fetchOne(
            "SELECT game_date, visitor_teamid, home_teamid FROM {$this->scheduleTable} WHERE id = ? LIMIT 1",
            "i",
            $schedId
        );

        if ($game === null || $game['game_date'] === null) {
            return ['PG' => 0, 'SG' => 0, 'SF' => 0, 'PF' => 0, 'C' => 0];
        }

        /** @var list<array{pid:int,pos:string,game_min:int}> $rows */
        $rows = $this->fetchAll(
            "SELECT pid, pos, game_min
             FROM {$this->boxScoresTable}
             WHERE game_date = ?
               AND visitor_teamid = ?
               AND home_teamid = ?
               AND teamid = ?
               AND game_min > 0
             ORDER BY game_min DESC, pid ASC",
            "siii",
            $game['game_date'],
            (int) $game['visitor_teamid'],
            (int) $game['home_teamid'],
            $tid
        );

        $starters = ['PG' => 0, 'SG' => 0, 'SF' => 0, 'PF' => 0, 'C' => 0];
        foreach ($rows as $row) {
            $pos = $row['pos'];
            if (isset($starters[$pos]) && $starters[$pos] === 0) {
                $starters[$pos] = $row['pid'];
            }
        }

        return $starters;
    }

    /**
     * @return PlayerLine|null
     */
    public function getPlayerLineForGame(int $pid, int $schedId): ?array
    {
        /** @var array{game_date:string|null,visitor_teamid:int|null,home_teamid:int|null}|null $game */
        $game = $this->fetchOne(
            "SELECT game_date, visitor_teamid, home_teamid FROM {$this->scheduleTable} WHERE id = ? LIMIT 1",
            "i",
            $schedId
        );

        if ($game === null || $game['game_date'] === null) {
            return null;
        }

        /** @var array{pid:int,name:string,pos:string,calc_points:int|null,game_min:int|null}|null $row */
        $row = $this->fetchOne(
            "SELECT pid, name, pos, calc_points, game_min
             FROM {$this->boxScoresTable}
             WHERE game_date = ?
               AND visitor_teamid = ?
               AND home_teamid = ?
               AND pid = ?
             ORDER BY game_min DESC
             LIMIT 1",
            "siii",
            $game['game_date'],
            (int) $game['visitor_teamid'],
            (int) $game['home_teamid'],
            $pid
        );

        if ($row === null) {
            return null;
        }

        return [
            'pid' => $row['pid'],
            'name' => $row['name'],
            'pos' => $row['pos'],
            'pts' => (int) ($row['calc_points'] ?? 0),
            'minutes' => (int) ($row['game_min'] ?? 0),
        ];
    }

    /**
     * Returns the team's record as of `$date` (inclusive of games on
     * $date). Uses `ibl_schedule`.
     *
     * @return TeamRecord
     */
    public function getTeamRecordAsOf(int $tid, string $date): array
    {
        /** @var array{wins:int|null,losses:int|null}|null $row */
        $row = $this->fetchOne(
            "SELECT
                SUM(CASE
                    WHEN (visitor_teamid = ? AND visitor_score > home_score)
                      OR (home_teamid    = ? AND home_score    > visitor_score)
                    THEN 1 ELSE 0
                END) AS wins,
                SUM(CASE
                    WHEN (visitor_teamid = ? AND visitor_score < home_score)
                      OR (home_teamid    = ? AND home_score    < visitor_score)
                    THEN 1 ELSE 0
                END) AS losses
             FROM {$this->scheduleTable}
             WHERE (visitor_teamid = ? OR home_teamid = ?)
               AND game_date <= ?
               AND (visitor_score > 0 OR home_score > 0)",
            "iiiiiis",
            $tid, $tid, $tid, $tid, $tid, $tid, $date
        );

        return [
            'wins' => (int) ($row['wins'] ?? 0),
            'losses' => (int) ($row['losses'] ?? 0),
        ];
    }

    /**
     * @return array{tid:int,city:string,name:string}|null
     */
    public function getTeamInfo(int $tid): ?array
    {
        /** @var array{teamid:int,team_city:string,team_name:string}|null $row */
        $row = $this->fetchOne(
            "SELECT teamid, team_city, team_name FROM {$this->teamInfoTable} WHERE teamid = ? LIMIT 1",
            "i",
            $tid
        );

        if ($row === null) {
            return null;
        }

        return [
            'tid' => $row['teamid'],
            'city' => $row['team_city'],
            'name' => $row['team_name'],
        ];
    }
}
