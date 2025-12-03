<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * TeamRepositoryInterface - Contract for Team data access operations
 * 
 * Defines methods for querying team information from multiple database tables:
 * power rankings, standings, banners, history, rosters, and playoff results.
 * 
 * All methods use prepared statements and safe escaping internally.
 * All methods return database result objects or arrays, never throw exceptions.
 */
interface TeamRepositoryInterface
{
    /**
     * Get team power ranking data
     * 
     * @param string $teamName Team name to search for (e.g., "Chicago Bulls")
     * @return array<string, mixed>|null Complete row from ibl_power table or null if not found
     * 
     * **Return Structure (if found):**
     * - Team: Team name
     * - Rank: Power ranking position
     * - Division: Team's division
     * - Conference: Team's conference
     * - Wins, Losses, gb (games behind), etc.
     * 
     * **Behaviors:**
     * - Returns null if team not found
     * - Never throws exceptions
     * - All queries use safe escaping
     */
    public function getTeamPowerData(string $teamName): ?array;

    /**
     * Get all teams in a specific division with standings
     * 
     * @param string $division Division name (e.g., "Atlantic", "Central", "Pacific")
     * @return mixed Database result object (use sql_numrows/sql_fetch_assoc to iterate)
     * 
     * **Return Structure:**
     * Each row represents one team in the division with:
     * - Team, Rank, Wins, Losses, gb, etc.
     * - Ordered by gb DESC (games behind descending)
     * 
     * **Behaviors:**
     * - Returns database result resource
     * - Results ordered by gb DESC (closest to first place first)
     * - Never throws exceptions
     */
    public function getDivisionStandings(string $division): mixed;

    /**
     * Get all teams in a specific conference with standings
     * 
     * @param string $conference Conference name (e.g., "Eastern", "Western")
     * @return mixed Database result object (use sql_numrows/sql_fetch_assoc to iterate)
     * 
     * **Return Structure:**
     * Each row represents one team in the conference with standings data
     * - Ordered by gb DESC (games behind descending)
     * 
     * **Behaviors:**
     * - Returns database result resource
     * - Results ordered by gb DESC
     * - Never throws exceptions
     */
    public function getConferenceStandings(string $conference): mixed;

    /**
     * Get championship banners (championships won) for a team
     * 
     * @param string $teamName Team name to search for
     * @return mixed Database result object with championship records
     * 
     * **Return Structure:**
     * Each row represents one championship won:
     * - currentname: Team name that won the championship
     * - year: Year championship was won
     * - (Other championship-related fields from ibl_banners)
     * - Ordered by year ASC (oldest first)
     * 
     * **Behaviors:**
     * - Returns database result resource
     * - Results ordered chronologically (year ASC)
     * - Never throws exceptions
     */
    public function getChampionshipBanners(string $teamName): mixed;

    /**
     * Get GM history for a team
     * 
     * Records match format: "Owner Name (Team Name)"
     * This is how the ibl_gm_history table stores the data.
     * 
     * @param string $ownerName Owner/GM name (e.g., "User Name")
     * @param string $teamName Team name (e.g., "Chicago Bulls")
     * @return mixed Database result object with GM history records
     * 
     * **Return Structure:**
     * Each row represents one GM tenure:
     * - name: "Owner Name (Team Name)" format
     * - year: Year record applies to
     * - Other history fields from ibl_gm_history
     * - Ordered by year ASC
     * 
     * **Behaviors:**
     * - Searches using "Owner Name (Team Name)" format
     * - Results ordered chronologically (year ASC)
     * - Uses LIKE operator (case-insensitive)
     * - Never throws exceptions
     */
    public function getGMHistory(string $ownerName, string $teamName): mixed;

    /**
     * Get team accomplishments and awards
     * 
     * @param string $teamName Team name to search for
     * @return mixed Database result object with team accomplishments
     * 
     * **Return Structure:**
     * Each row represents one award/accomplishment:
     * - name: Team name
     * - year: Year award was won/achieved
     * - Other award details from ibl_team_awards
     * - Ordered by year DESC (most recent first)
     * 
     * **Behaviors:**
     * - Results ordered reverse chronologically (year DESC)
     * - Uses LIKE operator (case-insensitive)
     * - Never throws exceptions
     */
    public function getTeamAccomplishments(string $teamName): mixed;

    /**
     * Get regular season win/loss history for a team
     * 
     * @param string $teamName Team name to search for
     * @return mixed Database result object with season records
     * 
     * **Return Structure:**
     * Each row represents one season:
     * - currentname: Team name
     * - year: Season year
     * - Wins, Losses, percentage, etc.
     * - Ordered by year DESC (most recent first)
     * 
     * **Behaviors:**
     * - Results ordered reverse chronologically (year DESC)
     * - Uses LIKE operator (case-insensitive)
     * - Never throws exceptions
     */
    public function getRegularSeasonHistory(string $teamName): mixed;

    /**
     * Get HEAT tournament results for a team
     * 
     * HEAT is a special tournament in the IBL.
     * 
     * @param string $teamName Team name to search for
     * @return mixed Database result object with HEAT tournament records
     * 
     * **Return Structure:**
     * Each row represents one HEAT tournament season:
     * - currentname: Team name
     * - year: Year of tournament
     * - Wins, Losses, results, etc.
     * - Ordered by year DESC (most recent first)
     * 
     * **Behaviors:**
     * - Results ordered reverse chronologically (year DESC)
     * - Uses LIKE operator (case-insensitive)
     * - Never throws exceptions
     */
    public function getHEATHistory(string $teamName): mixed;

    /**
     * Get playoff results for all teams
     * 
     * @return mixed Database result object with all playoff records
     * 
     * **Return Structure:**
     * Each row represents playoff results for a team in a season:
     * - Team name, year, results, round reached, etc.
     * - Ordered by year DESC (most recent first)
     * 
     * **Behaviors:**
     * - Results ordered reverse chronologically (year DESC)
     * - Includes all teams and all seasons
     * - Never throws exceptions
     */
    public function getPlayoffResults(): mixed;

    /**
     * Get free agency roster for a team
     * 
     * Returns players on team whose contract year (cyt) differs from
     * current contract year (cy), meaning they have an expiring contract
     * and are approaching free agency.
     * 
     * @param int $teamID Team ID (teamid from ibl_team_info)
     * @return mixed Database result object with player roster
     * 
     * **Return Structure:**
     * Each row is a complete ibl_plr table row:
     * - pid, name, pos, tid, cy, cyt, cy1-cy6, ordinal, etc.
     * - Ordered by: draft picks first (ordinal <= 960), then by name A-Z
     * - Excludes retired players (retired = 0)
     * - Includes only players with expiring contracts (cyt != cy)
     * 
     * **Behaviors:**
     * - Filtered to active players only (retired = 0)
     * - Filtered to expiring contracts (cyt != cy)
     * - Results ordered by ordinal priority, then alphabetically
     * - Returns entire ibl_plr rows (all columns available)
     * - Never throws exceptions
     */
    public function getFreeAgencyRoster(int $teamID): mixed;

    /**
     * Get current season roster for a team
     * 
     * Returns all active players under contract with the team.
     * 
     * @param int $teamID Team ID (teamid from ibl_team_info)
     * @return mixed Database result object with complete player roster
     * 
     * **Return Structure:**
     * Each row is a complete ibl_plr table row:
     * - pid, name, pos, tid, cy, cyt, cy1-cy6, ordinal, etc.
     * - Ordered by: draft picks first (ordinal <= 960), then by name A-Z
     * - Excludes retired players (retired = 0)
     * 
     * **Behaviors:**
     * - Returns all active players on team regardless of contract status
     * - Results ordered by ordinal priority, then alphabetically
     * - Returns entire ibl_plr rows (all columns available)
     * - Never throws exceptions
     */
    public function getRosterUnderContract(int $teamID): mixed;

    /**
     * Get free agents available for signing
     * 
     * Free agents are players with ordinal > 959 (not on any team).
     * Can optionally filter to show only players in active free agency period.
     * 
     * @param bool $includeFreeAgencyActive If true, only show players with expiring contracts (cyt != cy)
     *                                       If false, show all free agents regardless of contract status
     * 
     * @return mixed Database result object with free agent roster
     * 
     * **Return Structure:**
     * Each row is a complete ibl_plr table row:
     * - pid, name, pos, tid (should be 0), cy, cyt, ordinal, etc.
     * - Ordered by ordinal ASC
     * - Excludes retired players (retired = 0)
     * 
     * **Behaviors:**
     * - Returns only players with ordinal > 959 (free agents)
     * - If includeFreeAgencyActive=true: also filters cyt != cy (actively shopping)
     * - If includeFreeAgencyActive=false: returns all free agents
     * - Results ordered by ordinal
     * - Returns entire ibl_plr rows (all columns available)
     * - Never throws exceptions
     */
    public function getFreeAgents(bool $includeFreeAgencyActive = false): mixed;

    /**
     * Get entire league roster
     * 
     * Returns all active players currently rostered on teams (not free agents).
     * 
     * @return mixed Database result object with all league players
     * 
     * **Return Structure:**
     * Each row is a complete ibl_plr table row:
     * - pid, name, pos, tid, ordinal, etc.
     * - Ordered by ordinal ASC
     * - Excludes retired players (retired = 0)
     * - Excludes "Buyouts" entries
     * 
     * **Behaviors:**
     * - Returns only active players (retired = 0)
     * - Excludes "Buyouts" placeholder entries
     * - Results ordered by ordinal
     * - Returns entire ibl_plr rows (all columns available)
     * - Never throws exceptions
     */
    public function getEntireLeagueRoster(): mixed;

    /**
     * Get historical roster for a team in a specific season
     * 
     * @param int $teamID Team ID
     * @param string $year Season year (e.g., "2023", "2024")
     * @return mixed Database result object with historical player roster
     * 
     * **Return Structure:**
     * Each row is from ibl_hist table:
     * - pid, name, pos, tid, teamid, year, and all historical stat fields
     * - Ordered by name ASC
     * 
     * **Behaviors:**
     * - Queries ibl_hist table (historical data, not current)
     * - Filters to specific team and year
     * - Results ordered alphabetically by name
     * - Never throws exceptions
     */
    public function getHistoricalRoster(int $teamID, string $year): mixed;
}
