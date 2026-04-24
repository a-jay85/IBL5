<?php

declare(strict_types=1);

namespace Services;

use League\League;

/**
 * CommonMysqliRepository - Centralized repository for common database queries using mysqli
 *
 * This class consolidates frequently used database operations that were
 * duplicated across multiple repository classes, following the DRY principle.
 *
 * Uses prepared statements for all queries to prevent SQL injection.
 * Extends BaseMysqliRepository for standardized query execution.
 *
 * Responsibilities:
 * - User lookup operations
 * - Team lookup operations
 * - Player lookup operations
 * - Common data retrieval patterns
 *
 * @phpstan-type UserRow array{user_id: int, username: string, user_email: string}
 * @phpstan-type TeamInfoRow array{teamid: int, team_city: string, team_name: string, color1: string, color2: string, arena: string, owner_name: string, owner_email: string, gm_username: ?string, discord_id: ?int, used_extension_this_chunk: int, used_extension_this_season: ?int, has_mle: int, has_lle: int, ...}
 * @phpstan-type PlayerRow array{pid: int, name: string, nickname: ?string, age: ?int, teamid: int, teamname: ?string, pos: string, stamina: ?int, exp: ?int, bird: ?int, cy: ?int, cyt: ?int, cy1: ?int, cy2: ?int, cy3: ?int, cy4: ?int, cy5: ?int, cy6: ?int, ordinal: ?int, injured: ?int, retired: ?int, droptime: ?int, stats_gs: ?int, stats_gm: ?int, stats_min: ?int, stats_fgm: ?int, stats_fga: ?int, stats_ftm: ?int, stats_fta: ?int, stats_3gm: ?int, stats_3ga: ?int, stats_orb: ?int, stats_drb: ?int, stats_ast: ?int, stats_stl: ?int, stats_tvr: ?int, stats_blk: ?int, stats_pf: ?int, sh_pts: ?int, sh_reb: ?int, sh_ast: ?int, sh_stl: ?int, sh_blk: ?int, s_dd: ?int, s_td: ?int, sp_pts: ?int, sp_reb: ?int, sp_ast: ?int, sp_stl: ?int, sp_blk: ?int, ch_pts: ?int, ch_reb: ?int, ch_ast: ?int, ch_stl: ?int, ch_blk: ?int, c_dd: ?int, c_td: ?int, cp_pts: ?int, cp_reb: ?int, cp_ast: ?int, cp_stl: ?int, cp_blk: ?int, car_gm: ?int, car_min: ?int, car_fgm: ?int, car_fga: ?int, car_ftm: ?int, car_fta: ?int, car_tgm: ?int, car_tga: ?int, car_orb: ?int, car_drb: ?int, car_reb: ?int, car_ast: ?int, car_stl: ?int, car_to: ?int, car_blk: ?int, car_pf: ?int, r_fga: ?int, r_fgp: ?int, r_fta: ?int, r_ftp: ?int, r_3ga: ?int, r_3gp: ?int, r_orb: ?int, r_drb: ?int, r_ast: ?int, r_stl: ?int, r_tvr: ?int, r_blk: ?int, r_foul: ?int, oo: ?int, od: ?int, r_drive_off: ?int, dd: ?int, po: ?int, pd: ?int, r_trans_off: ?int, td: ?int, clutch: ?int, consistency: ?int, talent: ?int, skill: ?int, intangibles: ?int, loyalty: ?int, playing_time: ?int, winner: ?int, tradition: ?int, security: ?int, draftround: ?int, draftedby: ?string, draftedbycurrentname: ?string, draftyear: ?int, draftpickno: ?int, htft: ?int, htin: ?int, wt: ?int, college: ?string, dc_pg_depth: ?int, dc_sg_depth: ?int, dc_sf_depth: ?int, dc_pf_depth: ?int, dc_c_depth: ?int, dc_can_play_in_game: ?int, dc_minutes: ?int, dc_of: ?int, dc_df: ?int, dc_oi: ?int, dc_di: ?int, dc_bh: ?int, ...}
 */
class CommonMysqliRepository extends \BaseMysqliRepository
{
    /**
     * Gets complete user information by username
     *
     * @param string $username Username to look up
     * @return UserRow|null User information or null if not found
     */
    public function getUserByUsername(string $username): ?array
    {
        /** @var UserRow|null */
        return $this->fetchOne(
            "SELECT id AS user_id, username, email AS user_email FROM auth_users WHERE username = ?",
            "s",
            $username
        );
    }

    /**
     * Gets the team name associated with a username
     * 
     * @param string|null $username Username to look up (nullable)
     * @return string|null Team name if found, League::FREE_AGENTS_TEAM_NAME if username is empty, or null if not found
     */
    public function getTeamnameFromUsername(?string $username): ?string
    {
        if ($username === null || $username === '') {
            return League::FREE_AGENTS_TEAM_NAME;
        }

        $override = \Utilities\TestCookieOverrides::getTeamOverride();
        if ($override !== null) {
            return $override;
        }

        // Primary: check ibl_team_info (authoritative for real GMs)
        /** @var array{team_name: string}|null $result */
        $result = $this->fetchOne(
            "SELECT team_name FROM ibl_team_info WHERE gm_username = ? LIMIT 1",
            "s",
            $username
        );

        return $result !== null ? $result['team_name'] : null;
    }

    /**
     * Gets the GM username associated with a team name
     *
     * @param string $teamName Team name to look up
     * @return string|null GM username or null if not found
     */
    public function getUsernameFromTeamname(string $teamName): ?string
    {
        /** @var array{gm_username: ?string}|null $result */
        $result = $this->fetchOne(
            "SELECT gm_username FROM ibl_team_info WHERE team_name = ? LIMIT 1",
            "s",
            $teamName
        );

        return $result !== null ? $result['gm_username'] : null;
    }

    /**
     * Gets complete team information by team name
     *
     * @param string $teamName Team name to look up
     * @return TeamInfoRow|null Team information or null if not found
     */
    public function getTeamByName(string $teamName): ?array
    {
        /** @var TeamInfoRow|null */
        return $this->fetchOne(
            "SELECT * FROM ibl_team_info WHERE team_name = ?",
            "s",
            $teamName
        );
    }

    /**
     * Gets team ID from team name
     * 
     * @param string $teamName Team name to look up
     * @return int|null Team ID or null if not found
     */
    public function getTidFromTeamname(string $teamName): ?int
    {
        /** @var array{teamid: int}|null $result */
        $result = $this->fetchOne(
            "SELECT teamid FROM ibl_team_info WHERE team_name = ? LIMIT 1",
            "s",
            $teamName
        );

        return $result !== null ? ($result['teamid'] ?? 0) : null;
    }

    /**
     * Gets team name from team ID
     * 
     * @param int $teamid Team ID to look up
     * @return string|null Team name or null if not found
     */
    public function getTeamnameFromTeamID(int $teamid): ?string
    {
        /** @var array{team_name: string}|null $result */
        $result = $this->fetchOne(
            "SELECT team_name FROM ibl_team_info WHERE teamid = ? LIMIT 1",
            "i",
            $teamid
        );

        return $result !== null ? $result['team_name'] : null;
    }

    /**
     * Gets Discord ID for a team
     *
     * @param string $teamName Team name to look up
     * @return int|null Discord ID or null if not found
     */
    public function getTeamDiscordID(string $teamName): ?int
    {
        /** @var array{discord_id: int|string|null}|null $result */
        $result = $this->fetchOne(
            "SELECT `discord_id` FROM `ibl_team_info` WHERE `team_name` = ? LIMIT 1",
            "s",
            $teamName
        );

        if ($result === null) {
            return null;
        }

        $discordId = $result['discord_id'] ?? null;
        return $discordId !== null ? (int) $discordId : null;
    }

    /**
     * Gets complete player information by player ID
     *
     * @param int $playerID Player ID to look up
     * @return PlayerRow|null Player information or null if not found
     */
    public function getPlayerByID(int $playerID): ?array
    {
        /** @var PlayerRow|null */
        return $this->fetchOne(
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.teamid = t.teamid
            WHERE p.pid = ?",
            "i",
            $playerID
        );
    }

    /**
     * Gets player ID from player name
     * 
     * @param string $playerName Player name to look up
     * @return int|null Player ID or null if not found
     */
    public function getPlayerIDFromPlayerName(string $playerName): ?int
    {
        /** @var array{pid: int}|null $result */
        $result = $this->fetchOne(
            "SELECT pid FROM ibl_plr WHERE name = ? LIMIT 1",
            "s",
            $playerName
        );

        return $result !== null ? ($result['pid'] ?? null) : null;
    }

    /**
     * Gets complete player information by player name
     *
     * @param string $playerName Player name to look up
     * @return PlayerRow|null Player information or null if not found
     */
    public function getPlayerByName(string $playerName): ?array
    {
        /** @var PlayerRow|null */
        return $this->fetchOne(
            "SELECT p.*, t.team_name AS teamname, t.color1, t.color2
            FROM ibl_plr p
            LEFT JOIN ibl_team_info t ON p.teamid = t.teamid
            WHERE p.name = ?",
            "s",
            $playerName
        );
    }

    /**
     * Gets all real teams (excludes Free Agents, All-Star teams, Rookies, etc.)
     *
     * @param string $orderBy SQL ORDER BY clause (must be a whitelisted column name)
     * @return list<TeamInfoRow>
     */
    public function getAllRealTeams(string $orderBy = 'team_name ASC'): array
    {
        /** @var list<TeamInfoRow> */
        return $this->fetchAllRealTeams($orderBy);
    }

    /**
     * Gets total salary for a team for the current year
     *
     * @param string $teamName Team name
     * @return int Total salary in thousands
     */
    public function getTeamTotalSalary(string $teamName): int
    {
        /** @var array{total_salary: int|null}|null $result */
        $result = $this->fetchOne(
            "SELECT SUM(current_salary) AS total_salary
            FROM vw_current_salary
            WHERE teamname = ?",
            "s",
            $teamName
        );

        return (int) ($result['total_salary'] ?? 0);
    }

    /**
     * Gets total salary commitment for next season for a team
     *
     * @param string $teamName Team name
     * @return int Next year total salary in thousands
     */
    public function getTeamNextYearSalary(string $teamName): int
    {
        /** @var array{total_salary: int|null}|null $result */
        $result = $this->fetchOne(
            "SELECT SUM(next_year_salary) AS total_salary
            FROM vw_current_salary
            WHERE teamname = ?",
            "s",
            $teamName
        );

        return (int) ($result['total_salary'] ?? 0);
    }

    /**
     * Gets salary commitment at a position for next season, excluding a specific player
     *
     * @param string $teamName Team name
     * @param string $position Position (PG, SG, etc.)
     * @param int $excludePlayerID Player ID to exclude
     * @return int Next year salary commitment at position in thousands
     */
    public function getPositionSalaryCommitmentNextYear(string $teamName, string $position, int $excludePlayerID): int
    {
        /** @var array{total_salary: int|null}|null $result */
        $result = $this->fetchOne(
            "SELECT SUM(next_year_salary) AS total_salary
            FROM vw_current_salary
            WHERE teamname = ?
              AND pos = ?
              AND pid != ?",
            "ssi",
            $teamName,
            $position,
            $excludePlayerID
        );

        return (int) ($result['total_salary'] ?? 0);
    }

    /**
     * Gets both current and next-year salary totals for a team in a single query
     *
     * @param string $teamName Team name
     * @return array{current: int, nextYear: int} Salary totals in thousands
     */
    public function getTeamSalarySummary(string $teamName): array
    {
        /** @var array{current_salary: int|null, next_year_salary: int|null}|null $result */
        $result = $this->fetchOne(
            "SELECT SUM(current_salary) AS current_salary, SUM(next_year_salary) AS next_year_salary
            FROM vw_current_salary
            WHERE teamname = ?",
            "s",
            $teamName
        );

        return [
            'current' => (int) ($result['current_salary'] ?? 0),
            'nextYear' => (int) ($result['next_year_salary'] ?? 0),
        ];
    }

    /**
     * Gets cap space for next season for a team
     *
     * @param string $teamName Team name
     * @return int Cap space in thousands
     */
    public function getTeamCapSpaceNextSeason(string $teamName): int
    {
        return League::HARD_CAP_MAX - $this->getTeamNextYearSalary($teamName);
    }
}
