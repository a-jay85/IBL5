<?php

declare(strict_types=1);

namespace League;

use BaseMysqliRepository;
use JSB;
use Season\Season;

/**
 * League - IBL league-wide operations and queries
 *
 * Extends BaseMysqliRepository for standardized database access.
 * Provides league configuration, voting candidates, and team operations.
 *
 * @see BaseMysqliRepository For base class documentation and error codes
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class League extends BaseMysqliRepository
{
    const CONFERENCE_NAMES = array('Eastern', 'Western');
    const DIVISION_NAMES = array('Atlantic', 'Central', 'Midwest', 'Pacific');

    const EASTERN_CONFERENCE_TEAMIDS = array(1, 2, 3, 4, 5, 7, 8, 9, 10, 11, 12, 22, 25, 27);
    const WESTERN_CONFERENCE_TEAMIDS = array(6, 13, 14, 15, 16, 17, 18, 19, 20, 21, 23, 24, 26, 28);

    const ALL_STAR_BACKCOURT_POSITIONS = "'PG', 'SG'";
    const ALL_STAR_FRONTCOURT_POSITIONS = "'C', 'SF', 'PF'";

    const SOFT_CAP_MAX = 5000;
    const HARD_CAP_MAX = 7000;

    const FREE_AGENTS_TEAMID = 0;
    const FREE_AGENTS_TEAM_NAME = 'Free Agents';
    const MAX_REAL_TEAMID = 28;
    const ROOKIES_TEAMID = 40;
    const SOPHOMORES_TEAMID = 41;
    const ALL_STAR_AWAY_TEAMID = 50;
    const ALL_STAR_HOME_TEAMID = 51;

    /**
     * Check if a team ID represents a real franchise (not Free Agents, All-Star, Rookies, etc.)
     */
    public static function isRealFranchise(int $teamId): bool
    {
        return $teamId >= 1 && $teamId <= self::MAX_REAL_TEAMID;
    }

    /**
     * Constructor - inherits from BaseMysqliRepository
     *
     * @param \mysqli $db Active mysqli connection
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(\mysqli $db)
    {
        parent::__construct($db);
    }

    /**
     * Format team IDs for SQL IN clause
     *
     * @param array<int, int> $conferenceTids Array of team IDs
     * @return string Formatted string for SQL IN clause
     */
    public function formatTidsForSqlQuery(array $conferenceTids): string
    {
        $tidsFormattedForQuery = join("','", $conferenceTids);
        return $tidsFormattedForQuery;
    }

    /**
     * Get sim length in days from settings
     * 
     * @return int Sim length in days
     */
    public function getSimLengthInDays(): int
    {
        /** @var array{value: string}|null $result */
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ? LIMIT 1",
            "s",
            "Sim Length in Days"
        );

        if ($result === null) {
            return 0;
        }

        return (int) $result['value'];
    }

    /**
     * Get All-Star voting candidates for a conference/position
     *
     * @param string $votingCategory Voting category (e.g., 'EC-CF', 'WC-CB')
     * @return list<PlayerRow> All matching players
     */
    public function getAllStarCandidatesResult(string $votingCategory): array
    {
        if (strpos($votingCategory, 'EC') !== false) {
            $conferenceTids = self::EASTERN_CONFERENCE_TEAMIDS;
        } else {
            $conferenceTids = self::WESTERN_CONFERENCE_TEAMIDS;
        }

        if (strpos($votingCategory, 'CF') !== false) {
            $positions = self::ALL_STAR_FRONTCOURT_POSITIONS;
        } else {
            $positions = self::ALL_STAR_BACKCOURT_POSITIONS;
        }

        $query = "SELECT p.*, t.team_name AS teamname, t.team_city, t.color1, t.color2
        FROM ibl_plr p
        JOIN ibl_team_info t ON p.teamid = t.teamid
        WHERE p.pos IN ($positions)
          AND p.teamid IN ('" . $this->formatTidsForSqlQuery($conferenceTids) . "')
          AND p.retired != 1
          AND p.stats_gm > '14'
        ORDER BY p.name";

        /** @var list<PlayerRow> */
        return $this->fetchAll($query);
    }

    /**
     * Get all injured players
     *
     * @return list<PlayerRow> All injured players
     */
    public function getInjuredPlayersResult(): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE injured > 0
              AND retired = 0
            ORDER BY ordinal ASC"
        );
    }

    /**
     * Get all free agents for the season
     *
     * @param Season $season Current season
     * @return list<PlayerRow> All free agent players
     */
    public function getFreeAgentsResult(Season $season): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE retired = 0
              AND CASE COALESCE(cy, 0) + 1
                  WHEN 1 THEN salary_yr1
                  WHEN 2 THEN salary_yr2
                  WHEN 3 THEN salary_yr3
                  WHEN 4 THEN salary_yr4
                  WHEN 5 THEN salary_yr5
                  WHEN 6 THEN salary_yr6
                  ELSE 0
              END = 0
            ORDER BY name ASC"
        );
    }

    /**
     * Get all waived players
     *
     * @return list<PlayerRow> All waived players
     */
    public function getWaivedPlayersResult(): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE ordinal > ?
              AND retired = 0
              AND name != '(no starter)'
            ORDER BY name ASC",
            "i",
            JSB::WAIVERS_ORDINAL
        );
    }

    /**
     * Get MVP award candidates
     *
     * @return list<PlayerRow> All MVP candidates
     */
    public function getMVPCandidatesResult(): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT p.*, t.team_name AS teamname, t.team_city, t.color1, t.color2
            FROM ibl_plr p
            JOIN ibl_team_info t ON p.teamid = t.teamid
            WHERE p.retired != 1
              AND p.stats_gm >= '41'
              AND p.stats_min / p.stats_gm >= '30'
            ORDER BY p.name"
        );
    }

    /**
     * Get Sixth Person of the Year award candidates
     *
     * @return list<PlayerRow> All Sixth Person candidates
     */
    public function getSixthPersonOfTheYearCandidatesResult(): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT p.*, t.team_name AS teamname, t.team_city, t.color1, t.color2
            FROM ibl_plr p
            JOIN ibl_team_info t ON p.teamid = t.teamid
            WHERE p.retired != 1
              AND p.stats_min / p.stats_gm >= 15
              AND p.stats_gs / p.stats_gm <= '.5'
              AND p.stats_gm >= '41'
            ORDER BY p.name"
        );
    }

    /**
     * Get Rookie of the Year award candidates
     *
     * @return list<PlayerRow> All Rookie of the Year candidates
     */
    public function getRookieOfTheYearCandidatesResult(): array
    {
        /** @var list<PlayerRow> */
        return $this->fetchAll(
            "SELECT p.*, t.team_name AS teamname, t.team_city, t.color1, t.color2
            FROM ibl_plr p
            JOIN ibl_team_info t ON p.teamid = t.teamid
            WHERE p.retired != 1
              AND p.exp = '1'
              AND p.stats_gm >= '41'
            ORDER BY p.name"
        );
    }

    /**
     * Get GM of the Year award candidates
     *
     * @return array<int, array<string, mixed>> All GM candidates
     */
    public function getGMOfTheYearCandidatesResult(): array
    {
        return $this->fetchAll(
            "SELECT owner_name, team_city, team_name
            FROM ibl_team_info
            WHERE teamid BETWEEN 1 AND ?
            ORDER BY owner_name",
            "i",
            self::MAX_REAL_TEAMID
        );
    }

    /**
     * Get all teams
     *
     * @return array<int, array<string, mixed>> All teams except free agents
     */
    public function getAllTeamsResult(): array
    {
        return $this->fetchAll(
            "SELECT *
            FROM ibl_team_info
            WHERE teamid BETWEEN 1 AND ?
            ORDER BY teamid ASC",
            "i",
            self::MAX_REAL_TEAMID
        );
    }
}