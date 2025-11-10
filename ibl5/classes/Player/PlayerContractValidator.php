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
        
        if ($seasonPhase == "Free Agency") {
            return $this->checkRookieOptionEligibility($playerData, $round, 2, 1);
        } elseif ($seasonPhase == "Preseason" || $seasonPhase == "HEAT") {
            return $this->checkRookieOptionEligibility($playerData, $round, 3, 2);
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
     * @param int $round1Experience Years of experience required for round 1
     * @param int $round2Experience Years of experience required for round 2
     */
    private function checkRookieOptionEligibility(
        PlayerData $playerData, 
        int $round, 
        int $round1Experience, 
        int $round2Experience
    ): bool {
        if ($round == 1) {
            return $playerData->yearsOfExperience == $round1Experience
                && $playerData->contractYear4Salary == 0;
        }
        
        if ($round == 2) {
            return $playerData->yearsOfExperience == $round2Experience
                && $playerData->contractYear3Salary == 0;
        }
        
        return false;
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
