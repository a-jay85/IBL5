<?php

declare(strict_types=1);

namespace FreeAgencyPreview\Contracts;

/**
 * Repository interface for Free Agency Preview module.
 *
 * Provides method to retrieve upcoming free agents from the database.
 *
 * @phpstan-type ActivePlayerRow array{pid: int, teamid: int, name: string, teamname: string, pos: string, age: int, draftyear: int, exp: int, cy: int, cyt: int, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int, r_fga: int, r_fgp: int, r_fta: int, r_ftp: int, r_3ga: int, r_3gp: int, r_orb: int, r_drb: int, r_ast: int, r_stl: int, r_blk: int, r_tvr: int, r_foul: int, oo: int, r_drive_off: int, po: int, r_trans_off: int, od: int, dd: int, pd: int, td: int, loyalty: int, winner: int, playing_time: int, security: int, tradition: int, team_city: ?string, color1: ?string, color2: ?string}
 */
interface FreeAgencyPreviewRepositoryInterface
{
    /**
     * Get all active players ordered for free agency preview.
     *
     * @return list<ActivePlayerRow> Array of player data with contract and rating info
     */
    public function getActivePlayers(): array;
}
