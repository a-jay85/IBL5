<?php

declare(strict_types=1);

namespace RecordHolders;

use RecordHolders\Contracts\RecordHoldersRepositoryInterface;

/**
 * RecordHoldersRepository - Data access layer for all-time IBL records.
 *
 * Retrieves record data from box scores, history, awards, and team tables.
 *
 * @phpstan-import-type PlayerSingleGameRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type PlayerSeasonRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type QuadrupleDoubleRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type AllStarRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type TeamSingleGameRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type TeamHalfRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type MarginRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type SeasonWinLossRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type StreakRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type SeasonStartRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type FranchiseTitleRecord from RecordHoldersRepositoryInterface
 * @phpstan-import-type PlayoffAppearanceRecord from RecordHoldersRepositoryInterface
 *
 * @see RecordHoldersRepositoryInterface
 * @see \BaseMysqliRepository For base class documentation
 */
class RecordHoldersRepository extends \BaseMysqliRepository implements RecordHoldersRepositoryInterface
{
    private const ANNOUNCEMENT_CACHE_KEY = 'record_announcements_last_date';

    /**
     * Season ending year derivation from game date.
     *
     * IBL seasons span two calendar years (Nov-May). HEAT is in October.
     * Games in Oct-Dec belong to a season ending the NEXT calendar year.
     * Games in Jan-Jun belong to a season ending the SAME calendar year.
     */
    private const SEASON_YEAR_EXPRESSION = 'bs.season_year';

    /** @var list<array{Date: string, visitorTeamID: int, homeTeamID: int, visitorScore: int, homeScore: int}>|null */
    private ?array $regularSeasonGamesCache = null;

    /** @var array<int, string> Team ID → team name lookup cache */
    private array $teamNameCache = [];

    /**
     * @see RecordHoldersRepositoryInterface::getTopPlayerSingleGame()
     *
     * @return list<PlayerSingleGameRecord>
     */
    public function getTopPlayerSingleGame(string $statExpression, string $dateFilter): array
    {
        $query = "SELECT
                bs.pid,
                p.name,
                h.teamid AS tid,
                h.team AS team_name,
                bs.Date AS `date`,
                COALESCE(sch.BoxID, 0) AS BoxID,
                COALESCE(bst.gameOfThatDay, 0) AS gameOfThatDay,
                CASE WHEN h.teamid = bs.visitorTID THEN bs.homeTID ELSE bs.visitorTID END AS oppTid,
                opp.team_name AS opp_team_name,
                {$statExpression} AS value
            FROM ibl_box_scores bs
            JOIN ibl_plr p ON p.pid = bs.pid
            JOIN ibl_hist h ON h.pid = bs.pid AND h.year = ({$this->seasonYearExpression()})
            LEFT JOIN ibl_schedule sch ON sch.Date = bs.Date
                AND sch.Visitor = bs.visitorTID AND sch.Home = bs.homeTID
            LEFT JOIN (
                SELECT Date, visitorTeamID, homeTeamID, MIN(gameOfThatDay) AS gameOfThatDay
                FROM ibl_box_scores_teams
                GROUP BY Date, visitorTeamID, homeTeamID
            ) bst ON bst.Date = bs.Date AND bst.visitorTeamID = bs.visitorTID AND bst.homeTeamID = bs.homeTID
            LEFT JOIN ibl_team_info opp ON opp.teamid = CASE
                WHEN h.teamid = bs.visitorTID THEN bs.homeTID
                ELSE bs.visitorTID END
            WHERE {$dateFilter}
            ORDER BY value DESC, bs.Date ASC
            LIMIT 5";

        $rows = $this->fetchAll($query);

        /** @var list<PlayerSingleGameRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int} $row */
            $records[] = [
                'pid' => $row['pid'],
                'name' => $row['name'],
                'tid' => $row['tid'],
                'team_name' => $row['team_name'],
                'date' => $row['date'],
                'BoxID' => $row['BoxID'],
                'gameOfThatDay' => $row['gameOfThatDay'],
                'oppTid' => $row['oppTid'],
                'opp_team_name' => $row['opp_team_name'],
                'value' => $row['value'],
            ];
        }

        return $records;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getTopSeasonAverage()
     *
     * @return list<PlayerSeasonRecord>
     */
    public function getTopSeasonAverage(string $statColumn, string $gamesColumn, int $minGames = 50): array
    {
        $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $statColumn);
        if ($safeColumn === null || $safeColumn === '') {
            return [];
        }
        $safeGames = preg_replace('/[^a-zA-Z0-9_]/', '', $gamesColumn);
        if ($safeGames === null || $safeGames === '') {
            return [];
        }

        $query = "SELECT
                h.pid,
                h.name,
                h.teamid,
                h.team,
                h.year,
                ROUND(h.{$safeColumn} / h.{$safeGames}, 1) AS value
            FROM ibl_hist h
            WHERE h.{$safeGames} >= ?
            ORDER BY value DESC
            LIMIT 5";

        $rows = $this->fetchAll($query, 'i', $minGames);

        /** @var list<PlayerSeasonRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{pid: int, name: string, teamid: int, team: string, year: int, value: float} $row */
            $records[] = [
                'pid' => $row['pid'],
                'name' => $row['name'],
                'teamid' => $row['teamid'],
                'team' => $row['team'],
                'year' => $row['year'],
                'value' => $row['value'],
            ];
        }

        return $records;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getQuadrupleDoubles()
     *
     * @return list<QuadrupleDoubleRecord>
     */
    public function getQuadrupleDoubles(): array
    {
        $query = "SELECT
                bs.pid,
                p.name,
                h.teamid AS tid,
                h.team AS team_name,
                bs.Date AS `date`,
                COALESCE(sch.BoxID, 0) AS BoxID,
                COALESCE(bst.gameOfThatDay, 0) AS gameOfThatDay,
                CASE WHEN h.teamid = bs.visitorTID THEN bs.homeTID ELSE bs.visitorTID END AS oppTid,
                opp.team_name AS opp_team_name,
                bs.calc_points AS points,
                bs.calc_rebounds AS rebounds,
                bs.gameAST AS assists,
                bs.gameSTL AS steals,
                bs.gameBLK AS blocks
            FROM ibl_box_scores bs
            JOIN ibl_plr p ON p.pid = bs.pid
            JOIN ibl_hist h ON h.pid = bs.pid AND h.year = ({$this->seasonYearExpression()})
            LEFT JOIN ibl_schedule sch ON sch.Date = bs.Date
                AND sch.Visitor = bs.visitorTID AND sch.Home = bs.homeTID
            LEFT JOIN (
                SELECT Date, visitorTeamID, homeTeamID, MIN(gameOfThatDay) AS gameOfThatDay
                FROM ibl_box_scores_teams
                GROUP BY Date, visitorTeamID, homeTeamID
            ) bst ON bst.Date = bs.Date AND bst.visitorTeamID = bs.visitorTID AND bst.homeTeamID = bs.homeTID
            LEFT JOIN ibl_team_info opp ON opp.teamid = CASE
                WHEN h.teamid = bs.visitorTID THEN bs.homeTID
                ELSE bs.visitorTID END
            WHERE (
                (CASE WHEN bs.calc_points >= 10 THEN 1 ELSE 0 END)
                + (CASE WHEN bs.calc_rebounds >= 10 THEN 1 ELSE 0 END)
                + (CASE WHEN bs.gameAST >= 10 THEN 1 ELSE 0 END)
                + (CASE WHEN bs.gameSTL >= 10 THEN 1 ELSE 0 END)
                + (CASE WHEN bs.gameBLK >= 10 THEN 1 ELSE 0 END)
            ) >= 4
            ORDER BY bs.Date ASC";

        $rows = $this->fetchAll($query);

        /** @var list<QuadrupleDoubleRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, points: int, rebounds: int, assists: int, steals: int, blocks: int} $row */
            $records[] = [
                'pid' => $row['pid'],
                'name' => $row['name'],
                'tid' => $row['tid'],
                'team_name' => $row['team_name'],
                'date' => $row['date'],
                'BoxID' => $row['BoxID'],
                'gameOfThatDay' => $row['gameOfThatDay'],
                'oppTid' => $row['oppTid'],
                'opp_team_name' => $row['opp_team_name'],
                'points' => $row['points'],
                'rebounds' => $row['rebounds'],
                'assists' => $row['assists'],
                'steals' => $row['steals'],
                'blocks' => $row['blocks'],
            ];
        }

        return $records;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getMostAllStarAppearances()
     *
     * @return list<AllStarRecord>
     */
    public function getMostAllStarAppearances(): array
    {
        $query = "SELECT a.name, h.pid, COUNT(*) AS appearances
            FROM ibl_awards a
            LEFT JOIN (SELECT DISTINCT pid, name FROM ibl_hist) h ON h.name = a.name
            WHERE a.Award LIKE '%Conference All-Star'
            GROUP BY a.name, h.pid
            ORDER BY appearances DESC, a.name ASC
            LIMIT 5";

        $rows = $this->fetchAll($query);

        /** @var list<AllStarRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{name: string, pid: int|null, appearances: int} $row */
            $records[] = [
                'name' => $row['name'],
                'pid' => $row['pid'] !== null ? (int) $row['pid'] : null,
                'appearances' => $row['appearances'],
            ];
        }

        return $records;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getTopTeamSingleGame()
     *
     * @return list<TeamSingleGameRecord>
     */
    public function getTopTeamSingleGame(string $statExpression, string $dateFilter, string $order = 'DESC'): array
    {
        $safeOrder = $order === 'ASC' ? 'ASC' : 'DESC';

        $query = "SELECT
                t.teamid AS tid,
                t.team_name,
                bs.Date AS `date`,
                COALESCE(sch.BoxID, 0) AS BoxID,
                COALESCE(bs.gameOfThatDay, 0) AS gameOfThatDay,
                CASE WHEN t.teamid = bs.visitorTeamID THEN bs.homeTeamID ELSE bs.visitorTeamID END AS oppTid,
                opp.team_name AS opp_team_name,
                {$statExpression} AS value
            FROM ibl_box_scores_teams bs
            JOIN ibl_team_info t ON t.team_name = bs.name
            LEFT JOIN ibl_schedule sch ON sch.Date = bs.Date
                AND sch.Visitor = bs.visitorTeamID AND sch.Home = bs.homeTeamID
            LEFT JOIN ibl_team_info opp ON opp.teamid = CASE
                WHEN t.teamid = bs.visitorTeamID THEN bs.homeTeamID
                ELSE bs.visitorTeamID END
            WHERE {$dateFilter}
            ORDER BY value {$safeOrder}, bs.Date ASC
            LIMIT 5";

        $rows = $this->fetchAll($query);

        /** @var list<TeamSingleGameRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int} $row */
            $records[] = [
                'tid' => $row['tid'],
                'team_name' => $row['team_name'],
                'date' => $row['date'],
                'BoxID' => $row['BoxID'],
                'gameOfThatDay' => $row['gameOfThatDay'],
                'oppTid' => $row['oppTid'],
                'opp_team_name' => $row['opp_team_name'],
                'value' => $row['value'],
            ];
        }

        return $records;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getTopTeamHalfScore()
     *
     * @return list<TeamHalfRecord>
     */
    public function getTopTeamHalfScore(string $half, string $order): array
    {
        $safeOrder = $order === 'ASC' ? 'ASC' : 'DESC';

        if ($half === 'first') {
            // First half: Q1 + Q2 — determine which team based on visitor/home columns
            $expression = "(CASE WHEN t.teamid = bs.visitorTeamID
                THEN bs.visitorQ1points + bs.visitorQ2points
                ELSE bs.homeQ1points + bs.homeQ2points END)";
        } else {
            // Second half: Q3 + Q4 + OT
            $expression = "(CASE WHEN t.teamid = bs.visitorTeamID
                THEN bs.visitorQ3points + bs.visitorQ4points + COALESCE(bs.visitorOTpoints, 0)
                ELSE bs.homeQ3points + bs.homeQ4points + COALESCE(bs.homeOTpoints, 0) END)";
        }

        $query = "SELECT
                t.teamid AS tid,
                t.team_name,
                bs.Date AS `date`,
                COALESCE(sch.BoxID, 0) AS BoxID,
                COALESCE(bs.gameOfThatDay, 0) AS gameOfThatDay,
                CASE WHEN t.teamid = bs.visitorTeamID THEN bs.homeTeamID ELSE bs.visitorTeamID END AS oppTid,
                opp.team_name AS opp_team_name,
                {$expression} AS value
            FROM ibl_box_scores_teams bs
            JOIN ibl_team_info t ON t.team_name = bs.name
            LEFT JOIN ibl_schedule sch ON sch.Date = bs.Date
                AND sch.Visitor = bs.visitorTeamID AND sch.Home = bs.homeTeamID
            LEFT JOIN ibl_team_info opp ON opp.teamid = CASE
                WHEN t.teamid = bs.visitorTeamID THEN bs.homeTeamID
                ELSE bs.visitorTeamID END
            ORDER BY value {$safeOrder}, bs.Date ASC
            LIMIT 5";

        $rows = $this->fetchAll($query);

        /** @var list<TeamHalfRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int} $row */
            $records[] = [
                'tid' => $row['tid'],
                'team_name' => $row['team_name'],
                'date' => $row['date'],
                'BoxID' => $row['BoxID'],
                'gameOfThatDay' => $row['gameOfThatDay'],
                'oppTid' => $row['oppTid'],
                'opp_team_name' => $row['opp_team_name'],
                'value' => $row['value'],
            ];
        }

        return $records;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getLargestMarginOfVictory()
     *
     * Each row in ibl_box_scores_teams already has both visitor and home quarter scores,
     * so we compute the margin from a single row grouped by game (no self-join needed).
     *
     * @return list<MarginRecord>
     */
    public function getLargestMarginOfVictory(string $dateFilter): array
    {
        $query = "SELECT
                winner_t.teamid AS winner_tid,
                winner_t.team_name AS winner_name,
                loser_t.teamid AS loser_tid,
                loser_t.team_name AS loser_name,
                sub.Date AS `date`,
                COALESCE(sch.BoxID, 0) AS BoxID,
                COALESCE(bst.gameOfThatDay, 0) AS gameOfThatDay,
                sub.margin
            FROM (
                SELECT
                    bs.Date,
                    bs.visitorTeamID,
                    bs.homeTeamID,
                    ABS(bs.visitorScore - bs.homeScore) AS margin,
                    CASE WHEN bs.visitorScore > bs.homeScore
                        THEN bs.visitorTeamID ELSE bs.homeTeamID END AS winner_id,
                    CASE WHEN bs.visitorScore > bs.homeScore
                        THEN bs.homeTeamID ELSE bs.visitorTeamID END AS loser_id
                FROM vw_team_total_score bs
                WHERE {$dateFilter}
                GROUP BY bs.Date, bs.visitorTeamID, bs.homeTeamID
            ) sub
            JOIN ibl_team_info winner_t ON winner_t.teamid = sub.winner_id
            JOIN ibl_team_info loser_t ON loser_t.teamid = sub.loser_id
            LEFT JOIN ibl_schedule sch ON sch.Date = sub.Date
                AND sch.Visitor = sub.visitorTeamID AND sch.Home = sub.homeTeamID
            LEFT JOIN (
                SELECT Date, visitorTeamID, homeTeamID, MIN(gameOfThatDay) AS gameOfThatDay
                FROM ibl_box_scores_teams
                GROUP BY Date, visitorTeamID, homeTeamID
            ) bst ON bst.Date = sub.Date AND bst.visitorTeamID = sub.visitorTeamID AND bst.homeTeamID = sub.homeTeamID
            ORDER BY sub.margin DESC, sub.Date ASC
            LIMIT 5";

        $rows = $this->fetchAll($query);

        /** @var list<MarginRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{winner_tid: int, winner_name: string, loser_tid: int, loser_name: string, date: string, BoxID: int, gameOfThatDay: int, margin: int} $row */
            $records[] = [
                'winner_tid' => $row['winner_tid'],
                'winner_name' => $row['winner_name'],
                'loser_tid' => $row['loser_tid'],
                'loser_name' => $row['loser_name'],
                'date' => $row['date'],
                'BoxID' => $row['BoxID'],
                'gameOfThatDay' => $row['gameOfThatDay'],
                'margin' => $row['margin'],
            ];
        }

        return $records;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getBestWorstSeasonRecord()
     *
     * @return list<SeasonWinLossRecord>
     */
    public function getBestWorstSeasonRecord(string $order): array
    {
        $safeOrder = $order === 'ASC' ? 'ASC' : 'DESC';

        $query = "SELECT
                currentname AS team_name,
                year,
                wins,
                losses
            FROM ibl_team_win_loss
            WHERE currentname != 'Free Agents'
                AND (wins + losses) > 0
            ORDER BY (wins / (wins + losses)) {$safeOrder},
                wins {$safeOrder}
            LIMIT 5";

        $rows = $this->fetchAll($query);

        /** @var list<SeasonWinLossRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, year: int, wins: int, losses: int} $row */
            $records[] = [
                'team_name' => $row['team_name'],
                'year' => $row['year'],
                'wins' => $row['wins'],
                'losses' => $row['losses'],
            ];
        }

        return $records;
    }

    /**
     * Fetch all regular season games from ibl_box_scores_teams, cached for reuse
     *
     * Both getLongestStreak() and getBestWorstSeasonStart() need the same data.
     *
     * @return list<array{Date: string, visitorTeamID: int, homeTeamID: int, visitorScore: int, homeScore: int}>
     */
    private function getRegularSeasonGames(): array
    {
        if ($this->regularSeasonGamesCache !== null) {
            return $this->regularSeasonGamesCache;
        }

        $regularSeasonFilter = 'game_type = 1';

        /** @var list<array{Date: string, visitorTeamID: int, homeTeamID: int, visitorScore: int, homeScore: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT
                Date,
                visitorTeamID,
                homeTeamID,
                visitorScore,
                homeScore
            FROM vw_team_total_score
            WHERE {$regularSeasonFilter}
            GROUP BY Date, visitorTeamID, homeTeamID
            ORDER BY Date ASC"
        );

        $this->regularSeasonGamesCache = $rows;
        return $rows;
    }

    /**
     * Resolve team name from team ID, using pre-loaded cache
     */
    private function resolveTeamName(int $teamId): string
    {
        if ($this->teamNameCache === []) {
            /** @var list<array{teamid: int, team_name: string}> $rows */
            $rows = $this->fetchAll("SELECT teamid, team_name FROM ibl_team_info WHERE teamid BETWEEN 1 AND ?", 'i', \League::MAX_REAL_TEAMID);
            foreach ($rows as $row) {
                $this->teamNameCache[$row['teamid']] = $row['team_name'];
            }
        }

        return $this->teamNameCache[$teamId] ?? '';
    }

    /**
     * @see RecordHoldersRepositoryInterface::getLongestStreak()
     *
     * Processes all games sequentially to find the longest winning or losing streak.
     *
     * @return list<StreakRecord>
     */
    public function getLongestStreak(string $type): array
    {
        $rows = $this->getRegularSeasonGames();

        // Track streaks per team
        /** @var array<int, array{current: int, start: string, team: int}> $streaks */
        $streaks = [];
        /** @var array<int, array{streak: int, start: string, end: string}> $bestStreaks */
        $bestStreaks = [];

        foreach ($rows as $row) {
            /** @var array{Date: string, visitorTeamID: int, homeTeamID: int, visitorScore: int, homeScore: int} $row */
            $date = $row['Date'];
            $visitorTid = $row['visitorTeamID'];
            $homeTid = $row['homeTeamID'];
            $visitorScore = $row['visitorScore'];
            $homeScore = $row['homeScore'];

            $visitorWon = $visitorScore > $homeScore;

            foreach ([$visitorTid, $homeTid] as $tid) {
                $teamWon = ($tid === $visitorTid) ? $visitorWon : !$visitorWon;
                $isStreakType = ($type === 'winning') ? $teamWon : !$teamWon;

                if (!isset($streaks[$tid])) {
                    $streaks[$tid] = ['current' => 0, 'start' => '', 'team' => $tid];
                    $bestStreaks[$tid] = ['streak' => 0, 'start' => '', 'end' => ''];
                }

                if ($isStreakType) {
                    if ($streaks[$tid]['current'] === 0) {
                        $streaks[$tid]['start'] = $date;
                    }
                    $streaks[$tid]['current']++;
                    if ($streaks[$tid]['current'] > $bestStreaks[$tid]['streak']) {
                        $bestStreaks[$tid] = [
                            'streak' => $streaks[$tid]['current'],
                            'start' => $streaks[$tid]['start'],
                            'end' => $date,
                        ];
                    }
                } else {
                    $streaks[$tid]['current'] = 0;
                }
            }
        }

        // Find the overall best
        $maxStreak = 0;
        foreach ($bestStreaks as $data) {
            if ($data['streak'] > $maxStreak) {
                $maxStreak = $data['streak'];
            }
        }

        // Collect all teams matching the max streak
        /** @var list<StreakRecord> $records */
        $records = [];
        foreach ($bestStreaks as $tid => $data) {
            if ($data['streak'] === $maxStreak && $maxStreak > 0) {
                $startYear = $this->dateToSeasonEndingYear($data['start']);
                $endYear = $this->dateToSeasonEndingYear($data['end']);
                $records[] = [
                    'team_name' => $this->resolveTeamName($tid),
                    'streak' => $data['streak'],
                    'start_date' => $data['start'],
                    'end_date' => $data['end'],
                    'start_year' => $startYear,
                    'end_year' => $endYear,
                ];
            }
        }

        return $records;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getBestWorstSeasonStart()
     *
     * Finds the team with the best/worst record at the START of any season.
     * Best start = most consecutive wins from game 1; Worst start = most consecutive losses.
     *
     * @return list<SeasonStartRecord>
     */
    public function getBestWorstSeasonStart(string $type): array
    {
        $rows = $this->getRegularSeasonGames();

        // Track season starts per team per season
        /** @var array<string, array{wins: int, losses: int, streakBroken: bool}> $seasonStarts */
        $seasonStarts = [];

        foreach ($rows as $row) {
            /** @var array{Date: string, visitorTeamID: int, homeTeamID: int, visitorScore: int, homeScore: int} $row */
            $date = $row['Date'];
            $visitorTid = $row['visitorTeamID'];
            $homeTid = $row['homeTeamID'];
            $visitorScore = $row['visitorScore'];
            $homeScore = $row['homeScore'];
            $visitorWon = $visitorScore > $homeScore;
            $seasonYear = $this->dateToSeasonEndingYear($date);

            foreach ([$visitorTid, $homeTid] as $tid) {
                $key = $tid . '-' . $seasonYear;
                if (!isset($seasonStarts[$key])) {
                    $seasonStarts[$key] = ['wins' => 0, 'losses' => 0, 'streakBroken' => false];
                }

                if ($seasonStarts[$key]['streakBroken']) {
                    continue;
                }

                $teamWon = ($tid === $visitorTid) ? $visitorWon : !$visitorWon;

                if ($type === 'best') {
                    if ($teamWon) {
                        $seasonStarts[$key]['wins']++;
                    } else {
                        $seasonStarts[$key]['streakBroken'] = true;
                    }
                } else {
                    if (!$teamWon) {
                        $seasonStarts[$key]['losses']++;
                    } else {
                        $seasonStarts[$key]['streakBroken'] = true;
                    }
                }
            }
        }

        // Find the record-holding start
        $maxValue = 0;
        foreach ($seasonStarts as $data) {
            $value = $type === 'best' ? $data['wins'] : $data['losses'];
            if ($value > $maxValue) {
                $maxValue = $value;
            }
        }

        /** @var list<SeasonStartRecord> $records */
        $records = [];
        foreach ($seasonStarts as $key => $data) {
            $value = $type === 'best' ? $data['wins'] : $data['losses'];
            if ($value === $maxValue && $maxValue > 0) {
                [$tidStr, $yearStr] = explode('-', $key);
                $tid = (int) $tidStr;
                $year = (int) $yearStr;
                if ($type === 'best') {
                    $records[] = [
                        'team_name' => $this->resolveTeamName($tid),
                        'year' => $year,
                        'wins' => $data['wins'],
                        'losses' => 0,
                    ];
                } else {
                    $records[] = [
                        'team_name' => $this->resolveTeamName($tid),
                        'year' => $year,
                        'wins' => 0,
                        'losses' => $data['losses'],
                    ];
                }
            }
        }

        return $records;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getMostPlayoffAppearances()
     *
     * @return list<PlayoffAppearanceRecord>
     */
    public function getMostPlayoffAppearances(): array
    {
        $query = "SELECT
                t.team_name,
                COUNT(DISTINCT pr.year) AS count,
                GROUP_CONCAT(DISTINCT pr.year ORDER BY pr.year ASC SEPARATOR ', ') AS years
            FROM vw_playoff_series_results pr
            JOIN ibl_team_info t ON t.teamid = pr.winner_tid OR t.teamid = pr.loser_tid
            WHERE t.teamid BETWEEN 1 AND " . \League::MAX_REAL_TEAMID . "
            GROUP BY t.team_name
            ORDER BY count DESC, t.team_name ASC
            LIMIT 5";

        $rows = $this->fetchAll($query);

        // Find the max count to include ties
        $maxCount = 0;
        foreach ($rows as $countRow) {
            /** @var array{team_name: string, count: int, years: string} $countRow */
            if ($countRow['count'] > $maxCount) {
                $maxCount = $countRow['count'];
            }
        }

        /** @var list<PlayoffAppearanceRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, count: int, years: string} $row */
            if ($row['count'] === $maxCount) {
                $records[] = [
                    'team_name' => $row['team_name'],
                    'count' => $row['count'],
                    'years' => $row['years'],
                ];
            }
        }

        return $records;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getTopPlayerSingleGameBatch()
     *
     * @param array<string, string> $statExpressions
     * @return array<string, list<PlayerSingleGameRecord>>
     */
    public function getTopPlayerSingleGameBatch(array $statExpressions, string $dateFilter): array
    {
        if ($statExpressions === []) {
            return [];
        }

        $unions = [];
        foreach ($statExpressions as $label => $expression) {
            $safeLabel = str_replace("'", "''", $label);
            $unions[] = "(SELECT
                    '{$safeLabel}' AS stat_type,
                    bs.pid,
                    p.name,
                    h.teamid AS tid,
                    h.team AS team_name,
                    bs.Date AS `date`,
                    COALESCE(sch.BoxID, 0) AS BoxID,
                    COALESCE(bst.gameOfThatDay, 0) AS gameOfThatDay,
                    CASE WHEN h.teamid = bs.visitorTID THEN bs.homeTID ELSE bs.visitorTID END AS oppTid,
                    opp.team_name AS opp_team_name,
                    {$expression} AS value
                FROM ibl_box_scores bs
                JOIN ibl_plr p ON p.pid = bs.pid
                JOIN ibl_hist h ON h.pid = bs.pid AND h.year = ({$this->seasonYearExpression()})
                LEFT JOIN ibl_schedule sch ON sch.Date = bs.Date
                    AND sch.Visitor = bs.visitorTID AND sch.Home = bs.homeTID
                LEFT JOIN (
                    SELECT Date, visitorTeamID, homeTeamID, MIN(gameOfThatDay) AS gameOfThatDay
                    FROM ibl_box_scores_teams
                    GROUP BY Date, visitorTeamID, homeTeamID
                ) bst ON bst.Date = bs.Date AND bst.visitorTeamID = bs.visitorTID AND bst.homeTeamID = bs.homeTID
                LEFT JOIN ibl_team_info opp ON opp.teamid = CASE
                    WHEN h.teamid = bs.visitorTID THEN bs.homeTID
                    ELSE bs.visitorTID END
                WHERE {$dateFilter}
                ORDER BY value DESC, bs.Date ASC
                LIMIT 5)";
        }

        $query = implode("\nUNION ALL\n", $unions);
        $rows = $this->fetchAll($query);

        /** @var array<string, list<PlayerSingleGameRecord>> $results */
        $results = [];
        foreach (array_keys($statExpressions) as $label) {
            $results[$label] = [];
        }

        foreach ($rows as $row) {
            /** @var array{stat_type: string, pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int} $row */
            $label = $row['stat_type'];
            $results[$label][] = [
                'pid' => $row['pid'],
                'name' => $row['name'],
                'tid' => $row['tid'],
                'team_name' => $row['team_name'],
                'date' => $row['date'],
                'BoxID' => $row['BoxID'],
                'gameOfThatDay' => $row['gameOfThatDay'],
                'oppTid' => $row['oppTid'],
                'opp_team_name' => $row['opp_team_name'],
                'value' => $row['value'],
            ];
        }

        return $results;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getTopTeamSingleGameBatch()
     *
     * @param array<string, array{expression: string, order: string}> $statExpressions
     * @return array<string, list<TeamSingleGameRecord>>
     */
    public function getTopTeamSingleGameBatch(array $statExpressions, string $dateFilter): array
    {
        if ($statExpressions === []) {
            return [];
        }

        $unions = [];
        foreach ($statExpressions as $label => $config) {
            $safeLabel = str_replace("'", "''", $label);
            $safeOrder = $config['order'] === 'ASC' ? 'ASC' : 'DESC';
            $unions[] = "(SELECT
                    '{$safeLabel}' AS stat_type,
                    t.teamid AS tid,
                    t.team_name,
                    bs.Date AS `date`,
                    COALESCE(sch.BoxID, 0) AS BoxID,
                    COALESCE(bs.gameOfThatDay, 0) AS gameOfThatDay,
                    CASE WHEN t.teamid = bs.visitorTeamID THEN bs.homeTeamID ELSE bs.visitorTeamID END AS oppTid,
                    opp.team_name AS opp_team_name,
                    {$config['expression']} AS value
                FROM ibl_box_scores_teams bs
                JOIN ibl_team_info t ON t.team_name = bs.name
                LEFT JOIN ibl_schedule sch ON sch.Date = bs.Date
                    AND sch.Visitor = bs.visitorTeamID AND sch.Home = bs.homeTeamID
                LEFT JOIN ibl_team_info opp ON opp.teamid = CASE
                    WHEN t.teamid = bs.visitorTeamID THEN bs.homeTeamID
                    ELSE bs.visitorTeamID END
                WHERE {$dateFilter}
                ORDER BY value {$safeOrder}, bs.Date ASC
                LIMIT 5)";
        }

        $query = implode("\nUNION ALL\n", $unions);
        $rows = $this->fetchAll($query);

        /** @var array<string, list<TeamSingleGameRecord>> $results */
        $results = [];
        foreach (array_keys($statExpressions) as $label) {
            $results[$label] = [];
        }

        foreach ($rows as $row) {
            /** @var array{stat_type: string, tid: int, team_name: string, date: string, BoxID: int, gameOfThatDay: int, oppTid: int, opp_team_name: string, value: int} $row */
            $label = $row['stat_type'];
            $results[$label][] = [
                'tid' => $row['tid'],
                'team_name' => $row['team_name'],
                'date' => $row['date'],
                'BoxID' => $row['BoxID'],
                'gameOfThatDay' => $row['gameOfThatDay'],
                'oppTid' => $row['oppTid'],
                'opp_team_name' => $row['opp_team_name'],
                'value' => $row['value'],
            ];
        }

        return $results;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getTopSeasonAverageBatch()
     *
     * @param array<string, array{statColumn: string, gamesColumn: string}> $statColumns
     * @return array<string, list<PlayerSeasonRecord>>
     */
    public function getTopSeasonAverageBatch(array $statColumns, int $minGames = 50): array
    {
        if ($statColumns === []) {
            return [];
        }

        $unions = [];
        foreach ($statColumns as $label => $columns) {
            $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $columns['statColumn']);
            $safeGames = preg_replace('/[^a-zA-Z0-9_]/', '', $columns['gamesColumn']);
            if ($safeColumn === null || $safeColumn === '' || $safeGames === null || $safeGames === '') {
                continue;
            }
            $safeLabel = str_replace("'", "''", $label);
            $unions[] = "(SELECT
                    '{$safeLabel}' AS stat_type,
                    h.pid,
                    h.name,
                    h.teamid,
                    h.team,
                    h.year,
                    ROUND(h.{$safeColumn} / h.{$safeGames}, 1) AS value
                FROM ibl_hist h
                WHERE h.{$safeGames} >= {$minGames}
                ORDER BY value DESC
                LIMIT 5)";
        }

        if ($unions === []) {
            return [];
        }

        $query = implode("\nUNION ALL\n", $unions);
        $rows = $this->fetchAll($query);

        /** @var array<string, list<PlayerSeasonRecord>> $results */
        $results = [];
        foreach (array_keys($statColumns) as $label) {
            $results[$label] = [];
        }

        foreach ($rows as $row) {
            /** @var array{stat_type: string, pid: int, name: string, teamid: int, team: string, year: int, value: float} $row */
            $label = $row['stat_type'];
            $results[$label][] = [
                'pid' => $row['pid'],
                'name' => $row['name'],
                'teamid' => $row['teamid'],
                'team' => $row['team'],
                'year' => $row['year'],
                'value' => $row['value'],
            ];
        }

        return $results;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getMostTitlesByType()
     *
     * @return list<FranchiseTitleRecord>
     */
    public function getMostTitlesByType(string $titlePattern): array
    {
        $query = "SELECT
                name AS team_name,
                COUNT(*) AS count,
                GROUP_CONCAT(year ORDER BY year ASC SEPARATOR ', ') AS years
            FROM vw_team_awards
            WHERE Award LIKE ?
            GROUP BY name
            ORDER BY count DESC, name ASC
            LIMIT 5";

        $rows = $this->fetchAll($query, 's', '%' . $titlePattern . '%');

        // Find the max count to include ties
        $maxCount = 0;
        foreach ($rows as $countRow) {
            /** @var array{team_name: string, count: int, years: string} $countRow */
            if ($countRow['count'] > $maxCount) {
                $maxCount = $countRow['count'];
            }
        }

        /** @var list<FranchiseTitleRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, count: int, years: string} $row */
            if ($row['count'] === $maxCount) {
                $records[] = [
                    'team_name' => $row['team_name'],
                    'count' => $row['count'],
                    'years' => $row['years'],
                ];
            }
        }

        return $records;
    }

    /**
     * @see RecordHoldersRepositoryInterface::getLastAnnouncedDate()
     */
    public function getLastAnnouncedDate(): ?string
    {
        $row = $this->fetchOne(
            "SELECT `value` FROM `cache` WHERE `key` = ?",
            's',
            self::ANNOUNCEMENT_CACHE_KEY
        );

        if ($row === null) {
            return null;
        }

        /** @var array{value: string} $row */
        return $row['value'];
    }

    /**
     * @see RecordHoldersRepositoryInterface::markAnnouncementsProcessed()
     */
    public function markAnnouncementsProcessed(string $gameDate): void
    {
        $this->execute(
            "REPLACE INTO `cache` (`key`, `value`, `expiration`) VALUES (?, ?, 0)",
            'ss',
            self::ANNOUNCEMENT_CACHE_KEY,
            $gameDate
        );
    }

    /**
     * @see RecordHoldersRepositoryInterface::getUnannouncedGameDates()
     *
     * @return list<string>
     */
    public function getUnannouncedGameDates(?string $lastAnnouncedDate): array
    {
        // Get the latest sim's date range from ibl_sim_dates
        /** @var array{start_date: string, end_date: string}|null $latestSim */
        $latestSim = $this->fetchOne(
            "SELECT `Start Date` AS start_date, `End Date` AS end_date FROM ibl_sim_dates ORDER BY Sim DESC LIMIT 1"
        );

        if ($latestSim === null) {
            return [];
        }

        $simStart = $latestSim['start_date'];
        $simEnd = $latestSim['end_date'];

        // If the last announced date is at or after the sim end, everything is already processed
        if ($lastAnnouncedDate !== null && $lastAnnouncedDate >= $simEnd) {
            return [];
        }

        // Use the later of sim start or (lastAnnouncedDate + 1 day) as the floor
        $floor = $simStart;
        if ($lastAnnouncedDate !== null && $lastAnnouncedDate >= $simStart) {
            $floor = $lastAnnouncedDate;
        }

        /** @var list<array{Date: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT DISTINCT Date FROM ibl_box_scores WHERE Date > ? AND Date <= ? ORDER BY Date ASC",
            'ss',
            $floor,
            $simEnd
        );

        return array_map(static fn(array $row): string => $row['Date'], $rows);
    }

    /**
     * Get the season ending year from a date string.
     *
     * Oct-Dec: season ends next year. Jan-Jun: season ends this year.
     */
    private function dateToSeasonEndingYear(string $date): int
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return 0;
        }
        $month = (int) date('n', $timestamp);
        $year = (int) date('Y', $timestamp);

        return $month >= 10 ? $year + 1 : $year;
    }

    /**
     * SQL expression to derive season ending year from box score date.
     */
    private function seasonYearExpression(): string
    {
        return self::SEASON_YEAR_EXPRESSION;
    }
}
