<?php

declare(strict_types=1);

namespace FranchiseRecordBook\Contracts;

/**
 * Interface for franchise record book database operations.
 *
 * @phpstan-type AlltimeRecord array{id: int, scope: string, team_id: int|null, record_type: string, stat_category: string, ranking: int, player_name: string, car_block_id: int|null, pid: int|null, stat_value: string, stat_raw: int, team_of_record: int|null, season_year: int|null, career_total: int|null}
 * @phpstan-type TeamInfo array{teamid: int, team_name: string, color1: string, color2: string}
 */
interface FranchiseRecordBookRepositoryInterface
{
    /**
     * Get all-time single-season records for a team.
     *
     * @param int $teamId JSB team ID (1-28)
     * @param int $limit Max records per stat category
     * @return list<AlltimeRecord>
     */
    public function getTeamSingleSeasonRecords(int $teamId, int $limit = 10): array;

    /**
     * Get all-time career records for a team (league-wide scope only, since
     * team-scope career records are not stored — odd entries in team groups
     * are team season records which are skipped by the parser).
     *
     * @param int $limit Max records per stat category
     * @return list<AlltimeRecord>
     */
    public function getLeagueCareerRecords(int $limit = 10): array;

    /**
     * Get league-wide single-season records.
     *
     * @param int $limit Max records per stat category
     * @return list<AlltimeRecord>
     */
    public function getLeagueSingleSeasonRecords(int $limit = 10): array;

    /**
     * Get all teams for the team selector dropdown.
     *
     * @return list<TeamInfo>
     */
    public function getAllTeams(): array;

    /**
     * Get team info by team ID.
     *
     * @param int $teamId Database team ID
     * @return TeamInfo|null
     */
    public function getTeamInfo(int $teamId): ?array;
}
