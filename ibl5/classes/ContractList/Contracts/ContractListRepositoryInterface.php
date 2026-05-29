<?php

declare(strict_types=1);

namespace ContractList\Contracts;

/**
 * Repository interface for Contract List module.
 *
 * Provides method to retrieve player contract information from the database.
 */
interface ContractListRepositoryInterface
{
    /**
     * Get all active player contracts ordered by ordinal.
     *
     * @return list<array{
     *     pid: int,
     *     name: string,
     *     pos: string,
     *     teamname: string,
     *     teamid: int,
     *     cy: int,
     *     cyt: int,
     *     salary_yr1: int,
     *     salary_yr2: int,
     *     salary_yr3: int,
     *     salary_yr4: int,
     *     salary_yr5: int,
     *     salary_yr6: int,
     *     bird: string,
     *     team_city: string|null,
     *     color1: string|null,
     *     color2: string|null
     * }> Array of player contract data
     */
    public function getActivePlayerContracts(): array;
}
