<?php

class League
{
    protected $db;

    const EASTERN_CONFERENCE_TEAMIDS = array(1, 2, 3, 4, 5, 7, 8, 9, 10, 11, 12, 22, 25, 27);
    const WESTERN_CONFERENCE_TEAMIDS = array(6, 13, 14, 15, 16, 17, 18, 19, 20, 21, 23, 24, 26, 28);

    const ALL_STAR_BACKCOURT_POSITIONS = "'PG', 'SG'";
    const ALL_STAR_FRONTCOURT_POSITIONS = "'C', 'SF', 'PF'";

    const SOFT_CAP_MAX = 5000;
    const HARD_CAP_MAX = 7000;

    const FREE_AGENTS_TEAMID = 35;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function formatTidsForSqlQuery($conferenceTids)
    {
        $tidsFormattedForQuery = join("','", $conferenceTids);
        return $tidsFormattedForQuery;
    }

    public function getAllStarCandidatesResult($votingCategory)
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
        $result = $this->db->sql_query($query);
        return $result;
    }

    public function getInjuredPlayersResult()
    {
        $query = "SELECT *
            FROM ibl_plr
            WHERE injured > 0
              AND retired = 0
            ORDER BY ordinal ASC";
        $result = $this->db->sql_query($query);
        return $result;
    }

    public function getWaivedPlayersResult()
    {
        $query = "SELECT *
            FROM ibl_plr
            WHERE ordinal > '" . JSB::WAIVERS_ORDINAL . "'
              AND retired = '0'
              AND name NOT LIKE '%|%'
            ORDER BY name ASC";
        $result = $this->db->sql_query($query);
        return $result;
    }

    public function getMVPCandidatesResult()
    {
        $query = "SELECT *
            FROM ibl_plr
            WHERE retired != 1
              AND stats_gm >= '41'
              AND stats_min / stats_gm >= '30'
            ORDER BY name";
        $result = $this->db->sql_query($query);
        return $result;
    }

    public function getSixthPersonOfTheYearCandidatesResult()
    {
        $query = "SELECT *
            FROM ibl_plr
            WHERE retired != 1
              AND stats_min / stats_gm >= 15
              AND stats_gs / stats_gm <= '.5'
              AND stats_gm >= '41'
            ORDER BY name";
        $result = $this->db->sql_query($query);
        return $result;
    }

    public function getRookieOfTheYearCandidatesResult()
    {
        $query = "SELECT *
            FROM ibl_plr
            WHERE retired != 1
              AND exp = '1'
              AND stats_gm >= '41'
            ORDER BY name";
        $result = $this->db->sql_query($query);
        return $result;
    }

    public function getGMOfTheYearCandidatesResult()
    {
        $query = "SELECT owner_name, team_city, team_name
            FROM ibl_team_info
            WHERE teamid != " . League::FREE_AGENTS_TEAMID . "
            ORDER BY owner_name";
        $result = $this->db->sql_query($query);
        return $result;
    }

    public static function getAllTeamsResult($db)
    {
        $query = "SELECT *
            FROM ibl_team_info
            WHERE teamid != " . League::FREE_AGENTS_TEAMID . "
            ORDER BY teamid ASC;";
        $result = $db->sql_query($query);
        return $result;
    }
}