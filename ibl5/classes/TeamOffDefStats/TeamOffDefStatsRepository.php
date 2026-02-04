<?php

declare(strict_types=1);

namespace TeamOffDefStats;

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
     * @param object $db Database connection (mysqli wrapper)
     */
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * Get all team statistics (offense and defense) in a single bulk query
     *
     * @see TeamOffDefStatsRepositoryInterface::getAllTeamStats()
     * @return list<AllTeamStatsRow> Array of team statistics rows ordered by team name
     */
    public function getAllTeamStats(): array
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
            LEFT JOIN ibl_team_offense_stats tos ON ti.teamid = tos.teamID
            LEFT JOIN ibl_team_defense_stats tds ON ti.teamid = tds.teamID
            WHERE ti.teamid != " . \League::FREE_AGENTS_TEAMID . "
            ORDER BY ti.team_city
        ";

        /** @var list<AllTeamStatsRow> */
        return $this->fetchAll($query);
    }

    /**
     * Get team offense statistics by team name
     *
     * @see TeamOffDefStatsRepositoryInterface::getTeamOffenseStats()
     * @param string $teamName Team name
     * @return TeamOffenseStatsRow|null Team offense statistics
     */
    public function getTeamOffenseStats(string $teamName): ?array
    {
        /** @var TeamOffenseStatsRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_team_offense_stats WHERE name = ? LIMIT 1",
            "s",
            $teamName
        );
    }

    /**
     * Get team defense statistics by team name
     *
     * @see TeamOffDefStatsRepositoryInterface::getTeamDefenseStats()
     * @param string $teamName Team name
     * @return TeamDefenseStatsRow|null Team defense statistics
     */
    public function getTeamDefenseStats(string $teamName): ?array
    {
        /** @var TeamDefenseStatsRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_team_defense_stats WHERE name = ? LIMIT 1",
            "s",
            $teamName
        );
    }
}
