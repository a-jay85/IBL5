<?php

declare(strict_types=1);

namespace ContractList\Contracts;

/**
 * Service interface for Contract List module.
 *
 * Provides business logic for calculating contract year values.
 */
interface ContractListServiceInterface
{
    /**
     * Get all contracts with calculated year values and cap totals.
     *
     * @return array{
     *     contracts: array<int, array{
     *         name: string,
     *         pos: string,
     *         teamname: string,
     *         bird: string,
     *         con1: float,
     *         con2: float,
     *         con3: float,
     *         con4: float,
     *         con5: float,
     *         con6: float
     *     }>,
     *     capTotals: array{cap1: float, cap2: float, cap3: float, cap4: float, cap5: float, cap6: float},
     *     avgCaps: array{acap1: float, acap2: float, acap3: float, acap4: float, acap5: float, acap6: float}
     * }
     */
    public function getContractsWithCalculations(): array;
}
