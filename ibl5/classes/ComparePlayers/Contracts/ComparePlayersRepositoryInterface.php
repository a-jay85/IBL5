<?php

declare(strict_types=1);

namespace ComparePlayers\Contracts;

/**
 * ComparePlayersRepositoryInterface - Contract for player comparison data access
 * 
 * Defines database operations for retrieving player data for comparison purposes.
 * All methods use prepared statements to prevent SQL injection.
 * Handles both modern (prepared statements) and legacy (sql_*) database interfaces.
 */
interface ComparePlayersRepositoryInterface
{
    /**
     * Get all active player names for autocomplete
     * 
     * Retrieves a simple array of all player names where ordinal != 0 (active roster).
     * Used to populate the JavaScript autocomplete dropdown.
     * Results are ordered alphabetically by player name.
     * 
     * @return array<int, string> Array of player names
     *     - Index: numeric (0, 1, 2, ...)
     *     - Value: player name (e.g., "Michael Jordan")
     * 
     * IMPORTANT BEHAVIORS:
     *  - Only includes players with ordinal != 0 (excludes free agents)
     *  - Returns empty array if no players found
     *  - Names returned as-is from database (may contain special characters)
     *  - Sorted alphabetically (ASC)
     *  - NEVER throws exceptions
     * 
     * Examples:
     *  $names = $repo->getAllPlayerNames();
     *  // Returns ['Allen Iverson', 'Kobe Bryant', 'Tim Duncan', ...]
     *  
     *  // Used in JavaScript autocomplete:
     *  var availableTags = ["Allen Iverson", "Kobe Bryant", ...];
     */
    public function getAllPlayerNames(): array;

    /**
     * Get complete player data by exact name match
     * 
     * Retrieves all columns from ibl_plr table for a single player.
     * Uses exact name matching (case-sensitive).
     * Returns a single row or null if player not found.
     * 
     * @param string $playerName Exact player name to search for
     * @return array<string, mixed>|null Complete player row or null if not found
     * 
     * RETURNED FIELDS (selection of key fields):
     *  Basic Info:
     *   - pid: Player ID (int)
     *   - name: Player name (string)
     *   - pos: Position (string: PG/SG/SF/PF/C)
     *   - age: Current age (int)
     *   - teamname: Current team (string)
     * 
     *  Current Ratings (r_* prefix):
     *   - r_fga, r_fgp: 2-point attempt/percentage ratings (int)
     *   - r_fta, r_ftp: Free throw attempt/percentage ratings (int)
     *   - r_tga, r_tgp: 3-point attempt/percentage ratings (int)
     *   - r_orb, r_drb: Rebound ratings (int)
     *   - r_ast, r_stl, r_to, r_blk, r_foul: Other stat ratings (int)
     * 
     *  Skill Ratings:
     *   - oo, do, po, to: Offense ratings (int)
     *   - od, dd, pd, td: Defense ratings (int)
     * 
     *  Current Season Stats (stats_* prefix):
     *   - stats_gm, stats_gs: Games played/started (int)
     *   - stats_min: Total minutes (int)
     *   - stats_fgm, stats_fga: Field goals made/attempted (int)
     *   - stats_ftm, stats_fta: Free throws made/attempted (int)
     *   - stats_3gm, stats_3ga: 3-pointers made/attempted (int)
     *   - stats_orb, stats_drb: Offensive/defensive rebounds (int)
     *   - stats_ast, stats_stl, stats_to, stats_blk, stats_pf: Other stats (int)
     * 
     *  Career Stats (car_* prefix):
     *   - car_gm: Career games (int)
     *   - car_min: Career minutes (int)
     *   - car_fgm, car_fga: Career field goals (int)
     *   - car_ftm, car_fta: Career free throws (int)
     *   - car_tgm, car_tga: Career 3-pointers (int)
     *   - car_orb, car_drb, car_reb: Career rebounds (int)
     *   - car_ast, car_stl, car_to, car_blk, car_pf: Career stats (int)
     *   - car_pts: Career points (int)
     * 
     * IMPORTANT BEHAVIORS:
     *  - Uses prepared statements (SQL injection safe)
     *  - Exact name match required (case-sensitive)
     *  - Returns ALL columns from ibl_plr table
     *  - Returns null if player name doesn't exist
     *  - LIMIT 1 ensures single row returned
     *  - NEVER throws exceptions
     * 
     * SECURITY:
     *  - Player name is parameterized (prevents SQL injection)
     *  - Uses prepared statements in modern DB
     *  - Uses DatabaseService::escapeString() in legacy DB
     * 
     * Examples:
     *  $player = $repo->getPlayerByName('Michael Jordan');
     *  // Returns ['pid' => 123, 'name' => 'Michael Jordan', 'pos' => 'SG', ...]
     *  
     *  $player = $repo->getPlayerByName('NonExistent Player');
     *  // Returns null
     *  
     *  $player = $repo->getPlayerByName("O'Neal"); // Handles apostrophes safely
     *  // Returns player data (no SQL injection)
     */
    public function getPlayerByName(string $playerName): ?array;
}
