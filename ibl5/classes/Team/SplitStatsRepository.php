<?php

declare(strict_types=1);

namespace Team;

use Team\Contracts\SplitStatsRepositoryInterface;

/**
 * SplitStatsRepository - Queries per-game averages filtered by game context
 *
 * Reuses the same SQL aggregation pattern as PeriodAverages (SUM/COUNT grouped by player)
 * with additional WHERE clauses or JOINs based on the split type.
 *
 * @phpstan-import-type SplitStatsRow from Contracts\SplitStatsRepositoryInterface
 *
 * @see SplitStatsRepositoryInterface
 */
class SplitStatsRepository extends \BaseMysqliRepository implements SplitStatsRepositoryInterface
{
    /**
     * Map of split keys to human-readable labels.
     * Dynamic keys (month_N, div_X, conf_X, vs_N) are resolved at runtime.
     *
     * @var array<string, string>
     */
    private const STATIC_LABELS = [
        'home' => 'Home',
        'road' => 'Road',
        'wins' => 'Wins',
        'losses' => 'Losses',
        'pre_allstar' => 'Pre All-Star',
        'post_allstar' => 'Post All-Star',
    ];

    /** @var array<int, string> Month number to display name */
    private const MONTH_NAMES = [
        11 => 'November',
        12 => 'December',
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
    ];

    /**
     * @see SplitStatsRepositoryInterface::getSplitStats()
     * @return list<SplitStatsRow>
     */
    public function getSplitStats(int $teamID, int $seasonEndingYear, string $splitKey): array
    {
        $splitCondition = $this->buildSplitCondition($splitKey, $teamID, $seasonEndingYear);

        $query = "SELECT p.name,
            bs.pos,
            bs.pid,
            COUNT(DISTINCT bs.`Date`) as games,
            ROUND(SUM(bs.gameMIN)/COUNT(DISTINCT bs.`Date`), 1) as gameMINavg,
            ROUND(SUM(bs.game2GM + bs.game3GM)/COUNT(DISTINCT bs.`Date`), 2) as gameFGMavg,
            ROUND(SUM(bs.game2GA + bs.game3GA)/COUNT(DISTINCT bs.`Date`), 2) as gameFGAavg,
            ROUND((SUM(bs.game2GM) + SUM(bs.game3GM)) / NULLIF(SUM(bs.game2GA) + SUM(bs.game3GA), 0), 3) as gameFGPavg,
            ROUND(SUM(bs.gameFTM)/COUNT(DISTINCT bs.`Date`), 2) as gameFTMavg,
            ROUND(SUM(bs.gameFTA)/COUNT(DISTINCT bs.`Date`), 2) as gameFTAavg,
            ROUND(SUM(bs.gameFTM) / NULLIF(SUM(bs.gameFTA), 0), 3) as gameFTPavg,
            ROUND(SUM(bs.game3GM)/COUNT(DISTINCT bs.`Date`), 2) as game3GMavg,
            ROUND(SUM(bs.game3GA)/COUNT(DISTINCT bs.`Date`), 2) as game3GAavg,
            ROUND(SUM(bs.game3GM) / NULLIF(SUM(bs.game3GA), 0), 3) as game3GPavg,
            ROUND(SUM(bs.gameORB)/COUNT(DISTINCT bs.`Date`), 1) as gameORBavg,
            ROUND((SUM(bs.gameORB) + SUM(bs.gameDRB))/COUNT(DISTINCT bs.`Date`), 1) as gameREBavg,
            ROUND(SUM(bs.gameAST)/COUNT(DISTINCT bs.`Date`), 1) as gameASTavg,
            ROUND(SUM(bs.gameSTL)/COUNT(DISTINCT bs.`Date`), 1) as gameSTLavg,
            ROUND(SUM(bs.gameTOV)/COUNT(DISTINCT bs.`Date`), 1) as gameTOVavg,
            ROUND(SUM(bs.gameBLK)/COUNT(DISTINCT bs.`Date`), 1) as gameBLKavg,
            ROUND(SUM(bs.gamePF)/COUNT(DISTINCT bs.`Date`), 1) as gamePFavg,
            ROUND(((2 * SUM(bs.game2GM)) + SUM(bs.gameFTM) + (3 * SUM(bs.game3GM)))/COUNT(DISTINCT bs.`Date`), 1) as gamePTSavg
        FROM ibl_box_scores bs
        JOIN ibl_plr p ON bs.pid = p.pid
        " . $splitCondition['joins'] . "
        WHERE bs.season_year = ?
            AND (bs.homeTID = ? OR bs.visitorTID = ?)
            AND bs.gameMIN > 0
            AND p.tid = ?
            AND p.retired = 0
            AND p.name NOT LIKE '%|%'
            AND bs.game_type = 1
            " . $splitCondition['where'] . "
        GROUP BY p.name, bs.pos, bs.pid
        ORDER BY p.name ASC";

        $types = 'iiii' . $splitCondition['types'];
        $params = array_merge(
            [$seasonEndingYear, $teamID, $teamID, $teamID],
            $splitCondition['params']
        );

        /** @var list<SplitStatsRow> */
        return $this->fetchAll($query, $types, ...$params);
    }

    /**
     * @see SplitStatsRepositoryInterface::getValidSplitKeys()
     * @return list<string>
     */
    public function getValidSplitKeys(): array
    {
        $keys = ['home', 'road', 'wins', 'losses', 'pre_allstar', 'post_allstar'];

        foreach (array_keys(self::MONTH_NAMES) as $month) {
            $keys[] = 'month_' . $month;
        }

        foreach (\League::DIVISION_NAMES as $division) {
            $keys[] = 'div_' . strtolower($division);
        }

        foreach (\League::CONFERENCE_NAMES as $conference) {
            $keys[] = 'conf_' . strtolower($conference);
        }

        for ($tid = 1; $tid <= \League::MAX_REAL_TEAMID; $tid++) {
            $keys[] = 'vs_' . $tid;
        }

        return $keys;
    }

    /**
     * @see SplitStatsRepositoryInterface::getSplitLabel()
     */
    public function getSplitLabel(string $splitKey): string
    {
        if (isset(self::STATIC_LABELS[$splitKey])) {
            return self::STATIC_LABELS[$splitKey];
        }

        // month_N
        if (str_starts_with($splitKey, 'month_')) {
            $month = (int) substr($splitKey, 6);
            return self::MONTH_NAMES[$month] ?? 'Unknown Month';
        }

        // div_X
        if (str_starts_with($splitKey, 'div_')) {
            $divKey = substr($splitKey, 4);
            foreach (\League::DIVISION_NAMES as $division) {
                if (strtolower($division) === $divKey) {
                    return 'vs. ' . $division;
                }
            }
            return 'vs. Unknown Division';
        }

        // conf_X
        if (str_starts_with($splitKey, 'conf_')) {
            $confKey = substr($splitKey, 5);
            foreach (\League::CONFERENCE_NAMES as $conference) {
                if (strtolower($conference) === $confKey) {
                    return 'vs. ' . $conference;
                }
            }
            return 'vs. Unknown Conference';
        }

        // vs_N
        if (str_starts_with($splitKey, 'vs_')) {
            $opponentTid = (int) substr($splitKey, 3);
            $row = $this->fetchOne(
                "SELECT team_name FROM ibl_team_info WHERE teamid = ?",
                "i",
                $opponentTid
            );
            if (is_array($row) && isset($row['team_name']) && is_string($row['team_name'])) {
                return 'vs. ' . $row['team_name'];
            }
            return 'vs. Team #' . $opponentTid;
        }

        return $splitKey;
    }

    /**
     * Build the WHERE additions, extra JOINs, types, and params for a split condition
     *
     * @return array{joins: string, where: string, types: string, params: list<mixed>}
     */
    private function buildSplitCondition(string $splitKey, int $teamID, int $seasonEndingYear): array
    {
        $result = ['joins' => '', 'where' => '', 'types' => '', 'params' => []];

        // Location splits
        if ($splitKey === 'home') {
            $result['where'] = 'AND bs.homeTID = ?';
            $result['types'] = 'i';
            $result['params'] = [$teamID];
            return $result;
        }

        if ($splitKey === 'road') {
            $result['where'] = 'AND bs.visitorTID = ?';
            $result['types'] = 'i';
            $result['params'] = [$teamID];
            return $result;
        }

        // Result splits
        if ($splitKey === 'wins') {
            $result['joins'] = 'JOIN ibl_schedule sch ON bs.`Date` = sch.`Date` AND ((sch.Home = bs.homeTID AND sch.Visitor = bs.visitorTID) OR (sch.Home = bs.visitorTID AND sch.Visitor = bs.homeTID))';
            $result['where'] = 'AND ((sch.Home = ? AND sch.HScore > sch.VScore) OR (sch.Visitor = ? AND sch.VScore > sch.HScore))';
            $result['types'] = 'ii';
            $result['params'] = [$teamID, $teamID];
            return $result;
        }

        if ($splitKey === 'losses') {
            $result['joins'] = 'JOIN ibl_schedule sch ON bs.`Date` = sch.`Date` AND ((sch.Home = bs.homeTID AND sch.Visitor = bs.visitorTID) OR (sch.Home = bs.visitorTID AND sch.Visitor = bs.homeTID))';
            $result['where'] = 'AND ((sch.Home = ? AND sch.HScore < sch.VScore) OR (sch.Visitor = ? AND sch.VScore < sch.HScore))';
            $result['types'] = 'ii';
            $result['params'] = [$teamID, $teamID];
            return $result;
        }

        // Season half splits
        if ($splitKey === 'pre_allstar') {
            $cutoffDate = sprintf('%d-%02d-%02d', $seasonEndingYear, \Season::IBL_ALL_STAR_MONTH, \Season::IBL_ALL_STAR_BREAK_START_DAY);
            $result['where'] = 'AND bs.`Date` < ?';
            $result['types'] = 's';
            $result['params'] = [$cutoffDate];
            return $result;
        }

        if ($splitKey === 'post_allstar') {
            $cutoffDate = sprintf('%d-%02d-%02d', $seasonEndingYear, \Season::IBL_ALL_STAR_MONTH, \Season::IBL_POST_ALL_STAR_FIRST_DAY);
            $result['where'] = 'AND bs.`Date` >= ?';
            $result['types'] = 's';
            $result['params'] = [$cutoffDate];
            return $result;
        }

        // Month splits
        if (str_starts_with($splitKey, 'month_')) {
            $month = (int) substr($splitKey, 6);
            $result['where'] = 'AND MONTH(bs.`Date`) = ?';
            $result['types'] = 'i';
            $result['params'] = [$month];
            return $result;
        }

        // Division splits
        if (str_starts_with($splitKey, 'div_')) {
            $divKey = substr($splitKey, 4);
            $divisionName = '';
            foreach (\League::DIVISION_NAMES as $division) {
                if (strtolower($division) === $divKey) {
                    $divisionName = $division;
                    break;
                }
            }
            $result['joins'] = 'JOIN ibl_standings opp_s ON opp_s.tid = CASE WHEN bs.homeTID = ' . $teamID . ' THEN bs.visitorTID ELSE bs.homeTID END';
            $result['where'] = 'AND opp_s.division = ?';
            $result['types'] = 's';
            $result['params'] = [$divisionName];
            return $result;
        }

        // Conference splits
        if (str_starts_with($splitKey, 'conf_')) {
            $confKey = substr($splitKey, 5);
            $conferenceName = '';
            foreach (\League::CONFERENCE_NAMES as $conference) {
                if (strtolower($conference) === $confKey) {
                    $conferenceName = $conference;
                    break;
                }
            }
            $result['joins'] = 'JOIN ibl_standings opp_s ON opp_s.tid = CASE WHEN bs.homeTID = ' . $teamID . ' THEN bs.visitorTID ELSE bs.homeTID END';
            $result['where'] = 'AND opp_s.conference = ?';
            $result['types'] = 's';
            $result['params'] = [$conferenceName];
            return $result;
        }

        // vs. Team splits
        if (str_starts_with($splitKey, 'vs_')) {
            $opponentTid = (int) substr($splitKey, 3);
            $result['where'] = 'AND CASE WHEN bs.homeTID = ? THEN bs.visitorTID ELSE bs.homeTID END = ?';
            $result['types'] = 'ii';
            $result['params'] = [$teamID, $opponentTid];
            return $result;
        }

        return $result;
    }
}
