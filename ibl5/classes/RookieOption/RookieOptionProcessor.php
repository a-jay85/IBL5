<?php

namespace RookieOption;

/**
 * Processes rookie option business logic
 * 
 * Handles calculations related to rookie option values.
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
}
