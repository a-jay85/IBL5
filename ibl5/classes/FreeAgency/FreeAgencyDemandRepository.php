<?php

declare(strict_types=1);

namespace FreeAgency;

use BaseMysqliRepository;
use FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface;

/**
 * FreeAgencyDemandRepository - Database operations for free agency demand calculations
 *
 * Extends BaseMysqliRepository for standardized prepared statement handling.
 * Centralizes all database queries for the FreeAgency demand calculation module.
 *
 * @see FreeAgencyDemandRepositoryInterface For method contracts
 * @see BaseMysqliRepository For base class documentation and error codes
 *
 * @phpstan-import-type TeamPerformanceRow from \FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface
 * @phpstan-import-type PlayerDemandsRow from \FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface
 * @phpstan-import-type ContractYearRow from \FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface
 */
class FreeAgencyDemandRepository extends BaseMysqliRepository implements FreeAgencyDemandRepositoryInterface
{
    /**
     * Constructor - inherits from BaseMysqliRepository
     * 
     * @param object $db Active mysqli connection (or duck-typed mock during migration)
     * @throws \RuntimeException If connection is invalid (error code 1002)
     * 
     * TEMPORARY: Accepts duck-typed objects during mysqli migration for testing.
     * Will be strictly \mysqli once migration completes.
     */
    public function __construct(object $db)
    {
        parent::__construct($db);
    }

    /**
     * @see FreeAgencyDemandRepositoryInterface::getTeamPerformance()
     *
     * @return TeamPerformanceRow
     */
    public function getTeamPerformance(string $teamName): array
    {
        /** @var array{Contract_Wins: int, Contract_Losses: int, Contract_AvgW: int, Contract_AvgL: int}|null $result */
        $result = $this->fetchOne(
            "SELECT Contract_Wins, Contract_Losses, Contract_AvgW, Contract_AvgL
             FROM ibl_team_info
             WHERE team_name = ?",
            "s",
            $teamName
        );

        if ($result === null) {
            return ['wins' => 0, 'losses' => 0, 'tradWins' => 0, 'tradLosses' => 0];
        }

        return [
            'wins' => $result['Contract_Wins'],
            'losses' => $result['Contract_Losses'],
            'tradWins' => $result['Contract_AvgW'],
            'tradLosses' => $result['Contract_AvgL'],
        ];
    }

    /**
     * @see FreeAgencyDemandRepositoryInterface::getPositionSalaryCommitment()
     *
     * @return int Total salary committed to the position
     */
    public function getPositionSalaryCommitment(string $teamName, string $position, int $excludePlayerID): int
    {
        /** @var list<ContractYearRow> $rows */
        $rows = $this->fetchAll(
            "SELECT cy, cy1, cy2, cy3, cy4, cy5, cy6 
             FROM ibl_plr 
             WHERE teamname = ? 
               AND pos = ? 
               AND pid != ?",
            "ssi",
            $teamName,
            $position,
            $excludePlayerID
        );
        
        $totalSalary = 0;
        
        foreach ($rows as $row) {
            $currentYear = (int) $row['cy'];
            
            // Get salary for next year based on current contract year
            switch ($currentYear) {
                case 0:
                    $totalSalary += (int) $row['cy1'];
                    break;
                case 1:
                    $totalSalary += (int) $row['cy2'];
                    break;
                case 2:
                    $totalSalary += (int) $row['cy3'];
                    break;
                case 3:
                    $totalSalary += (int) $row['cy4'];
                    break;
                case 4:
                    $totalSalary += (int) $row['cy5'];
                    break;
                case 5:
                    $totalSalary += (int) $row['cy6'];
                    break;
            }
        }
        
        return $totalSalary;
    }

    /**
     * @see FreeAgencyDemandRepositoryInterface::getPlayerDemands()
     *
     * @return PlayerDemandsRow
     */
    public function getPlayerDemands(string $playerName): array
    {
        /** @var array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int}|null $result */
        $result = $this->fetchOne(
            "SELECT dem1, dem2, dem3, dem4, dem5, dem6
             FROM ibl_demands
             WHERE name = ?",
            "s",
            $playerName
        );

        if ($result === null) {
            return [
                'dem1' => 0,
                'dem2' => 0,
                'dem3' => 0,
                'dem4' => 0,
                'dem5' => 0,
                'dem6' => 0,
            ];
        }

        return [
            'dem1' => $result['dem1'],
            'dem2' => $result['dem2'],
            'dem3' => $result['dem3'],
            'dem4' => $result['dem4'],
            'dem5' => $result['dem5'],
            'dem6' => $result['dem6'],
        ];
    }
}
