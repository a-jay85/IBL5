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
            "SELECT id, scope, team_id, record_type, stat_category, ranking,
                    player_name, car_block_id, pid, stat_value, stat_raw,
                    team_of_record, season_year, career_total
             FROM ibl_rcb_alltime_records
             WHERE scope = 'team' AND team_id = ? AND record_type = 'single_season'
               AND ranking <= ?
             ORDER BY stat_category, ranking",
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
            "SELECT id, scope, team_id, record_type, stat_category, ranking,
                    player_name, car_block_id, pid, stat_value, stat_raw,
                    team_of_record, season_year, career_total
             FROM ibl_rcb_alltime_records
             WHERE scope = 'league' AND record_type = 'career'
               AND ranking <= ?
             ORDER BY stat_category, ranking",
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
            "SELECT id, scope, team_id, record_type, stat_category, ranking,
                    player_name, car_block_id, pid, stat_value, stat_raw,
                    team_of_record, season_year, career_total
             FROM ibl_rcb_alltime_records
             WHERE scope = 'league' AND record_type = 'single_season'
               AND ranking <= ?
             ORDER BY stat_category, ranking",
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
             WHERE teamid <> ?
             ORDER BY team_name ASC",
            'i',
            \League::FREE_AGENTS_TEAMID
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
