<?php

namespace Waivers;

use Season;
use Player\Player;
use Player\PlayerContractCalculator;
use Services\PlayerDataConverter;

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
     * @param Player $player Player object
     * @return string Formatted contract display
     */
    public function getPlayerContractDisplay(Player $player, Season $season): string
    {
        // Convert Player object properties to array for conversion to PlayerData
        $playerArray = [
            'cy' => $player->contractCurrentYear,
            'cyt' => $player->contractTotalYears,
            'cy1' => $player->contractYear1Salary,
            'cy2' => $player->contractYear2Salary,
            'cy3' => $player->contractYear3Salary,
            'cy4' => $player->contractYear4Salary,
            'cy5' => $player->contractYear5Salary,
            'cy6' => $player->contractYear6Salary,
            'exp' => $player->yearsOfExperience,
        ];
        $playerData = PlayerDataConverter::arrayToPlayerData($playerArray);
        
        if ($season->phase === 'Free Agency') {
            $currentSeasonSalary = $this->contractCalculator->getNextSeasonSalary($playerData);
        } else {
            $currentSeasonSalary = $this->contractCalculator->getCurrentSeasonSalary($playerData);
        }
        
        if ($currentSeasonSalary == 0) {
            return (string) $this->calculateVeteranMinimumSalary($player->yearsOfExperience);
        }
        
        $remainingContract = $this->contractCalculator->getRemainingContractArray($playerData);
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
     * @param array $playerData Player data array
     * @param Season $season Season instance to determine phase
     * @return array Contract data with cy field, contract year, and final contract display
     */
    public function prepareContractData(array $playerData, Season $season): array
    {
        $isFreeAgency = $season->phase === 'Free Agency';
        $contractYearField = $isFreeAgency ? 'cy2' : 'cy1';
        $contractYear = $isFreeAgency ? 2 : 1;
        
        // Convert array to PlayerData for calculator methods
        $playerDataObj = PlayerDataConverter::arrayToPlayerData($playerData);
        
        // Determine current season salary based on phase
        if ($isFreeAgency) {
            $currentSeasonSalary = $this->contractCalculator->getNextSeasonSalary($playerDataObj);
        } else {
            $currentSeasonSalary = $this->contractCalculator->getCurrentSeasonSalary($playerDataObj);
        }
        
        if ($currentSeasonSalary == 0) {
            $experience = (int) ($playerData['exp'] ?? 0);
            $vetMinSalary = $this->calculateVeteranMinimumSalary($experience);
            
            return [
                'contractYearField' => $contractYearField,
                'contractYear' => $contractYear,
                'salary' => $vetMinSalary,
                'isNewContract' => true,
                'finalContract' => (string) $vetMinSalary
            ];
        }
        
        // Use remaining contract from calculator
        $contractParts = $this->contractCalculator->getRemainingContractArray($playerDataObj);
        
        return [
            'isNewContract' => false,
            'finalContract' => implode(" ", $contractParts)
        ];
    }
}
