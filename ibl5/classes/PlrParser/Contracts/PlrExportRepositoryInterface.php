<?php

declare(strict_types=1);

namespace PlrParser\Contracts;

/**
 * Database reads needed to export current player state back into a .plr file.
 */
interface PlrExportRepositoryInterface
{
    /**
     * Get all changeable player fields for PLR export, keyed by pid.
     *
     * Returns dc_ prefixed depth chart fields (the GM's intended values from the website),
     * NOT the non-prefixed fields (which are the values currently IN the .plr file from last parse).
     *
     * @return array<int, array{
     *     pid: int,
     *     name: string,
     *     teamid: int,
     *     bird: int,
     *     cy: int,
     *     cyt: int,
     *     salary_yr1: int,
     *     salary_yr2: int,
     *     salary_yr3: int,
     *     salary_yr4: int,
     *     salary_yr5: int,
     *     salary_yr6: int,
     *     fa_signing_flag: int
     * }> Keyed by pid
     */
    public function getAllPlayerChangeableFields(): array;

    /**
     * Get every player's GM depth chart from the dc_* columns.
     *
     * @return array<int, array{pid: int, teamid: int, dc_pg_depth: int, dc_sg_depth: int, dc_sf_depth: int, dc_pf_depth: int, dc_c_depth: int, dc_can_play_in_game: int, dc_minutes: int, dc_of: int, dc_df: int, dc_oi: int, dc_di: int, dc_bh: int}> Keyed by pid
     */
    public function getAllPlayerDepthCharts(): array;
}
