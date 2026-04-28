<?php

declare(strict_types=1);

namespace Player\Contracts;

use Player\Player;

/**
 * PlayerStatsInterface - Contract for player statistics data object
 *
 * Defines the interface for creating and populating player statistics objects
 * from various data sources (player ID, Player object, database rows, boxscore lines).
 *
 * @phpstan-type PlayerStatsRow array{
 *     pid: int, name: string, pos: string, retired: ?int,
 *     stats_gs: ?int, stats_gm: ?int, stats_min: ?int,
 *     stats_fgm: ?int, stats_fga: ?int, stats_ftm: ?int, stats_fta: ?int,
 *     stats_3gm: ?int, stats_3ga: ?int,
 *     stats_orb: ?int, stats_drb: ?int,
 *     stats_ast: ?int, stats_stl: ?int, stats_tvr: ?int, stats_blk: ?int, stats_pf: ?int,
 *     sh_pts: ?int, sh_reb: ?int, sh_ast: ?int, sh_stl: ?int, sh_blk: ?int,
 *     s_dd: ?int, s_td: ?int,
 *     sp_pts: ?int, sp_reb: ?int, sp_ast: ?int, sp_stl: ?int, sp_blk: ?int,
 *     ch_pts: ?int, ch_reb: ?int, ch_ast: ?int, ch_stl: ?int, ch_blk: ?int,
 *     c_dd: ?int, c_td: ?int,
 *     cp_pts: ?int, cp_reb: ?int, cp_ast: ?int, cp_stl: ?int, cp_blk: ?int,
 *     car_gm: ?int, car_min: ?int, car_fgm: ?int, car_fga: ?int,
 *     car_ftm: ?int, car_fta: ?int, car_tgm: ?int, car_tga: ?int,
 *     car_orb: ?int, car_drb: ?int, car_reb: ?int,
 *     car_ast: ?int, car_stl: ?int, car_to: ?int, car_blk: ?int, car_pf: ?int,
 *     ...
 * }
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
