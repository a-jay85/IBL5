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
     */
    public function getTeamPerformance(string $teamName): array
    {
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
            'wins' => (int) ($result['Contract_Wins'] ?? 0),
            'losses' => (int) ($result['Contract_Losses'] ?? 0),
            'tradWins' => (int) ($result['Contract_AvgW'] ?? 0),
            'tradLosses' => (int) ($result['Contract_AvgL'] ?? 0),
        ];
    }

    /**
     * @see FreeAgencyDemandRepositoryInterface::getPositionSalaryCommitment()
     */
    public function getPositionSalaryCommitment(string $teamName, string $position, int $excludePlayerID): int
    {
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
     */
    public function getPlayerDemands(string $playerName): array
    {
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
            'dem1' => (int) ($result['dem1'] ?? 0),
            'dem2' => (int) ($result['dem2'] ?? 0),
            'dem3' => (int) ($result['dem3'] ?? 0),
            'dem4' => (int) ($result['dem4'] ?? 0),
            'dem5' => (int) ($result['dem5'] ?? 0),
            'dem6' => (int) ($result['dem6'] ?? 0),
        ];
    }
}
