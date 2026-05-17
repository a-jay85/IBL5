<?php

declare(strict_types=1);

namespace Services\Contracts;

/**
 * @phpstan-type PlayerRow array{pid: int, name: string, nickname: ?string, age: ?int, teamid: int, teamname: ?string, pos: string, stamina: ?int, exp: ?int, bird: ?int, cy: ?int, cyt: ?int, salary_yr1: ?int, salary_yr2: ?int, salary_yr3: ?int, salary_yr4: ?int, salary_yr5: ?int, salary_yr6: ?int, ordinal: ?int, injured: ?int, retired: ?int, droptime: ?int, stats_gs: ?int, stats_gm: ?int, stats_min: ?int, stats_fgm: ?int, stats_fga: ?int, stats_ftm: ?int, stats_fta: ?int, stats_3gm: ?int, stats_3ga: ?int, stats_orb: ?int, stats_drb: ?int, stats_ast: ?int, stats_stl: ?int, stats_tvr: ?int, stats_blk: ?int, stats_pf: ?int, sh_pts: ?int, sh_reb: ?int, sh_ast: ?int, sh_stl: ?int, sh_blk: ?int, s_dd: ?int, s_td: ?int, sp_pts: ?int, sp_reb: ?int, sp_ast: ?int, sp_stl: ?int, sp_blk: ?int, ch_pts: ?int, ch_reb: ?int, ch_ast: ?int, ch_stl: ?int, ch_blk: ?int, c_dd: ?int, c_td: ?int, cp_pts: ?int, cp_reb: ?int, cp_ast: ?int, cp_stl: ?int, cp_blk: ?int, car_gm: ?int, car_min: ?int, car_fgm: ?int, car_fga: ?int, car_ftm: ?int, car_fta: ?int, car_tgm: ?int, car_tga: ?int, car_orb: ?int, car_drb: ?int, car_reb: ?int, car_ast: ?int, car_stl: ?int, car_to: ?int, car_blk: ?int, car_pf: ?int, r_fga: ?int, r_fgp: ?int, r_fta: ?int, r_ftp: ?int, r_3ga: ?int, r_3gp: ?int, r_orb: ?int, r_drb: ?int, r_ast: ?int, r_stl: ?int, r_tvr: ?int, r_blk: ?int, r_foul: ?int, oo: ?int, od: ?int, r_drive_off: ?int, dd: ?int, po: ?int, pd: ?int, r_trans_off: ?int, td: ?int, clutch: ?int, consistency: ?int, talent: ?int, skill: ?int, intangibles: ?int, loyalty: ?int, playing_time: ?int, winner: ?int, tradition: ?int, security: ?int, draftround: ?int, draftedby: ?string, draftedbycurrentname: ?string, draftyear: ?int, draftpickno: ?int, htft: ?int, htin: ?int, wt: ?int, college: ?string, dc_pg_depth: ?int, dc_sg_depth: ?int, dc_sf_depth: ?int, dc_pf_depth: ?int, dc_c_depth: ?int, dc_can_play_in_game: ?int, dc_minutes: ?int, dc_of: ?int, dc_df: ?int, dc_oi: ?int, dc_di: ?int, dc_bh: ?int, ...}
 */
interface PlayerLookupRepositoryInterface
{
    /**
     * @return PlayerRow|null
     */
    public function getPlayerByID(int $playerID): ?array;

    public function getPlayerIDFromPlayerName(string $playerName): ?int;

    /**
     * @return PlayerRow|null
     */
    public function getPlayerByName(string $playerName): ?array;
}
