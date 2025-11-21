<?php

namespace Player;

/**
 * PlayerContractCalculator - Handles salary and contract calculations
 * 
 * This class follows the Single Responsibility Principle by focusing only on
 * contract and salary calculations. It contains no data persistence logic.
 */
class PlayerContractCalculator
{
    /**
     * Calculate the current season salary based on contract year
     */
    public function getCurrentSeasonSalary(PlayerData $playerData): int
    {
        return $this->getSalaryForYear($playerData, $playerData->contractCurrentYear);
    }

    /**
     * Calculate the next season's salary
     */
    public function getNextSeasonSalary(PlayerData $playerData): int
    {
        return $this->getSalaryForYear($playerData, $playerData->contractCurrentYear + 1);
    }

    /**
     * Get salary for a specific contract year
     */
    private function getSalaryForYear(PlayerData $playerData, int $year): int
    {
        // Year 0 defaults to year 1
        if ($year == 0) {
            return $playerData->contractYear1Salary ?? 0;
        }
        
        // Year 7 or beyond means no salary (off the books)
        if ($year >= 7) {
            return 0;
        }
        
        // Dynamically access the contract year property (years 1-6)
        $propertyName = "contractYear" . $year . "Salary";
        return $playerData->$propertyName ?? 0;
    }

    /**
     * Get future salaries for the next 6 contract years
     * 
     * Returns the remaining contract years starting from the current contract year,
     * padded with zeros to always return a 6-element array.
     * 
     * @param PlayerData $playerData
     * @return array<int> Future salaries for years 1-6
     */
    public function getFutureSalaries(PlayerData $playerData): array
    {
        $contractYears = [
            $playerData->contractYear1Salary,
            $playerData->contractYear2Salary,
            $playerData->contractYear3Salary,
            $playerData->contractYear4Salary,
            $playerData->contractYear5Salary,
            $playerData->contractYear6Salary,
        ];
        
        // Slice from current year offset and pad with zeros to maintain 6-year array
        $remainingYears = array_slice($contractYears, $playerData->contractCurrentYear);
        return array_pad($remainingYears, 6, 0);
    }

    /**
     * Get an array of remaining contract years and salaries
     */
    public function getRemainingContractArray(PlayerData $playerData): array
    {
        $contractCurrentYear = ($playerData->contractCurrentYear != 0) ? $playerData->contractCurrentYear : 1;
        $contractTotalYears = ($playerData->contractTotalYears != 0) ? $playerData->contractTotalYears : 1;

        $contractArray = [];
        $remainingContractYear = 1;
        for ($i = $contractCurrentYear; $i <= $contractTotalYears; $i++) {
            $salary = $this->getSalaryForYear($playerData, $i);
            if ($salary != 0) {
                $contractArray[$remainingContractYear] = $salary;
            }
            $remainingContractYear++;
        }

        // Ensure year 1 always exists in array
        return $contractArray ?: [1 => 0];
    }

    /**
     * Calculate total remaining salary on the contract
     */
    public function getTotalRemainingSalary(PlayerData $playerData): int
    {
        $contractArray = $this->getRemainingContractArray($playerData);
        return array_sum($contractArray);
    }

    /**
     * Calculate long buyout terms (6 years)
     */
    public function getLongBuyoutArray(PlayerData $playerData): array
    {
        return $this->getBuyoutArray($playerData, 6);
    }

    /**
     * Calculate short buyout terms (2 years)
     */
    public function getShortBuyoutArray(PlayerData $playerData): array
    {
        return $this->getBuyoutArray($playerData, 2);
    }

    /**
     * Calculate buyout terms spread over specified number of years
     */
    private function getBuyoutArray(PlayerData $playerData, int $years): array
    {
        $totalRemainingSalary = $this->getTotalRemainingSalary($playerData);
        $salaryPerYear = round($totalRemainingSalary / $years);
        
        return array_fill(1, $years, $salaryPerYear);
    }
}
