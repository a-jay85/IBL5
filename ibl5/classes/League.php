<?php

declare(strict_types=1);

/**
 * League - IBL league-wide operations and queries
 * 
 * Extends BaseMysqliRepository for standardized database access.
 * Provides league configuration, voting candidates, and team operations.
 * 
 * @see BaseMysqliRepository For base class documentation and error codes
 */
class League extends BaseMysqliRepository
{
    private \League\LeagueContext $leagueContext;

    const CONFERENCE_NAMES = array('Eastern', 'Western');
    const DIVISION_NAMES = array('Atlantic', 'Central', 'Midwest', 'Pacific');

    const EASTERN_CONFERENCE_TEAMIDS = array(1, 2, 3, 4, 5, 7, 8, 9, 10, 11, 12, 22, 25, 27);
    const WESTERN_CONFERENCE_TEAMIDS = array(6, 13, 14, 15, 16, 17, 18, 19, 20, 21, 23, 24, 26, 28);

    const ALL_STAR_BACKCOURT_POSITIONS = "'PG', 'SG'";
    const ALL_STAR_FRONTCOURT_POSITIONS = "'C', 'SF', 'PF'";

    const SOFT_CAP_MAX = 5000;
    const HARD_CAP_MAX = 7000;

    const FREE_AGENTS_TEAMID = 0;

    /**
     * Constructor - inherits from BaseMysqliRepository
     * 
     * @param object $db Active mysqli connection (or duck-typed mock during migration)
     * @param \League\LeagueContext $leagueContext League context for multi-league support
     * @throws \RuntimeException If connection is invalid (error code 1002)
     */
    public function __construct(object $db, \League\LeagueContext $leagueContext)
    {
        parent::__construct($db);
        $this->leagueContext = $leagueContext;
    }

    /**
     * Format team IDs for SQL IN clause
     * 
     * @param array $conferenceTids Array of team IDs
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
        $result = $this->fetchOne(
            "SELECT value FROM ibl_settings WHERE name = ? LIMIT 1",
            "s",
            "Sim Length in Days"
        );

        return (int)($result['value'] ?? 0);
    }

    /**
     * Get All-Star voting candidates for a conference/position
     * 
     * @param string $votingCategory Voting category (e.g., 'EC-CF', 'WC-CB')
     * @return array All matching players
     */
    public function getAllStarCandidatesResult(string $votingCategory): array
    {
        if (strpos($votingCategory, 'EC') !== false) {
            $conferenceTids = $this::EASTERN_CONFERENCE_TEAMIDS;
        } elseif (strpos($votingCategory, 'WC') !== false) {
            $conferenceTids = $this::WESTERN_CONFERENCE_TEAMIDS;
        }

        if (strpos($votingCategory, 'CF') !== false) {
            $positions = $this::ALL_STAR_FRONTCOURT_POSITIONS;
        } elseif (strpos($votingCategory, 'CB') !== false) {
            $positions = $this::ALL_STAR_BACKCOURT_POSITIONS;
        }

        $query = "SELECT *
        FROM ibl_plr
        WHERE pos IN ($positions)
          AND tid IN ('" . $this->formatTidsForSqlQuery($conferenceTids) . "')
          AND retired != 1
          AND stats_gm > '14'
        ORDER BY name";
        
        return $this->fetchAll($query);
    }

    /**
     * Get all injured players
     * 
     * @return array All injured players
     */
    public function getInjuredPlayersResult(): array
    {
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
     * @return array All free agent players
     */
    public function getFreeAgentsResult(Season $season): array
    {
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE retired = '0'
              AND draftyear + exp + cyt - cy = ?
            ORDER BY name ASC",
            "i",
            $season->endingYear
        );
    }

    /**
     * Get all waived players
     * 
     * @return array All waived players
     */
    public function getWaivedPlayersResult(): array
    {
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE ordinal > ?
              AND retired = '0'
              AND name NOT LIKE '%|%'
            ORDER BY name ASC",
            "i",
            JSB::WAIVERS_ORDINAL
        );
    }

    /**
     * Get MVP award candidates
     * 
     * @return array All MVP candidates
     */
    public function getMVPCandidatesResult(): array
    {
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE retired != 1
              AND stats_gm >= '41'
              AND stats_min / stats_gm >= '30'
            ORDER BY name"
        );
    }

    /**
     * Get Sixth Person of the Year award candidates
     * 
     * @return array All Sixth Person candidates
     */
    public function getSixthPersonOfTheYearCandidatesResult(): array
    {
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE retired != 1
              AND stats_min / stats_gm >= 15
              AND stats_gs / stats_gm <= '.5'
              AND stats_gm >= '41'
            ORDER BY name"
        );
    }

    /**
     * Get Rookie of the Year award candidates
     * 
     * @return array All Rookie of the Year candidates
     */
    public function getRookieOfTheYearCandidatesResult(): array
    {
        return $this->fetchAll(
            "SELECT *
            FROM ibl_plr
            WHERE retired != 1
              AND exp = '1'
              AND stats_gm >= '41'
            ORDER BY name"
        );
    }

    /**
     * Get GM of the Year award candidates
     * 
     * @return array All GM candidates
     */
    public function getGMOfTheYearCandidatesResult(): array
    {
        $table = $this->leagueContext->getTableName('ibl_team_info');
        return $this->fetchAll(
            "SELECT owner_name, team_city, team_name
            FROM {$table}
            WHERE teamid != ?
            ORDER BY owner_name",
            "i",
            League::FREE_AGENTS_TEAMID
        );
    }

    /**
     * Get all teams
     * 
     * @return array All teams except free agents
     */
    public function getAllTeamsResult(): array
    {
        $table = $this->leagueContext->getTableName('ibl_team_info');
        return $this->fetchAll(
            "SELECT *
            FROM {$table}
            WHERE teamid != ?
            ORDER BY teamid ASC",
            "i",
            League::FREE_AGENTS_TEAMID
        );
    }
}