<?php

declare(strict_types=1);

namespace Services;

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
 * @phpstan-type UserRow array{user_id: int, username: string, user_email: string, user_ibl_team: string, name: string, date_started: string, discordID: ?int, user_password: string, user_level: int, user_active: ?int, ...}
 * @phpstan-type TeamInfoRow array{teamid: int, team_city: string, team_name: string, color1: string, color2: string, arena: string, owner_name: string, owner_email: string, discordID: ?int, formerly_known_as: ?string, Used_Extension_This_Chunk: int, Used_Extension_This_Season: ?int, HasMLE: int, HasLLE: int, ...}
 * @phpstan-type PlayerRow array{pid: int, name: string, nickname: ?string, age: ?int, tid: int, teamname: ?string, pos: string, sta: ?int, exp: ?int, bird: ?int, cy: ?int, cyt: ?int, cy1: ?int, cy2: ?int, cy3: ?int, cy4: ?int, cy5: ?int, cy6: ?int, ordinal: ?int, active: ?int, injured: ?int, retired: ?int, droptime: ?int, stats_gs: ?int, stats_gm: ?int, stats_min: ?int, stats_fgm: ?int, stats_fga: ?int, stats_ftm: ?int, stats_fta: ?int, stats_3gm: ?int, stats_3ga: ?int, stats_orb: ?int, stats_drb: ?int, stats_ast: ?int, stats_stl: ?int, stats_to: ?int, stats_blk: ?int, stats_pf: ?int, sh_pts: ?int, sh_reb: ?int, sh_ast: ?int, sh_stl: ?int, sh_blk: ?int, s_dd: ?int, s_td: ?int, sp_pts: ?int, sp_reb: ?int, sp_ast: ?int, sp_stl: ?int, sp_blk: ?int, ch_pts: ?int, ch_reb: ?int, ch_ast: ?int, ch_stl: ?int, ch_blk: ?int, c_dd: ?int, c_td: ?int, cp_pts: ?int, cp_reb: ?int, cp_ast: ?int, cp_stl: ?int, cp_blk: ?int, car_gm: ?int, car_min: ?int, car_fgm: ?int, car_fga: ?int, car_ftm: ?int, car_fta: ?int, car_tgm: ?int, car_tga: ?int, car_orb: ?int, car_drb: ?int, car_reb: ?int, car_ast: ?int, car_stl: ?int, car_to: ?int, car_blk: ?int, car_pf: ?int, r_fga: ?int, r_fgp: ?int, r_fta: ?int, r_ftp: ?int, r_tga: ?int, r_tgp: ?int, r_orb: ?int, r_drb: ?int, r_ast: ?int, r_stl: ?int, r_to: ?int, r_blk: ?int, r_foul: ?int, oo: ?int, od: ?int, do: ?int, dd: ?int, po: ?int, pd: ?int, to: ?int, td: ?int, Clutch: ?int, Consistency: ?int, talent: ?int, skill: ?int, intangibles: ?int, loyalty: ?int, playingTime: ?int, winner: ?int, tradition: ?int, security: ?int, draftround: ?int, draftedby: ?string, draftedbycurrentname: ?string, draftyear: ?int, draftpickno: ?int, htft: ?string, htin: ?string, wt: ?string, college: ?string, dc_PGDepth: ?int, dc_SGDepth: ?int, dc_SFDepth: ?int, dc_PFDepth: ?int, dc_CDepth: ?int, dc_active: ?int, dc_minutes: ?int, dc_of: ?int, dc_df: ?int, dc_oi: ?int, dc_di: ?int, dc_bh: ?int, ...}
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
            "SELECT * FROM nuke_users WHERE username = ?",
            "s",
            $username
        );
    }

    /**
     * Gets the team name associated with a username
     * 
     * @param string|null $username Username to look up (nullable)
     * @return string|null Team name if found, "Free Agents" if username is empty, or null if username not found
     */
    public function getTeamnameFromUsername(?string $username): ?string
    {
        if ($username === null || $username === '') {
            return "Free Agents";
        }
        
        /** @var array{user_ibl_team: string}|null $result */
        $result = $this->fetchOne(
            "SELECT user_ibl_team FROM nuke_users WHERE username = ? LIMIT 1",
            "s",
            $username
        );

        return $result !== null ? $result['user_ibl_team'] : null;
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
     * @param int $teamID Team ID to look up
     * @return string|null Team name or null if not found
     */
    public function getTeamnameFromTeamID(int $teamID): ?string
    {
        /** @var array{team_name: string}|null $result */
        $result = $this->fetchOne(
            "SELECT team_name FROM ibl_team_info WHERE teamid = ? LIMIT 1",
            "i",
            $teamID
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
        /** @var array{discordID: int|null}|null $result */
        $result = $this->fetchOne(
            "SELECT discordID FROM ibl_team_info WHERE team_name = ? LIMIT 1",
            "s",
            $teamName
        );

        return $result !== null ? ($result['discordID'] ?? null) : null;
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
            "SELECT * FROM ibl_plr WHERE pid = ?",
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
            "SELECT * FROM ibl_plr WHERE name = ?",
            "s",
            $playerName
        );
    }

    /**
     * Gets total salary for a team for the current year
     * 
     * @param string $teamName Team name
     * @return int Total salary in thousands
     */
    public function getTeamTotalSalary(string $teamName): int
    {
        $players = $this->fetchAll(
            "SELECT * FROM ibl_plr WHERE teamname = ? AND retired = 0",
            "s",
            $teamName
        );
        
        $totalSalary = 0;
        foreach ($players as $player) {
            /** @var array{cy: int|null, cy1: int|null, cy2: int|null, cy3: int|null, cy4: int|null, cy5: int|null, cy6: int|null} $player */
            $cy = $player['cy'] ?? 0;
            $contractYearField = "cy$cy";
            if (isset($player[$contractYearField])) {
                /** @var int $salary */
                $salary = $player[$contractYearField];
                $totalSalary += $salary;
            }
        }
        
        return $totalSalary;
    }
}
