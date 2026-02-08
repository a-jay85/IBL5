<?php

declare(strict_types=1);

namespace ContractList\Contracts;

/**
 * View interface for Contract List module rendering.
 *
 * Provides method to render the master contract list table.
 */
interface ContractListViewInterface
{
    /**
     * Render the master contract list table.
     *
     * @param array{
     *     contracts: array<int, array{
     *         pid: int,
     *         name: string,
     *         pos: string,
     *         teamname: string,
     *         tid: int,
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
     * } $data Contract data with calculations
     * @return string HTML output for the contract list table
     */
    public function render(array $data): string;
}
