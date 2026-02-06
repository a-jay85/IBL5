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
    /**
     * Season ending year derivation from game date.
     *
     * IBL seasons span two calendar years (Nov-May). HEAT is in October.
     * Games in Oct-Dec belong to a season ending the NEXT calendar year.
     * Games in Jan-Jun belong to a season ending the SAME calendar year.
     */
    private const SEASON_YEAR_EXPRESSION = 'CASE WHEN MONTH(bs.Date) >= 10 THEN YEAR(bs.Date) + 1 ELSE YEAR(bs.Date) END';

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
                CASE WHEN h.teamid = bs.visitorTID THEN bs.homeTID ELSE bs.visitorTID END AS oppTid,
                opp.team_name AS opp_team_name,
                {$statExpression} AS value
            FROM ibl_box_scores bs
            JOIN ibl_plr p ON p.pid = bs.pid
            JOIN ibl_hist h ON h.pid = bs.pid AND h.year = ({$this->seasonYearExpression()})
            LEFT JOIN ibl_schedule sch ON sch.Date = bs.Date
                AND sch.Visitor = bs.visitorTID AND sch.Home = bs.homeTID
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
            /** @var array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, oppTid: int, opp_team_name: string, value: int} $row */
            $records[] = [
                'pid' => $row['pid'],
                'name' => $row['name'],
                'tid' => $row['tid'],
                'team_name' => $row['team_name'],
                'date' => $row['date'],
                'BoxID' => $row['BoxID'],
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
        $points = '(bs.game2GM * 2 + bs.gameFTM + bs.game3GM * 3)';
        $rebounds = '(bs.gameORB + bs.gameDRB)';
        $assists = 'bs.gameAST';
        $steals = 'bs.gameSTL';
        $blocks = 'bs.gameBLK';

        $query = "SELECT
                bs.pid,
                p.name,
                h.teamid AS tid,
                h.team AS team_name,
                bs.Date AS `date`,
                COALESCE(sch.BoxID, 0) AS BoxID,
                CASE WHEN h.teamid = bs.visitorTID THEN bs.homeTID ELSE bs.visitorTID END AS oppTid,
                opp.team_name AS opp_team_name,
                {$points} AS points,
                {$rebounds} AS rebounds,
                {$assists} AS assists,
                {$steals} AS steals,
                {$blocks} AS blocks
            FROM ibl_box_scores bs
            JOIN ibl_plr p ON p.pid = bs.pid
            JOIN ibl_hist h ON h.pid = bs.pid AND h.year = ({$this->seasonYearExpression()})
            LEFT JOIN ibl_schedule sch ON sch.Date = bs.Date
                AND sch.Visitor = bs.visitorTID AND sch.Home = bs.homeTID
            LEFT JOIN ibl_team_info opp ON opp.teamid = CASE
                WHEN h.teamid = bs.visitorTID THEN bs.homeTID
                ELSE bs.visitorTID END
            WHERE (
                (CASE WHEN {$points} >= 10 THEN 1 ELSE 0 END)
                + (CASE WHEN {$rebounds} >= 10 THEN 1 ELSE 0 END)
                + (CASE WHEN {$assists} >= 10 THEN 1 ELSE 0 END)
                + (CASE WHEN {$steals} >= 10 THEN 1 ELSE 0 END)
                + (CASE WHEN {$blocks} >= 10 THEN 1 ELSE 0 END)
            ) >= 4
            ORDER BY bs.Date ASC";

        $rows = $this->fetchAll($query);

        /** @var list<QuadrupleDoubleRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{pid: int, name: string, tid: int, team_name: string, date: string, BoxID: int, oppTid: int, opp_team_name: string, points: int, rebounds: int, assists: int, steals: int, blocks: int} $row */
            $records[] = [
                'pid' => $row['pid'],
                'name' => $row['name'],
                'tid' => $row['tid'],
                'team_name' => $row['team_name'],
                'date' => $row['date'],
                'BoxID' => $row['BoxID'],
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
            /** @var array{tid: int, team_name: string, date: string, BoxID: int, oppTid: int, opp_team_name: string, value: int} $row */
            $records[] = [
                'tid' => $row['tid'],
                'team_name' => $row['team_name'],
                'date' => $row['date'],
                'BoxID' => $row['BoxID'],
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
            /** @var array{tid: int, team_name: string, date: string, BoxID: int, oppTid: int, opp_team_name: string, value: int} $row */
            $records[] = [
                'tid' => $row['tid'],
                'team_name' => $row['team_name'],
                'date' => $row['date'],
                'BoxID' => $row['BoxID'],
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
     * @return list<MarginRecord>
     */
    public function getLargestMarginOfVictory(string $dateFilter): array
    {
        // Use ibl_schedule for scores since it has VScore and HScore
        // For older data that's only in box_scores_teams, compute from quarter scores
        $query = "SELECT
                winner_t.teamid AS winner_tid,
                winner_t.team_name AS winner_name,
                loser_t.teamid AS loser_tid,
                loser_t.team_name AS loser_name,
                sub.Date AS `date`,
                COALESCE(sch.BoxID, 0) AS BoxID,
                sub.margin
            FROM (
                SELECT
                    bs.Date,
                    bs.visitorTeamID,
                    bs.homeTeamID,
                    (CASE WHEN bs.visitorTeamID = bs2.visitorTeamID THEN bs.name ELSE '' END) AS v_name,
                    ABS(
                        (bs.visitorQ1points + bs.visitorQ2points + bs.visitorQ3points + bs.visitorQ4points + COALESCE(bs.visitorOTpoints, 0))
                        - (bs.homeQ1points + bs.homeQ2points + bs.homeQ3points + bs.homeQ4points + COALESCE(bs.homeOTpoints, 0))
                    ) AS margin,
                    CASE WHEN (bs.visitorQ1points + bs.visitorQ2points + bs.visitorQ3points + bs.visitorQ4points + COALESCE(bs.visitorOTpoints, 0))
                        > (bs.homeQ1points + bs.homeQ2points + bs.homeQ3points + bs.homeQ4points + COALESCE(bs.homeOTpoints, 0))
                        THEN bs.visitorTeamID ELSE bs.homeTeamID END AS winner_id,
                    CASE WHEN (bs.visitorQ1points + bs.visitorQ2points + bs.visitorQ3points + bs.visitorQ4points + COALESCE(bs.visitorOTpoints, 0))
                        > (bs.homeQ1points + bs.homeQ2points + bs.homeQ3points + bs.homeQ4points + COALESCE(bs.homeOTpoints, 0))
                        THEN bs.homeTeamID ELSE bs.visitorTeamID END AS loser_id
                FROM ibl_box_scores_teams bs
                LEFT JOIN ibl_box_scores_teams bs2 ON bs2.Date = bs.Date
                    AND bs2.visitorTeamID = bs.visitorTeamID AND bs2.homeTeamID = bs.homeTeamID
                    AND bs2.name != bs.name
                WHERE {$dateFilter}
                GROUP BY bs.Date, bs.visitorTeamID, bs.homeTeamID
            ) sub
            JOIN ibl_team_info winner_t ON winner_t.teamid = sub.winner_id
            JOIN ibl_team_info loser_t ON loser_t.teamid = sub.loser_id
            LEFT JOIN ibl_schedule sch ON sch.Date = sub.Date
                AND sch.Visitor = sub.visitorTeamID AND sch.Home = sub.homeTeamID
            ORDER BY sub.margin DESC, sub.Date ASC
            LIMIT 5";

        $rows = $this->fetchAll($query);

        /** @var list<MarginRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{winner_tid: int, winner_name: string, loser_tid: int, loser_name: string, date: string, BoxID: int, margin: int} $row */
            $records[] = [
                'winner_tid' => $row['winner_tid'],
                'winner_name' => $row['winner_name'],
                'loser_tid' => $row['loser_tid'],
                'loser_name' => $row['loser_name'],
                'date' => $row['date'],
                'BoxID' => $row['BoxID'],
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
                CAST(wins AS UNSIGNED) AS wins,
                CAST(losses AS UNSIGNED) AS losses
            FROM ibl_team_win_loss
            WHERE currentname != 'Free Agents'
                AND (CAST(wins AS UNSIGNED) + CAST(losses AS UNSIGNED)) > 0
            ORDER BY (CAST(wins AS UNSIGNED) / (CAST(wins AS UNSIGNED) + CAST(losses AS UNSIGNED))) {$safeOrder},
                CAST(wins AS UNSIGNED) {$safeOrder}
            LIMIT 5";

        $rows = $this->fetchAll($query);

        /** @var list<SeasonWinLossRecord> $records */
        $records = [];
        foreach ($rows as $row) {
            /** @var array{team_name: string, year: string, wins: int, losses: int} $row */
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

        $regularSeasonFilter = 'MONTH(Date) IN (11, 12, 1, 2, 3, 4, 5)';

        /** @var list<array{Date: string, visitorTeamID: int, homeTeamID: int, visitorScore: int, homeScore: int}> $rows */
        $rows = $this->fetchAll(
            "SELECT
                Date,
                visitorTeamID,
                homeTeamID,
                (visitorQ1points + visitorQ2points + visitorQ3points + visitorQ4points + COALESCE(visitorOTpoints, 0)) AS visitorScore,
                (homeQ1points + homeQ2points + homeQ3points + homeQ4points + COALESCE(homeOTpoints, 0)) AS homeScore
            FROM ibl_box_scores_teams
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
            $rows = $this->fetchAll("SELECT teamid, team_name FROM ibl_team_info", '');
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
            FROM ibl_playoff_results pr
            JOIN ibl_team_info t ON t.team_name = pr.winner OR t.team_name = pr.loser
            WHERE t.teamid != 0
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
            FROM ibl_team_awards
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
