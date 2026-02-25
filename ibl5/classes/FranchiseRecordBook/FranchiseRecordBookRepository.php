<?php

declare(strict_types=1);

namespace FranchiseRecordBook;

use FranchiseRecordBook\Contracts\FranchiseRecordBookRepositoryInterface;

/**
 * Repository for franchise record book database operations.
 *
 * Queries the ibl_rcb_alltime_records table for pre-computed all-time records
 * parsed from the JSB engine's .rcb file.
 *
 * @phpstan-import-type AlltimeRecord from FranchiseRecordBookRepositoryInterface
 * @phpstan-import-type TeamInfo from FranchiseRecordBookRepositoryInterface
 */
class FranchiseRecordBookRepository extends \BaseMysqliRepository implements FranchiseRecordBookRepositoryInterface
{
    /**
     * @see FranchiseRecordBookRepositoryInterface::getTeamSingleSeasonRecords()
     *
     * @return list<AlltimeRecord>
     */
    public function getTeamSingleSeasonRecords(int $teamId, int $limit = 10): array
    {
        /** @var list<AlltimeRecord> $rows */
        $rows = $this->fetchAll(
            "SELECT r.id, r.scope, r.team_id, r.record_type, r.stat_category, r.ranking,
                    r.player_name, r.car_block_id,
                    COALESCE(r.pid, plr.pid) AS pid,
                    r.stat_value, r.stat_raw,
                    r.team_of_record, r.season_year, r.career_total
             FROM ibl_rcb_alltime_records r
             LEFT JOIN (
               SELECT REPLACE(name, '''', '') AS clean_name, MAX(pid) AS pid
               FROM ibl_plr GROUP BY clean_name
             ) plr ON plr.clean_name = r.player_name
             WHERE r.scope = 'team' AND r.team_id = ? AND r.record_type = 'single_season'
               AND r.ranking <= ?
             ORDER BY r.stat_category, r.ranking",
            'ii',
            $teamId,
            $limit
        );

        return $rows;
    }

    /**
     * @see FranchiseRecordBookRepositoryInterface::getLeagueCareerRecords()
     *
     * @return list<AlltimeRecord>
     */
    public function getLeagueCareerRecords(int $limit = 10): array
    {
        /** @var list<AlltimeRecord> $rows */
        $rows = $this->fetchAll(
            "SELECT r.id, r.scope, r.team_id, r.record_type, r.stat_category, r.ranking,
                    r.player_name, r.car_block_id,
                    COALESCE(r.pid, plr.pid) AS pid,
                    r.stat_value, r.stat_raw,
                    r.team_of_record, r.season_year, r.career_total
             FROM ibl_rcb_alltime_records r
             LEFT JOIN (
               SELECT REPLACE(name, '''', '') AS clean_name, MAX(pid) AS pid
               FROM ibl_plr GROUP BY clean_name
             ) plr ON plr.clean_name = r.player_name
             WHERE r.scope = 'league' AND r.record_type = 'career'
               AND r.ranking <= ?
             ORDER BY r.stat_category, r.ranking",
            'i',
            $limit
        );

        return $rows;
    }

    /**
     * @see FranchiseRecordBookRepositoryInterface::getLeagueSingleSeasonRecords()
     *
     * @return list<AlltimeRecord>
     */
    public function getLeagueSingleSeasonRecords(int $limit = 10): array
    {
        /** @var list<AlltimeRecord> $rows */
        $rows = $this->fetchAll(
            "SELECT r.id, r.scope, r.team_id, r.record_type, r.stat_category, r.ranking,
                    r.player_name, r.car_block_id,
                    COALESCE(r.pid, plr.pid) AS pid,
                    r.stat_value, r.stat_raw,
                    r.team_of_record, r.season_year, r.career_total
             FROM ibl_rcb_alltime_records r
             LEFT JOIN (
               SELECT REPLACE(name, '''', '') AS clean_name, MAX(pid) AS pid
               FROM ibl_plr GROUP BY clean_name
             ) plr ON plr.clean_name = r.player_name
             WHERE r.scope = 'league' AND r.record_type = 'single_season'
               AND r.ranking <= ?
             ORDER BY r.stat_category, r.ranking",
            'i',
            $limit
        );

        return $rows;
    }

    /**
     * @see FranchiseRecordBookRepositoryInterface::getAllTeams()
     *
     * @return list<TeamInfo>
     */
    public function getAllTeams(): array
    {
        /** @var list<TeamInfo> $rows */
        $rows = $this->fetchAll(
            "SELECT teamid, team_name, color1, color2
             FROM ibl_team_info
             WHERE teamid BETWEEN 1 AND ?
             ORDER BY team_name ASC",
            'i',
            \League::MAX_REAL_TEAMID
        );

        return $rows;
    }

    /**
     * @see FranchiseRecordBookRepositoryInterface::getTeamInfo()
     *
     * @return TeamInfo|null
     */
    public function getTeamInfo(int $teamId): ?array
    {
        /** @var TeamInfo|null $row */
        $row = $this->fetchOne(
            'SELECT teamid, team_name, color1, color2
             FROM ibl_team_info
             WHERE teamid = ?',
            'i',
            $teamId
        );

        return $row;
    }
}
