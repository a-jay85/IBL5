<?php

declare(strict_types=1);

namespace ContractList;

use ContractList\Contracts\ContractListServiceInterface;
use ContractList\Contracts\ContractListRepositoryInterface;

/**
 * ContractListService - Business logic for contract calculations
 *
 * Calculates contract year values and cap totals.
 *
 * @phpstan-type ProcessedContract array{pid: int, name: string, pos: string, teamname: string, tid: int, team_city: string, color1: string, color2: string, bird: string, con1: int, con2: int, con3: int, con4: int, con5: int, con6: int}
 * @phpstan-type CapTotals array{cap1: float, cap2: float, cap3: float, cap4: float, cap5: float, cap6: float}
 * @phpstan-type AvgCaps array{acap1: float, acap2: float, acap3: float, acap4: float, acap5: float, acap6: float}
 * @phpstan-type ContractCalculations array{contracts: list<ProcessedContract>, capTotals: CapTotals, avgCaps: AvgCaps}
 *
 * @see ContractListServiceInterface For the interface contract
 */
class ContractListService implements ContractListServiceInterface
{
    private ContractListRepositoryInterface $repository;
    private const TEAM_COUNT = 28;

    public function __construct(ContractListRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see ContractListServiceInterface::getContractsWithCalculations()
     */
    public function getContractsWithCalculations(): array
    {
        $players = $this->repository->getActivePlayerContracts();

        $contracts = [];
        $capTotals = [
            'cap1' => 0,
            'cap2' => 0,
            'cap3' => 0,
            'cap4' => 0,
            'cap5' => 0,
            'cap6' => 0,
        ];

        foreach ($players as $player) {
            $contractYears = $this->calculateContractYears($player);

            $contracts[] = [
                'pid' => (int) ($player['pid'] ?? 0),
                'name' => $player['name'],
                'pos' => $player['pos'],
                'teamname' => $player['teamname'],
                'tid' => (int) ($player['tid'] ?? 0),
                'team_city' => $player['team_city'] ?? '',
                'color1' => $player['color1'] ?? 'FFFFFF',
                'color2' => $player['color2'] ?? '000000',
                'bird' => $player['bird'],
                'con1' => $contractYears['con1'],
                'con2' => $contractYears['con2'],
                'con3' => $contractYears['con3'],
                'con4' => $contractYears['con4'],
                'con5' => $contractYears['con5'],
                'con6' => $contractYears['con6'],
            ];

            // Accumulate cap totals
            $capTotals['cap1'] += $contractYears['con1'];
            $capTotals['cap2'] += $contractYears['con2'];
            $capTotals['cap3'] += $contractYears['con3'];
            $capTotals['cap4'] += $contractYears['con4'];
            $capTotals['cap5'] += $contractYears['con5'];
            $capTotals['cap6'] += $contractYears['con6'];
        }

        // Convert cap totals to millions (divide by 100)
        $capTotalsInMillions = [
            'cap1' => $capTotals['cap1'] / 100,
            'cap2' => $capTotals['cap2'] / 100,
            'cap3' => $capTotals['cap3'] / 100,
            'cap4' => $capTotals['cap4'] / 100,
            'cap5' => $capTotals['cap5'] / 100,
            'cap6' => $capTotals['cap6'] / 100,
        ];

        // Calculate average team cap
        $avgCaps = [
            'acap1' => $capTotals['cap1'] / self::TEAM_COUNT / 100,
            'acap2' => $capTotals['cap2'] / self::TEAM_COUNT / 100,
            'acap3' => $capTotals['cap3'] / self::TEAM_COUNT / 100,
            'acap4' => $capTotals['cap4'] / self::TEAM_COUNT / 100,
            'acap5' => $capTotals['cap5'] / self::TEAM_COUNT / 100,
            'acap6' => $capTotals['cap6'] / self::TEAM_COUNT / 100,
        ];

        return [
            'contracts' => $contracts,
            'capTotals' => $capTotalsInMillions,
            'avgCaps' => $avgCaps,
        ];
    }

    /**
     * Calculate contract year values for a player.
     *
     * @param array{cy: int, cyt: int, cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int} $player Player data
     * @return array{con1: int, con2: int, con3: int, con4: int, con5: int, con6: int} Contract values per year
     */
    private function calculateContractYears(array $player): array
    {
        $cy = $player['cy'];

        // Free agency offset is always 0 (original code's $faon was undefined)
        $year1 = $cy;
        $year2 = $cy + 1;
        $year3 = $cy + 2;
        $year4 = $cy + 3;
        $year5 = $cy + 4;
        $year6 = $cy + 5;

        /** @var array{cy1: int, cy2: int, cy3: int, cy4: int, cy5: int, cy6: int} $cyValues */
        $cyValues = [
            'cy1' => $player['cy1'],
            'cy2' => $player['cy2'],
            'cy3' => $player['cy3'],
            'cy4' => $player['cy4'],
            'cy5' => $player['cy5'],
            'cy6' => $player['cy6'],
        ];

        if ($cy === 0) {
            // Direct mapping when cy is 0
            return [
                'con1' => ($year1 < 7) ? $cyValues['cy1'] : 0,
                'con2' => ($year2 < 7) ? $cyValues['cy2'] : 0,
                'con3' => ($year3 < 7) ? $cyValues['cy3'] : 0,
                'con4' => ($year4 < 7) ? $cyValues['cy4'] : 0,
                'con5' => ($year5 < 7) ? $cyValues['cy5'] : 0,
                'con6' => ($year6 < 7) ? $cyValues['cy6'] : 0,
            ];
        }

        // Dynamic mapping based on current year
        return [
            'con1' => ($year1 < 7) ? ($cyValues['cy' . $year1] ?? 0) : 0,
            'con2' => ($year2 < 7) ? ($cyValues['cy' . $year2] ?? 0) : 0,
            'con3' => ($year3 < 7) ? ($cyValues['cy' . $year3] ?? 0) : 0,
            'con4' => ($year4 < 7) ? ($cyValues['cy' . $year4] ?? 0) : 0,
            'con5' => ($year5 < 7) ? ($cyValues['cy' . $year5] ?? 0) : 0,
            'con6' => ($year6 < 7) ? ($cyValues['cy' . $year6] ?? 0) : 0,
        ];
    }
}
