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
     *     contracts: list<array{
     *         pid: int,
     *         name: string,
     *         pos: string,
     *         teamname: string,
     *         teamid: int,
     *         team_city: string,
     *         color1: string,
     *         color2: string,
     *         bird: string,
     *         con1: int,
     *         con2: int,
     *         con3: int,
     *         con4: int,
     *         con5: int,
     *         con6: int
     *     }>,
     *     capTotals: array{cap1: float, cap2: float, cap3: float, cap4: float, cap5: float, cap6: float},
     *     avgCaps: array{acap1: float, acap2: float, acap3: float, acap4: float, acap5: float, acap6: float}
     * }
     */
    public function getContractsWithCalculations(): array;
}
