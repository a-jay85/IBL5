<?php

declare(strict_types=1);

namespace Player;

use Player\Contracts\PlayerContractCalculatorInterface;

/**
 * @see PlayerContractCalculatorInterface
 */
class PlayerContractCalculator implements PlayerContractCalculatorInterface
{
    /**
     * @see PlayerContractCalculatorInterface::getCurrentSeasonSalary()
     */
    public function getCurrentSeasonSalary(PlayerData $playerData): int
    {
        return $this->getSalaryForYear($playerData, $playerData->contractCurrentYear ?? 0);
    }

    /**
     * @see PlayerContractCalculatorInterface::getNextSeasonSalary()
     */
    public function getNextSeasonSalary(PlayerData $playerData): int
    {
        return $this->getSalaryForYear($playerData, ($playerData->contractCurrentYear ?? 0) + 1);
    }

    /**
     * Get salary for a specific contract year
     */
    private function getSalaryForYear(PlayerData $playerData, int $year): int
    {
        // Year 0 defaults to year 1
        if ($year === 0) {
            return $playerData->contractYear1Salary ?? 0;
        }

        // Year 7 or beyond means no salary (off the books)
        if ($year >= 7) {
            return 0;
        }

        // Map contract year to specific property (years 1-6)
        $salaryMap = [
            1 => $playerData->contractYear1Salary,
            2 => $playerData->contractYear2Salary,
            3 => $playerData->contractYear3Salary,
            4 => $playerData->contractYear4Salary,
            5 => $playerData->contractYear5Salary,
            6 => $playerData->contractYear6Salary,
        ];

        return $salaryMap[$year] ?? 0;
    }

    /**
     * @see PlayerContractCalculatorInterface::getFutureSalaries()
     */
    public function getFutureSalaries(PlayerData $playerData): array
    {
        $contractYears = [
            $playerData->contractYear1Salary ?? 0,
            $playerData->contractYear2Salary ?? 0,
            $playerData->contractYear3Salary ?? 0,
            $playerData->contractYear4Salary ?? 0,
            $playerData->contractYear5Salary ?? 0,
            $playerData->contractYear6Salary ?? 0,
        ];

        // Slice from current year offset and pad with zeros to maintain 6-year array
        $remainingYears = array_slice($contractYears, $playerData->contractCurrentYear ?? 0);
        return array_pad($remainingYears, 6, 0);
    }

    /**
     * @see PlayerContractCalculatorInterface::getRemainingContractArray()
     */
    public function getRemainingContractArray(PlayerData $playerData): array
    {
        $currentYear = $playerData->contractCurrentYear ?? 0;
        $totalYears = $playerData->contractTotalYears ?? 0;
        $contractCurrentYear = ($currentYear !== 0) ? $currentYear : 1;
        $contractTotalYears = ($totalYears !== 0) ? $totalYears : 1;

        $contractArray = [];
        $remainingContractYear = 1;
        for ($i = $contractCurrentYear; $i <= $contractTotalYears; $i++) {
            $salary = $this->getSalaryForYear($playerData, $i);
            if ($salary !== 0) {
                $contractArray[$remainingContractYear] = $salary;
            }
            $remainingContractYear++;
        }

        // Ensure year 1 always exists in array
        return $contractArray !== [] ? $contractArray : [1 => 0];
    }

    /**
     * @see PlayerContractCalculatorInterface::getTotalRemainingSalary()
     */
    public function getTotalRemainingSalary(PlayerData $playerData): int
    {
        $contractArray = $this->getRemainingContractArray($playerData);
        return array_sum($contractArray);
    }

    /**
     * @see PlayerContractCalculatorInterface::getLongBuyoutArray()
     */
    public function getLongBuyoutArray(PlayerData $playerData): array
    {
        return $this->getBuyoutArray($playerData, 6);
    }

    /**
     * @see PlayerContractCalculatorInterface::getShortBuyoutArray()
     */
    public function getShortBuyoutArray(PlayerData $playerData): array
    {
        return $this->getBuyoutArray($playerData, 2);
    }

    /**
     * Calculate buyout terms spread over specified number of years
     *
     * @return array<int, int>
     */
    private function getBuyoutArray(PlayerData $playerData, int $years): array
    {
        $totalRemainingSalary = $this->getTotalRemainingSalary($playerData);
        $salaryPerYear = (int) round($totalRemainingSalary / $years);

        /** @var array<int, int> */
        return array_fill(1, $years, $salaryPerYear);
    }
}
