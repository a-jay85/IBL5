<?php

namespace Waivers;

use Season;
use Player\PlayerData;
use Player\PlayerContractCalculator;

/**
 * Processes waiver wire business logic
 */
class WaiversProcessor
{
    private PlayerContractCalculator $contractCalculator;
    
    public function __construct()
    {
        $this->contractCalculator = new PlayerContractCalculator();
    }
    
    /**
     * Convert player array data to PlayerData object
     * 
     * @param array $playerData Raw player data array
     * @return PlayerData PlayerData object
     */
    private function arrayToPlayerData(array $playerData): PlayerData
    {
        $data = new PlayerData();
        $data->contractCurrentYear = (int) ($playerData['cy'] ?? 0);
        $data->contractTotalYears = (int) ($playerData['cyt'] ?? 0);
        $data->contractYear1Salary = (int) ($playerData['cy1'] ?? 0);
        $data->contractYear2Salary = (int) ($playerData['cy2'] ?? 0);
        $data->contractYear3Salary = (int) ($playerData['cy3'] ?? 0);
        $data->contractYear4Salary = (int) ($playerData['cy4'] ?? 0);
        $data->contractYear5Salary = (int) ($playerData['cy5'] ?? 0);
        $data->contractYear6Salary = (int) ($playerData['cy6'] ?? 0);
        $data->yearsOfExperience = (int) ($playerData['exp'] ?? 0);
        return $data;
    }
    /**
     * Calculates veteran minimum salary based on years of experience
     * 
     * Salary tiers are determined by the NBA collective bargaining agreement:
     * - 10+ years: $103k (maximum veteran minimum)
     * - 9 years: $100k
     * - 8 years: $89k
     * - 7 years: $82k
     * - 6 years: $76k
     * - 5 years: $70k
     * - 4 years: $64k
     * - 3 years: $61k
     * - 0-2 years: $51k (rookie minimum)
     * 
     * @param int $experience Years of experience
     * @return int Veteran minimum salary in thousands
     */
    public function calculateVeteranMinimumSalary(int $experience): int
    {
        if ($experience > 9) {
            return 103;
        } elseif ($experience > 8) {
            return 100;
        } elseif ($experience > 7) {
            return 89;
        } elseif ($experience > 6) {
            return 82;
        } elseif ($experience > 5) {
            return 76;
        } elseif ($experience > 4) {
            return 70;
        } elseif ($experience > 3) {
            return 64;
        } elseif ($experience > 2) {
            return 61;
        } else {
            return 51;
        }
    }
    
    /**
     * Gets the display contract for a player
     * 
     * @param array $playerData Player data including contract info
     * @return string Formatted contract display
     */
    public function getPlayerContractDisplay(array $playerData, Season $season): string
    {
        $data = $this->arrayToPlayerData($playerData);
        
        if ($season->phase === 'Free Agency') {
            $currentSeasonSalary = $this->contractCalculator->getNextSeasonSalary($data);
        } else {
            $currentSeasonSalary = $this->contractCalculator->getCurrentSeasonSalary($data);
        }
        
        if ($currentSeasonSalary == 0) {
            return (string) $this->calculateVeteranMinimumSalary($data->yearsOfExperience);
        }
        
        $remainingContract = $this->contractCalculator->getRemainingContractArray($data);
        return implode(" ", $remainingContract);
    }
    
    /**
     * Calculates wait time until a player clears waivers (24 hours)
     * 
     * @param int $dropTime Timestamp when player was dropped
     * @param int $currentTime Current timestamp
     * @return string Wait time display or empty if cleared
     */
    public function getWaiverWaitTime(int $dropTime, int $currentTime): string
    {
        $timeDiff = $currentTime - $dropTime;
        $waitPeriod = 86400; // 24 hours in seconds
        
        if ($timeDiff >= $waitPeriod) {
            return '';
        }
        
        $remainingTime = $waitPeriod - $timeDiff;
        $hours = floor($remainingTime / 3600);
        $minutes = floor(($remainingTime - $hours * 3600) / 60);
        $seconds = $remainingTime % 60;
        
        return "(Clears in $hours h, $minutes m, $seconds s)";
    }
    
    /**
     * Prepares contract data for a waiver signing
     * 
     * @param array $playerData Player data
     * @param Season $season Season instance to determine phase
     * @return array Contract data with cy field, contract year, and final contract display
     */
    public function prepareContractData(array $playerData, Season $season): array
    {
        $data = $this->arrayToPlayerData($playerData);
        $isFreeAgency = $season->phase === 'Free Agency';
        $contractYearField = $isFreeAgency ? 'cy2' : 'cy1';
        $contractYear = $isFreeAgency ? 2 : 1;
        
        if ($isFreeAgency) {
            $currentSeasonSalary = $this->contractCalculator->getNextSeasonSalary($data);
        } else {
            $currentSeasonSalary = $this->contractCalculator->getCurrentSeasonSalary($data);
        }
        
        if ($currentSeasonSalary == 0) {
            $vetMinSalary = $this->calculateVeteranMinimumSalary($data->yearsOfExperience);
            
            return [
                'contractYearField' => $contractYearField,
                'contractYear' => $contractYear,
                'salary' => $vetMinSalary,
                'isNewContract' => true,
                'finalContract' => (string) $vetMinSalary
            ];
        }
        
        // Use existing contract
        $remainingContract = $this->contractCalculator->getRemainingContractArray($data);
        
        return [
            'isNewContract' => false,
            'finalContract' => implode(" ", $remainingContract)
        ];
    }
}
