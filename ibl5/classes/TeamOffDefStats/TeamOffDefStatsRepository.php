<?php

declare(strict_types=1);

namespace TeamOffDefStats;

use League\League;
use TeamOffDefStats\Contracts\TeamOffDefStatsRepositoryInterface;

/**
 * Repository for fetching league-wide team statistics
 *
 * Uses a single bulk JOIN query to fetch all team offense and defense
 * statistics, eliminating the N+1 query problem in the original implementation.
 *
 * Performance improvement: 30 queries → 1 query
 *
 * @see TeamOffDefStatsRepositoryInterface for method documentation
 *
 * @phpstan-import-type AllTeamStatsRow from Contracts\TeamOffDefStatsRepositoryInterface
 * @phpstan-import-type TeamOffenseStatsRow from Contracts\TeamOffDefStatsRepositoryInterface
 * @phpstan-import-type TeamDefenseStatsRow from Contracts\TeamOffDefStatsRepositoryInterface
 */
class TeamOffDefStatsRepository extends \BaseMysqliRepository implements TeamOffDefStatsRepositoryInterface
{
    /**
     * @param \mysqli $db Database connection
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * Get all team statistics (offense and defense) in a single bulk query
     *
     * @see TeamOffDefStatsRepositoryInterface::getAllTeamStats()
     * @return list<AllTeamStatsRow> Array of team statistics rows ordered by team name
     */
    public function getAllTeamStats(int $seasonYear): array
    {
        $query = "
            SELECT
                ti.teamid,
                ti.team_city,
                ti.team_name,
                ti.color1,
                ti.color2,
                tos.games AS offense_games,
                tos.fgm AS offense_fgm,
                tos.fga AS offense_fga,
                tos.ftm AS offense_ftm,
                tos.fta AS offense_fta,
                tos.tgm AS offense_tgm,
                tos.tga AS offense_tga,
                tos.orb AS offense_orb,
                tos.reb AS offense_reb,
                tos.ast AS offense_ast,
                tos.stl AS offense_stl,
                tos.tvr AS offense_tvr,
                tos.blk AS offense_blk,
                tos.pf AS offense_pf,
                tds.games AS defense_games,
                tds.fgm AS defense_fgm,
                tds.fga AS defense_fga,
                tds.ftm AS defense_ftm,
                tds.fta AS defense_fta,
                tds.tgm AS defense_tgm,
                tds.tga AS defense_tga,
                tds.orb AS defense_orb,
                tds.reb AS defense_reb,
                tds.ast AS defense_ast,
                tds.stl AS defense_stl,
                tds.tvr AS defense_tvr,
                tds.blk AS defense_blk,
                tds.pf AS defense_pf
            FROM ibl_team_info ti
            LEFT JOIN (" . self::buildOffenseSubquery('bst.season_year = ?') . ") tos ON ti.teamid = tos.teamID
            LEFT JOIN (" . self::buildDefenseSubquery('my.season_year = ?') . ") tds ON ti.teamid = tds.teamID
            WHERE ti.teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
            ORDER BY ti.team_city
        ";

        /** @var list<AllTeamStatsRow> */
        return $this->fetchAll($query, "ii", $seasonYear, $seasonYear);
    }

    /**
     * Get team offense statistics by team name
     *
     * @see TeamOffDefStatsRepositoryInterface::getTeamOffenseStats()
     * @param string $teamName Team name
     * @param int $seasonYear Season ending year
     * @return TeamOffenseStatsRow|null Team offense statistics
     */
    public function getTeamOffenseStats(string $teamName, int $seasonYear): ?array
    {
        /** @var TeamOffenseStatsRow|null */
        return $this->fetchOne(
            "SELECT * FROM (" . self::buildOffenseSubquery('bst.season_year = ? AND fs.team_name = ?') . ") t LIMIT 1",
            "is",
            $seasonYear,
            $teamName
        );
    }

    /**
     * Get team defense statistics by team name
     *
     * @see TeamOffDefStatsRepositoryInterface::getTeamDefenseStats()
     * @param string $teamName Team name
     * @param int $seasonYear Season ending year
     * @return TeamDefenseStatsRow|null Team defense statistics
     */
    public function getTeamDefenseStats(string $teamName, int $seasonYear): ?array
    {
        /** @var TeamDefenseStatsRow|null */
        return $this->fetchOne(
            "SELECT * FROM (" . self::buildDefenseSubquery('my.season_year = ? AND fs.team_name = ?') . ") t LIMIT 1",
            "is",
            $seasonYear,
            $teamName
        );
    }

    /**
     * Get both offense and defense statistics for a team in a single JOIN query
     *
     * @see TeamOffDefStatsRepositoryInterface::getTeamBothStats()
     * @param string $teamName Team name
     * @param int $seasonYear Season ending year
     * @return array{offense: TeamOffenseStatsRow, defense: TeamDefenseStatsRow}|null Both stats or null
     */
    public function getTeamBothStats(string $teamName, int $seasonYear): ?array
    {
        /** @var array<string, int|string|null>|null $row */
        $row = $this->fetchOne(
            "SELECT
                tos.teamID AS tos_teamID, tos.name AS tos_name,
                tos.games AS tos_games, tos.fgm AS tos_fgm, tos.fga AS tos_fga,
                tos.ftm AS tos_ftm, tos.fta AS tos_fta, tos.tgm AS tos_tgm, tos.tga AS tos_tga,
                tos.orb AS tos_orb, tos.reb AS tos_reb, tos.ast AS tos_ast, tos.stl AS tos_stl,
                tos.tvr AS tos_tvr, tos.blk AS tos_blk, tos.pf AS tos_pf,
                tds.teamID AS tds_teamID, tds.name AS tds_name,
                tds.games AS tds_games, tds.fgm AS tds_fgm, tds.fga AS tds_fga,
                tds.ftm AS tds_ftm, tds.fta AS tds_fta, tds.tgm AS tds_tgm, tds.tga AS tds_tga,
                tds.orb AS tds_orb, tds.reb AS tds_reb, tds.ast AS tds_ast, tds.stl AS tds_stl,
                tds.tvr AS tds_tvr, tds.blk AS tds_blk, tds.pf AS tds_pf
            FROM (" . self::buildOffenseSubquery('bst.season_year = ? AND fs.team_name = ?') . ") tos
            JOIN (" . self::buildDefenseSubquery('my.season_year = ? AND fs.team_name = ?') . ") tds
                ON tos.teamID = tds.teamID AND tos.season_year = tds.season_year
            LIMIT 1",
            "isis",
            $seasonYear,
            $teamName,
            $seasonYear,
            $teamName
        );

        if ($row === null) {
            return null;
        }

        /** @var TeamOffenseStatsRow $offense */
        $offense = [
            'teamID' => (int) $row['tos_teamID'],
            'name' => (string) $row['tos_name'],
            'games' => (int) $row['tos_games'],
            'fgm' => (int) $row['tos_fgm'],
            'fga' => (int) $row['tos_fga'],
            'ftm' => (int) $row['tos_ftm'],
            'fta' => (int) $row['tos_fta'],
            'tgm' => (int) $row['tos_tgm'],
            'tga' => (int) $row['tos_tga'],
            'orb' => (int) $row['tos_orb'],
            'reb' => (int) $row['tos_reb'],
            'ast' => (int) $row['tos_ast'],
            'stl' => (int) $row['tos_stl'],
            'tvr' => (int) $row['tos_tvr'],
            'blk' => (int) $row['tos_blk'],
            'pf' => (int) $row['tos_pf'],
        ];

        /** @var TeamDefenseStatsRow $defense */
        $defense = [
            'teamID' => (int) $row['tds_teamID'],
            'name' => (string) $row['tds_name'],
            'games' => (int) $row['tds_games'],
            'fgm' => (int) $row['tds_fgm'],
            'fga' => (int) $row['tds_fga'],
            'ftm' => (int) $row['tds_ftm'],
            'fta' => (int) $row['tds_fta'],
            'tgm' => (int) $row['tds_tgm'],
            'tga' => (int) $row['tds_tga'],
            'orb' => (int) $row['tds_orb'],
            'reb' => (int) $row['tds_reb'],
            'ast' => (int) $row['tds_ast'],
            'stl' => (int) $row['tds_stl'],
            'tvr' => (int) $row['tds_tvr'],
            'blk' => (int) $row['tds_blk'],
            'pf' => (int) $row['tds_pf'],
        ];

        return ['offense' => $offense, 'defense' => $defense];
    }

    /**
     * Build inlined offense stats subquery with filter pushed before GROUP BY.
     *
     * Replaces ibl_team_offense_stats view which materializes ALL seasons
     * before filtering.
     */
    private static function buildOffenseSubquery(string $filterClause): string
    {
        return "SELECT fs.franchise_id AS teamID, fs.team_name AS name, bst.season_year,
            CAST(COUNT(*) AS SIGNED) AS games,
            CAST(SUM(bst.game2GM + bst.game3GM) AS SIGNED) AS fgm,
            CAST(SUM(bst.game2GA + bst.game3GA) AS SIGNED) AS fga,
            CAST(SUM(bst.gameFTM) AS SIGNED) AS ftm,
            CAST(SUM(bst.gameFTA) AS SIGNED) AS fta,
            CAST(SUM(bst.game3GM) AS SIGNED) AS tgm,
            CAST(SUM(bst.game3GA) AS SIGNED) AS tga,
            CAST(SUM(bst.gameORB) AS SIGNED) AS orb,
            CAST(SUM(bst.gameORB + bst.gameDRB) AS SIGNED) AS reb,
            CAST(SUM(bst.gameAST) AS SIGNED) AS ast,
            CAST(SUM(bst.gameSTL) AS SIGNED) AS stl,
            CAST(SUM(bst.gameTOV) AS SIGNED) AS tvr,
            CAST(SUM(bst.gameBLK) AS SIGNED) AS blk,
            CAST(SUM(bst.gamePF) AS SIGNED) AS pf
        FROM ibl_box_scores_teams bst
        JOIN ibl_franchise_seasons fs
            ON fs.team_name = bst.name AND fs.season_ending_year = bst.season_year
        WHERE bst.game_type = 1 AND {$filterClause}
        GROUP BY fs.franchise_id, fs.team_name, bst.season_year";
    }

    /**
     * Build inlined defense stats subquery with filter pushed before GROUP BY.
     *
     * Replaces ibl_team_defense_stats view which materializes ALL seasons
     * before filtering.
     */
    private static function buildDefenseSubquery(string $filterClause): string
    {
        return "SELECT fs.franchise_id AS teamID, fs.team_name AS name, my.season_year,
            CAST(COUNT(*) AS SIGNED) AS games,
            CAST(SUM(opp.game2GM + opp.game3GM) AS SIGNED) AS fgm,
            CAST(SUM(opp.game2GA + opp.game3GA) AS SIGNED) AS fga,
            CAST(SUM(opp.gameFTM) AS SIGNED) AS ftm,
            CAST(SUM(opp.gameFTA) AS SIGNED) AS fta,
            CAST(SUM(opp.game3GM) AS SIGNED) AS tgm,
            CAST(SUM(opp.game3GA) AS SIGNED) AS tga,
            CAST(SUM(opp.gameORB) AS SIGNED) AS orb,
            CAST(SUM(opp.gameORB + opp.gameDRB) AS SIGNED) AS reb,
            CAST(SUM(opp.gameAST) AS SIGNED) AS ast,
            CAST(SUM(opp.gameSTL) AS SIGNED) AS stl,
            CAST(SUM(opp.gameTOV) AS SIGNED) AS tvr,
            CAST(SUM(opp.gameBLK) AS SIGNED) AS blk,
            CAST(SUM(opp.gamePF) AS SIGNED) AS pf
        FROM ibl_box_scores_teams my
        JOIN ibl_box_scores_teams opp
            ON my.Date = opp.Date
            AND my.visitorTeamID = opp.visitorTeamID
            AND my.homeTeamID = opp.homeTeamID
            AND my.gameOfThatDay = opp.gameOfThatDay
            AND my.name <> opp.name
        JOIN ibl_franchise_seasons fs
            ON fs.team_name = my.name AND fs.season_ending_year = my.season_year
        WHERE my.game_type = 1 AND {$filterClause}
        GROUP BY fs.franchise_id, fs.team_name, my.season_year";
    }
}
