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
     * @return array<int, array{
     *     name: string,
     *     pos: string,
     *     teamname: string,
     *     cy: int,
     *     cyt: int,
     *     salary_yr1: int,
     *     salary_yr2: int,
     *     salary_yr3: int,
     *     salary_yr4: int,
     *     salary_yr5: int,
     *     salary_yr6: int,
     *     bird: string
     * }> Array of player contract data
     */
    public function getActivePlayerContracts(): array;
}
