<?php

declare(strict_types=1);

namespace Draft\Contracts;

/**
 * DraftRepositoryInterface - Contract for draft database operations
 *
 * Defines all database access methods for draft operations including:
 * - Draft pick queries and updates
 * - Player draft status checking
 * - Draft class roster management
 * - Player creation from draft selections
 *
 * All methods use prepared statements internally – SQL injection is prevented.
 */
interface DraftRepositoryInterface
{
    /**
     * Get the current draft selection for a specific pick
     *
     * @param int $draftRound The draft round (1-indexed)
     * @param int $draftPick The pick number within the round
     * @return string|null The player name already selected for this pick, or null if pick is available
     *
     * IMPORTANT BEHAVIORS:
     *  - Returns null if the pick has not been selected (empty string stored as null)
     *  - Returns the player name if the pick is filled
     *  - Uses prepared statements for safety
     *  - NEVER throws exceptions – returns null on error instead
     *  - Case-sensitive player name comparison
     *
     * Examples:
     *  $selection = $repo->getCurrentDraftSelection(1, 5);
     *  // Returns 'John Smith' if pick 5 in round 1 is filled
     *  // Returns null if pick is available
     */
    public function getCurrentDraftSelection(int $draftRound, int $draftPick): ?string;

    /**
     * Update the draft table with a player selection
     *
     * Records the drafted player and timestamp in the ibl_draft table for a specific pick.
     *
     * @param string $playerName The name of the drafted player
     * @param string $date The date/time of the selection (format: 'Y-m-d H:i:s')
     * @param int $draftRound The draft round (1-indexed)
     * @param int $draftPick The pick number within the round
     * @return bool True if update succeeded, false otherwise
     *
     * IMPORTANT BEHAVIORS:
     *  - Updates both player name AND date timestamp in ibl_draft table
     *  - Uses prepared statements for safety
     *  - Returns false if database error occurs
     *  - NEVER throws exceptions
     *
     * Side Effects:
     *  - Modifies ibl_draft table (single row update)
     *
     * Examples:
     *  $success = $repo->updateDraftTable('John Smith', '2025-06-15 10:30:00', 1, 5);
     *  // Returns true on success, false on database error
     */
    public function updateDraftTable(string $playerName, string $date, int $draftRound, int $draftPick): bool;

    /**
     * Update the rookie table to mark player as drafted
     *
     * Records which team drafted a player in the ibl_draft_class table.
     * The ibl_draft_class table is the source data for available draft prospects.
     *
     * @param string $playerName The name of the player being drafted
     * @param string $teamName The name of the team that drafted the player
     * @return bool True if update succeeded, false otherwise
     *
     * IMPORTANT BEHAVIORS:
     *  - Sets 'drafted' flag to 1 and records team name in ibl_draft_class
     *  - Uses prepared statements for safety
     *  - Returns false if database error occurs (e.g., player not found in ibl_draft_class)
     *  - NEVER throws exceptions
     *
     * Side Effects:
     *  - Modifies ibl_draft_class table (single row update)
     *  - Prevents same player from being drafted again (via drafted = 1)
     *
     * Examples:
     *  $success = $repo->updateRookieTable('John Smith', 'New York');
     *  // Marks John Smith as drafted by New York in ibl_draft_class
     *  // Returns true on success, false if player not found
     */
    public function updateRookieTable(string $playerName, string $teamName): bool;

    /**
     * Create a new player entry in ibl_plr from ibl_draft_class data
     *
     * When a player is drafted, this creates a new ibl_plr record with a temporary
     * PID in the range 90000+. This allows drafted players to appear in rosters
     * immediately. When plrParser.php processes an updated .plr file, it merges
     * the data using the player name and creates permanent JSB-assigned PIDs.
     *
     * Maps columns from ibl_draft_class to ibl_plr:
     *  - offo/offd/offp/offt -> oo/od/po/to (offensive ratings)
     *  - defo/defd/defp/deft -> do/dd/pd/td (defensive ratings)
     *  - age, sta, tal, skl, int -> age, sta, talent, skill, intangibles
     *
     * @param string $playerName The name of the drafted player (from ibl_draft_class)
     * @param string $teamName The name of the team that drafted the player
     * @return bool True if player created successfully, false otherwise
     *
     * IMPORTANT BEHAVIORS:
     *  - Generates next available PID in 90000+ range (temporary)
     *  - Truncates player name to 32 characters (ibl_plr.name column size)
     *  - Maps draft class ratings to ibl_plr rating columns
     *  - Sets all contract years (cy, cyt) to 0 (no contract yet)
     *  - Sets bird and exp to 0 (will be updated by plrParser)
     *  - Returns false if team not found or database error
     *  - NEVER throws exceptions
     *
     * Side Effects:
     *  - Inserts new row into ibl_plr table
     *  - May affect MAX(pid) calculations for next draft pick PID
     *
     * Database Dependencies:
     *  - Requires team to exist in ibl_team_info (retrieves tid)
     *  - Requires player to exist in ibl_draft_class (retrieves ratings)
     *
     * Examples:
     *  $success = $repo->createPlayerFromDraftClass('John Smith', 'New York');
     *  // Creates row in ibl_plr with PID 90001 (or next available)
     *  // Returns true on success, false if team/player not found
     */
    public function createPlayerFromDraftClass(string $playerName, string $teamName): bool;

    /**
     * Check if a player has already been drafted
     *
     * @param string $playerName The name of the player to check
     * @return bool True if player has been drafted (drafted = 1), false otherwise
     *
     * IMPORTANT BEHAVIORS:
     *  - Checks 'drafted' flag in ibl_draft_class table
     *  - Drafted flag value 1 or "1" both return true
     *  - Returns false if player not found in draft class
     *  - Uses prepared statements for safety
     *  - NEVER throws exceptions
     *
     * Examples:
     *  $isDrafted = $repo->isPlayerAlreadyDrafted('John Smith');
     *  // Returns true if John Smith already drafted
     *  // Returns false if available or doesn't exist in ibl_draft_class
     */
    public function isPlayerAlreadyDrafted(string $playerName): bool;

    /**
     * Get the next team on the clock (team with the next available pick)
     *
     * @return string|null The team name with the next available pick, or null if draft is complete
     *
     * IMPORTANT BEHAVIORS:
     *  - Finds first unpicked position (player field is empty)
     *  - Orders by round ASC, then pick ASC (lowest round/pick first)
     *  - Returns null if no unpicked positions exist (draft complete)
     *  - NEVER throws exceptions
     *
     * Examples:
     *  $team = $repo->getNextTeamOnClock();
     *  // Returns 'New York' if they own next pick
     *  // Returns null if draft is complete (all picks filled)
     */
    public function getNextTeamOnClock(): ?string;

    /**
     * Get all players in the draft class roster
     *
     * Retrieves the complete list of available draft prospects from ibl_draft_class.
     * Used for rendering player tables and searching available players.
     *
     * @return array<int, array<string, mixed>> Array of player records, each row is array<string, mixed>
     *
     * IMPORTANT BEHAVIORS:
     *  - Returns all players from ibl_draft_class table
     *  - Ordered by drafted ASC (undrafted first), then name ASC (alphabetical)
     *  - Returns empty array if no players in draft class
     *  - Each row includes all columns from ibl_draft_class (drafted, name, pos, team, ratings, etc.)
     *  - NEVER throws exceptions
     *
     * Examples:
     *  $players = $repo->getAllDraftClassPlayers();
     *  // Returns [
     *  //   ['name' => 'John Smith', 'pos' => 'PG', 'drafted' => 0, ...],
     *  //   ['name' => 'Jane Doe', 'pos' => 'C', 'drafted' => 1, ...],
     *  //   ...
     *  // ]
     */
    public function getAllDraftClassPlayers(): array;

    /**
     * Get the current draft pick information (next available pick)
     *
     * Combines team, round, and pick information for the next selection.
     * Used to determine whose turn it is and what round/pick number.
     *
     * @return array{team: string, round: int, pick: int}|null Array with team/round/pick info, or null if draft complete
     *
     * IMPORTANT BEHAVIORS:
     *  - Finds first unpicked position (player field is empty)
     *  - Orders by round ASC, then pick ASC (lowest round/pick first)
     *  - Returns null if no unpicked positions exist (draft complete)
     *  - Each row includes: team name, round number, pick number (within round)
     *  - NEVER throws exceptions
     *
     * Return Value Structure (when not null):
     *  - team: string – the team name that owns this pick
     *  - round: int|string – the round number
     *  - pick: int|string – the pick number within the round (1-indexed)
     *
     * Examples:
     *  $pick = $repo->getCurrentDraftPick();
     *  // Returns ['team' => 'New York', 'round' => '1', 'pick' => '5']
     *  // Used to check whose turn it is
     *
     *  $pick = $repo->getCurrentDraftPick();
     *  // Returns null if draft complete (all picks filled)
     */
    public function getCurrentDraftPick(): ?array;
}
