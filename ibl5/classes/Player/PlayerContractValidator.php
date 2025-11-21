<?php

namespace Player;

/**
 * PlayerContractValidator - Validates contract eligibility rules
 * 
 * This class encapsulates all contract validation logic, making it easy to test
 * and maintain. It follows the Single Responsibility Principle.
 */
class PlayerContractValidator
{
    // Rookie option experience requirements
    private const ROOKIE_OPTION_ROUND1_FA_EXPERIENCE = 2;
    private const ROOKIE_OPTION_ROUND1_INSEASON_EXPERIENCE = 3;
    private const ROOKIE_OPTION_ROUND2_FA_EXPERIENCE = 1;
    private const ROOKIE_OPTION_ROUND2_INSEASON_EXPERIENCE = 2;

    /**
     * Check if a player can renegotiate their contract
     * A player can renegotiate if they're in their last contract year or have no salary in the next year
     * EXCEPT: A player cannot renegotiate if they were rookie optioned and are in the rookie option year
     */
    public function canRenegotiateContract(PlayerData $playerData): bool
    {
        $currentYear = $playerData->contractCurrentYear;
        
        // Check if player was rookie optioned and is currently in the rookie option year
        if ($this->wasRookieOptioned($playerData)) {
            $round = $playerData->draftRound;
            // First round: rookie option is year 4
            if ($round == 1 && $currentYear == 4) {
                return false;
            }
            // Second round: rookie option is year 3
            if ($round == 2 && $currentYear == 3) {
                return false;
            }
        }
        
        // In final year (year 6) or beyond - always eligible
        if ($currentYear >= 6) {
            return true;
        }
        
        // Check if next year has no salary (eligible for renegotiation)
        // Safe to access: currentYear is 0-5, so nextYear is 1-6 which all exist as properties
        $nextYearProperty = "contractYear" . ($currentYear + 1) . "Salary";
        return $playerData->$nextYearProperty == 0;
    }

    /**
     * Check if a player is eligible for rookie option
     */
    public function canRookieOption(PlayerData $playerData, string $seasonPhase): bool
    {
        $round = $playerData->draftRound;
        
        // Only first and second round picks are eligible
        if ($round != 1 && $round != 2) {
            return false;
        }
        
        // Players with more than 3 years of experience are not eligible
        if ($playerData->yearsOfExperience > 3) {
            return false;
        }
        
        if ($seasonPhase == "Free Agency") {
            return $this->checkRookieOptionEligibility($playerData, $round, $seasonPhase);
        } elseif ($seasonPhase == "Preseason" || $seasonPhase == "HEAT") {
            return $this->checkRookieOptionEligibility($playerData, $round, $seasonPhase);
        }
        
        return false;
    }
    
    /**
     * Gets the final year of rookie contract salary based on draft round
     * 
     * @param PlayerData $playerData The player data
     * @return int Final year salary (0 if not a draft pick)
     */
    public function getFinalYearRookieContractSalary(PlayerData $playerData): int
    {
        $round = $playerData->draftRound;
        
        // First round picks have a 3-year contract (cy3 is final year)
        if ($round == 1) {
            return $playerData->contractYear3Salary;
        }
        
        // Second round picks have a 2-year contract (cy2 is final year)
        if ($round == 2) {
            return $playerData->contractYear2Salary;
        }
        
        // Not a draft pick
        return 0;
    }

    /**
     * Check rookie option eligibility for a specific round and experience level
     * 
     * @param PlayerData $playerData The player data
     * @param int $round Draft round (1 or 2)
     * @param string $seasonPhase Current season phase ("Free Agency", "Preseason", or "HEAT")
     */
    private function checkRookieOptionEligibility(
        PlayerData $playerData, 
        int $round,
        string $seasonPhase
    ): bool {
        if ($round == 1) {
            $requiredExperience = ($seasonPhase == "Free Agency") 
                ? self::ROOKIE_OPTION_ROUND1_FA_EXPERIENCE 
                : self::ROOKIE_OPTION_ROUND1_INSEASON_EXPERIENCE;
            return $playerData->yearsOfExperience == $requiredExperience
                && $playerData->contractYear4Salary == 0;
        }
        
        if ($round == 2) {
            $requiredExperience = ($seasonPhase == "Free Agency") 
                ? self::ROOKIE_OPTION_ROUND2_FA_EXPERIENCE 
                : self::ROOKIE_OPTION_ROUND2_INSEASON_EXPERIENCE;
            return $playerData->yearsOfExperience == $requiredExperience
                && $playerData->contractYear3Salary == 0;
        }
        
        return false;
    }

    /**
     * Check if player becomes a free agent in the specified season
     * 
     * A player becomes free agent when their contract ends.
     * Free agency year = draftYear + yearsOfExperience + contractTotalYears - contractCurrentYear
     * 
     * @param PlayerData $playerData The player data
     * @param \Season $season The season to check against
     * @return bool True if player becomes free agent this season
     */
    public function isPlayerFreeAgent(PlayerData $playerData, \Season $season): bool
    {
        $yearPlayerIsFreeAgent = $playerData->draftYear 
            + $playerData->yearsOfExperience 
            + $playerData->contractTotalYears 
            - $playerData->contractCurrentYear;
        
        return $yearPlayerIsFreeAgent == $season->endingYear;
    }

    /**
     * Check if a player's rookie option was previously exercised
     * Rookie options double the salary from the previous year
     */
    public function wasRookieOptioned(PlayerData $playerData): bool
    {
        $round = $playerData->draftRound;
        $experience = $playerData->yearsOfExperience;
        
        // First round: Check in year 4
        if ($round == 1 && $experience == 4) {
            return $this->isRookieOptionExercised($playerData, 3, 4);
        }
        
        // Second round: Check in year 3
        if ($round == 2 && $experience == 3) {
            return $this->isRookieOptionExercised($playerData, 2, 3);
        }
        
        return false;
    }

    /**
     * Check if rookie option was exercised by comparing salary years
     * 
     * @param PlayerData $playerData The player data
     * @param int $baseYear The year to check as base
     * @param int $optionYear The year that should be double the base
     */
    private function isRookieOptionExercised(PlayerData $playerData, int $baseYear, int $optionYear): bool
    {
        $baseProperty = "contractYear" . $baseYear . "Salary";
        $optionProperty = "contractYear" . $optionYear . "Salary";
        
        $baseSalary = $playerData->$baseProperty;
        $optionSalary = $playerData->$optionProperty;
        
        return $optionSalary != 0 && $baseSalary * 2 == $optionSalary;
    }
}
