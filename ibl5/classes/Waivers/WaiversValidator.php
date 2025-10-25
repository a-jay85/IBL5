<?php

namespace Waivers;

/**
 * Validates waiver wire operations
 */
class WaiversValidator
{
    private array $errors = [];
    
    /**
     * Gets validation errors
     * 
     * @return array Array of error messages
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Clears validation errors
     */
    public function clearErrors(): void
    {
        $this->errors = [];
    }
    
    /**
     * Validates a drop to waivers operation
     * 
     * @param int $rosterSlots Total roster slots filled
     * @param int $totalSalary Total team salary
     * @param int $hardCapMax Maximum hard cap
     * @return bool True if valid, false otherwise
     */
    public function validateDrop(int $rosterSlots, int $totalSalary, int $hardCapMax): bool
    {
        $this->clearErrors();
        
        if ($rosterSlots > 2 && $totalSalary > $hardCapMax) {
            $this->errors[] = "You have 12 players and are over $70 mill hard cap. Therefore you can't drop a player!";
            return false;
        }
        
        return true;
    }
    
    /**
     * Validates an add from waivers operation
     * 
     * @param int|null $playerID Player ID being added
     * @param int $healthyRosterSlots Number of healthy roster slots available
     * @param int $totalSalary Current team salary
     * @param int $playerSalary Salary of player being added
     * @param int $hardCapMax Maximum hard cap
     * @return bool True if valid, false otherwise
     */
    public function validateAdd(
        ?int $playerID,
        int $healthyRosterSlots,
        int $totalSalary,
        int $playerSalary,
        int $hardCapMax
    ): bool {
        $this->clearErrors();
        
        if ($playerID === null || $playerID === 0) {
            $this->errors[] = "You didn't select a valid player. Please select a player and try again.";
            return false;
        }
        
        if ($healthyRosterSlots < 1) {
            $this->errors[] = "You have full roster of 15 players. You can't sign another player at this time!";
            return false;
        }
        
        $newTotalSalary = $totalSalary + $playerSalary;
        
        // If 12+ healthy players and signing puts over hard cap
        if ($healthyRosterSlots < 4 && $newTotalSalary > $hardCapMax) {
            $this->errors[] = "You have 12 or more healthy players and this signing will put you over $70 million. Therefore you cannot make this signing.";
            return false;
        }
        
        // If under 12 healthy players but over hard cap and player salary > vet min
        if ($healthyRosterSlots > 3 && $newTotalSalary > $hardCapMax && $playerSalary > 103) {
            $this->errors[] = "You are over the hard cap and therefore can only sign players who are making veteran minimum!";
            return false;
        }
        
        return true;
    }
}
