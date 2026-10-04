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
}
