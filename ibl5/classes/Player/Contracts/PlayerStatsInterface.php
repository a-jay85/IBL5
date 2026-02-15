<?php

declare(strict_types=1);

namespace Player\Contracts;

use Player\Player;

/**
 * PlayerStatsInterface - Contract for player statistics data object
 * 
 * Defines the interface for creating and populating player statistics objects
 * from various data sources (player ID, Player object, database rows, boxscore lines).
 */
interface PlayerStatsInterface
{
    /**
     * Create a PlayerStats instance by loading player data by ID
     * 
     * @param \mysqli $db Database connection
     * @param int $playerID Player ID to load
     * @return self Populated PlayerStats instance
     */
    public static function withPlayerID(\mysqli $db, int $playerID): self;

    /**
     * Create a PlayerStats instance from a Player object
     * 
     * @param \mysqli $db Database connection
     * @param Player $player Player object to load stats for
     * @return self Populated PlayerStats instance
     */
    public static function withPlayerObject(\mysqli $db, Player $player): self;

    /**
     * Create a PlayerStats instance from a current player database row
     * 
     * @param \mysqli $db Database connection
     * @param array<string, mixed> $plrRow Raw database row from ibl_plr
     * @return self Populated PlayerStats instance
     */
    public static function withPlrRow(\mysqli $db, array $plrRow): self;

    /**
     * Create a PlayerStats instance from a historical player database row
     * 
     * @param \mysqli $db Database connection
     * @param array<string, mixed> $plrRow Raw database row from ibl_hist
     * @return self Populated PlayerStats instance
     */
    public static function withHistoricalPlrRow(\mysqli $db, array $plrRow): self;

    /**
     * Create a PlayerStats instance from a boxscore info line
     * 
     * Parses the fixed-width boxscore line format used in game data files.
     * 
     * @param \mysqli $db Database connection
     * @param string $playerInfoLine Fixed-width player info line from boxscore
     * @return self Populated PlayerStats instance
     */
    public static function withBoxscoreInfoLine(\mysqli $db, string $playerInfoLine): self;
}
