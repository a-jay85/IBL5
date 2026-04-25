<?php

declare(strict_types=1);

namespace Player;

use BaseMysqliRepository;
use Player\Contracts\PlayerStatsRepositoryInterface;

/**
 * PlayerStatsRepository - Database operations for player statistics
 *
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * Provides access to current season stats, historical stats, box scores,
 * and specialized stats (playoffs, HEAT, Olympics).
 *
 * @see PlayerStatsRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation
 *
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 * @phpstan-import-type StatsRow from \Player\Contracts\PlayerStatsRepositoryInterface
 * @phpstan-import-type CareerTotalsRow from \Player\Contracts\PlayerStatsRepositoryInterface
 * @phpstan-import-type CareerAveragesRow from \Player\Contracts\PlayerStatsRepositoryInterface
 */
class PlayerStatsRepository extends BaseMysqliRepository implements PlayerStatsRepositoryInterface
{
    /**
     * @see PlayerStatsRepositoryInterface::getPlayerStats()
     * @return PlayerRow|null
     */
    public function getPlayerStats(int $playerID): ?array
    {
        /** @var PlayerRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_plr WHERE pid = ? LIMIT 1",
            "i",
            $playerID
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getHistoricalStats()
     * @return list<StatsRow>
     */
    public function getHistoricalStats(int $playerID): array
    {
        /** @var list<StatsRow> */
        return $this->fetchAll(
            "SELECT * FROM ibl_hist WHERE pid = ? ORDER BY year ASC",
            "i",
            $playerID
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getBoxScoresBetweenDates()
     */
    public function getBoxScoresBetweenDates(int $playerID, string $startDate, string $endDate): array
    {
        return $this->fetchAll(
            "SELECT bs.*,
                    COALESCE(bst.gameOfThatDay, 0) AS gameOfThatDay,
                    COALESCE(sch.BoxID, 0) AS BoxID
             FROM ibl_box_scores bs
             LEFT JOIN (
                 SELECT Date, visitor_teamid, home_teamid, MIN(gameOfThatDay) AS gameOfThatDay
                 FROM ibl_box_scores_teams
                 GROUP BY Date, visitor_teamid, home_teamid
             ) bst ON bst.Date = bs.Date AND bst.visitor_teamid = bs.visitor_teamid AND bst.home_teamid = bs.home_teamid
             LEFT JOIN ibl_schedule sch ON sch.Date = bs.Date AND sch.Visitor = bs.visitor_teamid AND sch.Home = bs.home_teamid
             WHERE bs.pid = ? AND bs.Date BETWEEN ? AND ?
             ORDER BY bs.Date ASC",
            "iss",
            $playerID,
            $startDate,
            $endDate
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getSimDates()
     */
    public function getSimDates(int $limit = 20): array
    {
        return $this->fetchAll(
            "SELECT sim, start_date, end_date FROM ibl_sim_dates ORDER BY sim DESC LIMIT ?",
            "i",
            $limit
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getPlayoffStats()
     * @return list<array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int}>
     */
    public function getPlayoffStats(string $playerName): array
    {
        /** @var list<array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int}> */
        return $this->fetchAll(
            self::buildPerSeasonStatsQuery(2),
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getPlayoffCareerTotals()
     * @return CareerTotalsRow|null
     */
    public function getPlayoffCareerTotals(string $playerName): ?array
    {
        /** @var CareerTotalsRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_playoff_career_totals WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getPlayoffCareerAverages()
     * @return CareerAveragesRow|null
     */
    public function getPlayoffCareerAverages(string $playerName): ?array
    {
        /** @var CareerAveragesRow|null */
        return $this->fetchOne(
            self::buildCareerAveragesQuery(2, 'p.name = ?'),
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getHeatStats()
     * @return list<array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int}>
     */
    public function getHeatStats(string $playerName): array
    {
        /** @var list<array{year: int, pos: string, pid: int, name: string, team: string, games: int, minutes: int, fgm: int, fga: int, ftm: int, fta: int, tgm: int, tga: int, orb: int, reb: int, ast: int, stl: int, tvr: int, blk: int, pf: int, pts: int}> */
        return $this->fetchAll(
            self::buildPerSeasonStatsQuery(3),
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getHeatCareerTotals()
     * @return CareerTotalsRow|null
     */
    public function getHeatCareerTotals(string $playerName): ?array
    {
        /** @var CareerTotalsRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_heat_career_totals WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getHeatCareerAverages()
     * @return CareerAveragesRow|null
     */
    public function getHeatCareerAverages(string $playerName): ?array
    {
        /** @var CareerAveragesRow|null */
        return $this->fetchOne(
            self::buildCareerAveragesQuery(3, 'p.name = ?'),
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getOlympicsStats()
     */
    public function getOlympicsStats(int $playerID): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_olympics_stats WHERE pid = ? ORDER BY year ASC",
            "i",
            $playerID
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getOlympicsCareerTotals()
     * @return CareerTotalsRow|null
     */
    public function getOlympicsCareerTotals(int $playerID): ?array
    {
        /** @var CareerTotalsRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_olympics_career_totals WHERE pid = ?",
            "i",
            $playerID
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getOlympicsCareerAverages()
     * @return CareerAveragesRow|null
     */
    public function getOlympicsCareerAverages(int $playerID): ?array
    {
        /** @var CareerAveragesRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_olympics_career_avgs WHERE pid = ?",
            "i",
            $playerID
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getSeasonCareerAverages()
     * @return CareerAveragesRow|null
     */
    public function getSeasonCareerAverages(string $playerName): ?array
    {
        /** @var CareerAveragesRow|null */
        return $this->fetchOne(
            self::buildCareerAveragesQuery(1, 'p.name = ?'),
            "s",
            $playerName
        ) ?? $this->fetchOne(
            self::buildHistCareerAveragesQuery('p.name = ?'),
            "s",
            $playerName
        );
    }

    /**
     * @see PlayerStatsRepositoryInterface::getSeasonCareerAveragesById()
     * @return CareerAveragesRow|null
     */
    public function getSeasonCareerAveragesById(int $playerID): ?array
    {
        /** @var CareerAveragesRow|null */
        return $this->fetchOne(
            self::buildCareerAveragesQuery(1, 'bs.pid = ?'),
            "i",
            $playerID
        ) ?? $this->fetchOne(
            self::buildHistCareerAveragesQuery('h.pid = ?'),
            "i",
            $playerID
        );
    }

    /**
     * Build regular-season career averages from ibl_hist (pre-aggregated per-season totals).
     *
     * Much faster than aggregating 589K ibl_box_scores rows — ibl_hist has ~12K rows.
     */
    private static function buildHistCareerAveragesQuery(string $filterClause): string
    {
        return "SELECT h.pid, p.name,
            CAST(SUM(h.games) AS SIGNED) AS games,
            ROUND(SUM(h.minutes) / SUM(h.games), 2) AS minutes,
            ROUND(SUM(h.fgm) / SUM(h.games), 2) AS fgm,
            ROUND(SUM(h.fga) / SUM(h.games), 2) AS fga,
            CASE WHEN SUM(h.fga) > 0
                THEN ROUND(SUM(h.fgm) / SUM(h.fga), 3)
                ELSE 0.000 END AS fgpct,
            ROUND(SUM(h.ftm) / SUM(h.games), 2) AS ftm,
            ROUND(SUM(h.fta) / SUM(h.games), 2) AS fta,
            CASE WHEN SUM(h.fta) > 0
                THEN ROUND(SUM(h.ftm) / SUM(h.fta), 3)
                ELSE 0.000 END AS ftpct,
            ROUND(SUM(h.tgm) / SUM(h.games), 2) AS tgm,
            ROUND(SUM(h.tga) / SUM(h.games), 2) AS tga,
            CASE WHEN SUM(h.tga) > 0
                THEN ROUND(SUM(h.tgm) / SUM(h.tga), 3)
                ELSE 0.000 END AS tpct,
            ROUND(SUM(h.orb) / SUM(h.games), 2) AS orb,
            ROUND(SUM(h.reb) / SUM(h.games), 2) AS reb,
            ROUND(SUM(h.ast) / SUM(h.games), 2) AS ast,
            ROUND(SUM(h.stl) / SUM(h.games), 2) AS stl,
            ROUND(SUM(h.tvr) / SUM(h.games), 2) AS tvr,
            ROUND(SUM(h.blk) / SUM(h.games), 2) AS blk,
            ROUND(SUM(h.pf)  / SUM(h.games), 2) AS pf,
            ROUND(SUM(h.pts) / SUM(h.games), 2) AS pts,
            p.retired
        FROM ibl_hist h
        JOIN ibl_plr p ON h.pid = p.pid
        WHERE h.games > 0 AND {$filterClause}
        GROUP BY h.pid, p.name, p.retired";
    }

    /**
     * Build career averages from ibl_box_scores for playoff/HEAT game types.
     *
     * Regular season career averages use buildHistCareerAveragesQuery() instead.
     */
    private static function buildCareerAveragesQuery(int $gameType, string $filterClause): string
    {
        return "SELECT bs.pid, p.name,
            CAST(COUNT(*) AS SIGNED) AS games,
            ROUND(AVG(bs.gameMIN), 2) AS minutes,
            ROUND(AVG(bs.calc_fg_made), 2) AS fgm,
            ROUND(AVG(bs.game2GA + bs.game3GA), 2) AS fga,
            CASE WHEN SUM(bs.game2GA + bs.game3GA) > 0
                THEN ROUND(SUM(bs.calc_fg_made) / SUM(bs.game2GA + bs.game3GA), 3)
                ELSE 0.000 END AS fgpct,
            ROUND(AVG(bs.gameFTM), 2) AS ftm,
            ROUND(AVG(bs.gameFTA), 2) AS fta,
            CASE WHEN SUM(bs.gameFTA) > 0
                THEN ROUND(SUM(bs.gameFTM) / SUM(bs.gameFTA), 3)
                ELSE 0.000 END AS ftpct,
            ROUND(AVG(bs.game3GM), 2) AS tgm,
            ROUND(AVG(bs.game3GA), 2) AS tga,
            CASE WHEN SUM(bs.game3GA) > 0
                THEN ROUND(SUM(bs.game3GM) / SUM(bs.game3GA), 3)
                ELSE 0.000 END AS tpct,
            ROUND(AVG(bs.gameORB), 2) AS orb,
            ROUND(AVG(bs.calc_rebounds), 2) AS reb,
            ROUND(AVG(bs.gameAST), 2) AS ast,
            ROUND(AVG(bs.gameSTL), 2) AS stl,
            ROUND(AVG(bs.gameTOV), 2) AS tvr,
            ROUND(AVG(bs.gameBLK), 2) AS blk,
            ROUND(AVG(bs.gamePF), 2) AS pf,
            ROUND(AVG(bs.calc_points), 2) AS pts,
            p.retired
        FROM ibl_box_scores bs
        JOIN ibl_plr p ON bs.pid = p.pid
        WHERE bs.game_type = {$gameType} AND {$filterClause}
        GROUP BY bs.pid, p.name, p.retired";
    }

    /**
     * Build inlined per-season stats query with predicate pushed before GROUP BY.
     *
     * Replaces SELECT from ibl_playoff_stats / ibl_heat_stats views.
     */
    private static function buildPerSeasonStatsQuery(int $gameType): string
    {
        return "SELECT bs.season_year AS year, MIN(bs.pos) AS pos, bs.pid, p.name,
            fs.team_name AS team,
            CAST(COUNT(*) AS SIGNED) AS games,
            CAST(SUM(bs.gameMIN) AS SIGNED) AS minutes,
            CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
            CAST(SUM(bs.game2GA + bs.game3GA) AS SIGNED) AS fga,
            CAST(SUM(bs.gameFTM) AS SIGNED) AS ftm,
            CAST(SUM(bs.gameFTA) AS SIGNED) AS fta,
            CAST(SUM(bs.game3GM) AS SIGNED) AS tgm,
            CAST(SUM(bs.game3GA) AS SIGNED) AS tga,
            CAST(SUM(bs.gameORB) AS SIGNED) AS orb,
            CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
            CAST(SUM(bs.gameAST) AS SIGNED) AS ast,
            CAST(SUM(bs.gameSTL) AS SIGNED) AS stl,
            CAST(SUM(bs.gameTOV) AS SIGNED) AS tvr,
            CAST(SUM(bs.gameBLK) AS SIGNED) AS blk,
            CAST(SUM(bs.gamePF) AS SIGNED) AS pf,
            CAST(SUM(bs.calc_points) AS SIGNED) AS pts
        FROM ibl_box_scores bs
        JOIN ibl_plr p ON bs.pid = p.pid
        JOIN ibl_franchise_seasons fs ON bs.teamid = fs.franchise_id
            AND bs.season_year = fs.season_ending_year
        WHERE bs.game_type = {$gameType} AND p.name = ?
        GROUP BY bs.pid, p.name, bs.season_year, fs.team_name
        ORDER BY year ASC";
    }
}
