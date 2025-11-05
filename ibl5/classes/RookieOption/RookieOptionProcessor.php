<?php

namespace RookieOption;

/**
 * Processes rookie option business logic
 */
class RookieOptionProcessor
{
    /**
     * Calculates the rookie option value
     * 
     * The rookie option is worth 2x the final year of the rookie contract
     * 
     * @param int $finalYearSalary Final year salary of rookie contract
     * @return int Rookie option value
     */
    public function calculateRookieOptionValue(int $finalYearSalary): int
    {
        return 2 * $finalYearSalary;
    }
    
    /**
     * Converts salary from thousands to millions for display
     * 
     * @param int $salaryInThousands Salary in thousands
     * @return float Salary in millions
     */
    public function convertToMillions(int $salaryInThousands): float
    {
        return $salaryInThousands / 100;
    }
    
    /**
     * Gets the final year of rookie contract based on draft round
     * 
     * @param int $draftRound Draft round (1 or 2)
     * @param int $cy2Salary Contract year 2 salary
     * @param int $cy3Salary Contract year 3 salary
     * @return int Final year salary
     */
    public function getFinalYearRookieContractSalary(int $draftRound, int $cy2Salary, int $cy3Salary): int
    {
        // First round picks have a 3-year contract (cy3 is final year)
        // Second round picks have a 2-year contract (cy2 is final year)
        return ($draftRound == 1) ? $cy3Salary : $cy2Salary;
    }
}
