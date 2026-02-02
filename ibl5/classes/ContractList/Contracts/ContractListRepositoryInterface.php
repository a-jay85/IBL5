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
     *     cy1: int,
     *     cy2: int,
     *     cy3: int,
     *     cy4: int,
     *     cy5: int,
     *     cy6: int,
     *     bird: string
     * }> Array of player contract data
     */
    public function getActivePlayerContracts(): array;
}
