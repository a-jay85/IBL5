<?php

declare(strict_types=1);

namespace Player\Contracts;

use Player\Player;

/**
 * PlayerStatsInterface - Contract for player statistics value object
 * 
 * Defines how player statistics are accessed after loading from the database.
 * Provides both raw totals and calculated per-game/percentage values.
 */
interface PlayerStatsInterface
{
    /**
     * Create a PlayerStats instance by player ID
     * 
     * @param object $db Database connection (mysqli)
     * @param int $playerID Player ID to load stats for
     * @return self Hydrated PlayerStats instance
     */
    public static function withPlayerID(object $db, int $playerID): self;

    /**
     * Create a PlayerStats instance from a Player object
     * 
     * @param object $db Database connection (mysqli)
     * @param Player $player Player instance
     * @return self Hydrated PlayerStats instance
     */
    public static function withPlayerObject(object $db, Player $player): self;

    /**
     * Create a PlayerStats instance from a raw ibl_plr database row
     * 
     * @param object $db Database connection (mysqli)
     * @param array<string, mixed> $plrRow Database row from ibl_plr
     * @return self Hydrated PlayerStats instance
     */
    public static function withPlrRow(object $db, array $plrRow): self;

    /**
     * Create a PlayerStats instance from a historical ibl_hist row
     * 
     * @param object $db Database connection (mysqli)
     * @param array<string, mixed> $plrRow Database row from ibl_hist
     * @return self Hydrated PlayerStats instance
     */
    public static function withHistoricalPlrRow(object $db, array $plrRow): self;

    /**
     * Create a PlayerStats instance from a boxscore info line
     * 
     * @param object $db Database connection (mysqli)
     * @param string $playerInfoLine Fixed-width formatted boxscore line
     * @return self Hydrated PlayerStats instance
     */
    public static function withBoxscoreInfoLine(object $db, string $playerInfoLine): self;

    /**
     * Get the player ID
     * 
     * @return int|null Player ID or null if not loaded
     */
    public function getPlayerID(): ?int;

    /**
     * Get the player name
     * 
     * @return string|null Player name or null if not loaded
     */
    public function getName(): ?string;

    /**
     * Get the player position
     * 
     * @return string|null Position code (PG, SG, SF, PF, C) or null
     */
    public function getPosition(): ?string;

    /**
     * Check if the player is retired
     * 
     * @return int|null Retired status (0 = active, 1 = retired) or null
     */
    public function isRetired(): ?int;

    /**
     * Get season totals as an associative array
     * 
     * @return array{
     *     gamesStarted: int,
     *     gamesPlayed: int,
     *     minutes: int,
     *     fieldGoalsMade: int,
     *     fieldGoalsAttempted: int,
     *     freeThrowsMade: int,
     *     freeThrowsAttempted: int,
     *     threePointersMade: int,
     *     threePointersAttempted: int,
     *     offensiveRebounds: int,
     *     defensiveRebounds: int,
     *     totalRebounds: int,
     *     assists: int,
     *     steals: int,
     *     turnovers: int,
     *     blocks: int,
     *     personalFouls: int,
     *     points: int
     * }
     */
    public function getSeasonTotals(): array;

    /**
     * Get season per-game averages as an associative array
     * 
     * @return array{
     *     minutesPerGame: string,
     *     pointsPerGame: string,
     *     reboundsPerGame: string,
     *     assistsPerGame: string,
     *     stealsPerGame: string,
     *     blocksPerGame: string,
     *     turnoversPerGame: string,
     *     fieldGoalPercentage: string,
     *     freeThrowPercentage: string,
     *     threePointPercentage: string
     * }
     */
    public function getSeasonAverages(): array;

    /**
     * Get season highs as an associative array
     * 
     * @return array{
     *     points: int,
     *     rebounds: int,
     *     assists: int,
     *     steals: int,
     *     blocks: int,
     *     doubleDoubles: int,
     *     tripleDoubles: int
     * }
     */
    public function getSeasonHighs(): array;

    /**
     * Get career highs as an associative array
     * 
     * @return array{
     *     points: int,
     *     rebounds: int,
     *     assists: int,
     *     steals: int,
     *     blocks: int,
     *     doubleDoubles: int,
     *     tripleDoubles: int,
     *     playoffPoints: int,
     *     playoffRebounds: int,
     *     playoffAssists: int,
     *     playoffSteals: int,
     *     playoffBlocks: int
     * }
     */
    public function getCareerHighs(): array;

    /**
     * Get career totals as an associative array
     * 
     * @return array{
     *     gamesPlayed: int,
     *     minutesPlayed: int,
     *     fieldGoalsMade: int,
     *     fieldGoalsAttempted: int,
     *     freeThrowsMade: int,
     *     freeThrowsAttempted: int,
     *     threePointersMade: int,
     *     threePointersAttempted: int,
     *     offensiveRebounds: int,
     *     defensiveRebounds: int,
     *     totalRebounds: int,
     *     assists: int,
     *     steals: int,
     *     turnovers: int,
     *     blocks: int,
     *     personalFouls: int,
     *     points: int
     * }
     */
    public function getCareerTotals(): array;
}
