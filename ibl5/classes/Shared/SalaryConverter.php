<?php

namespace Shared;

/**
 * SalaryConverter - Converts salary values between different representations
 * 
 * Handles conversion between thousands and millions for consistent display formatting.
 */
class SalaryConverter
{
    /**
     * Converts salary from thousands to millions for display
     * 
     * @param int $salaryInThousands Salary in thousands
     * @return float Salary in millions
     */
    public static function convertToMillions(int $salaryInThousands): float
    {
        return $salaryInThousands / 100;
    }
}
