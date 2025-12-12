<?php

declare(strict_types=1);

namespace Services;

use Services\Contracts\CommonContractValidatorInterface;

/**
 * CommonContractValidator - Shared contract validation logic
 * 
 * Provides reusable validation methods for contract offers used across
 * Extension, FreeAgency, and Negotiation modules. Centralizes CBA rules
 * for raise calculations, salary decreases, and maximum contract limits.
 * 
 * Note: validateOfferAmounts() is NOT included here - it's Extension-specific
 * because Free Agency, Rookie Options, and Waivers do not require the first
 * three years to have non-zero amounts.
 * 
 * @see CommonContractValidatorInterface
 */
class CommonContractValidator implements CommonContractValidatorInterface
{
    /**
     * @see CommonContractValidatorInterface::validateRaises()
     */
    public function validateRaises(array $offer, int $birdYears): array
    {
        $maxRaisePercentage = \ContractRules::getMaxRaisePercentage($birdYears);
        $maxIncrease = (int) floor(($offer['year1'] ?? 0) * $maxRaisePercentage);
        
        $years = ['year1', 'year2', 'year3', 'year4', 'year5', 'year6'];
        
        for ($i = 1; $i < count($years); $i++) {
            $currentYear = $years[$i];
            $previousYear = $years[$i - 1];
            $currentValue = $offer[$currentYear] ?? 0;
            $previousValue = $offer[$previousYear] ?? 0;
            
            // Skip if current year is zero (contract ends or not offered)
            if ($currentValue === 0) {
                continue;
            }
            
            // Check if raise exceeds maximum
            if ($currentValue > $previousValue + $maxIncrease) {
                $legalOffer = $previousValue + $maxIncrease;
                $yearNumber = $i + 1;
                $prevYearNumber = $i;
                
                return [
                    'valid' => false,
                    'error' => "Sorry, you tried to offer a larger raise than is permitted. Your first year offer was {$offer['year1']} which means the maximum raise allowed each year is {$maxIncrease}. Your offer in Year {$yearNumber} was {$currentValue}, which is more than your Year {$prevYearNumber} offer, {$previousValue}, plus the max increase of {$maxIncrease}. Given your offer in Year {$prevYearNumber}, the most you can offer in Year {$yearNumber} is {$legalOffer}."
                ];
            }
        }
        
        return ['valid' => true, 'error' => null];
    }

    /**
     * @see CommonContractValidatorInterface::validateSalaryDecreases()
     */
    public function validateSalaryDecreases(array $offer): array
    {
        $years = ['year1', 'year2', 'year3', 'year4', 'year5'];
        
        for ($i = 1; $i < count($years); $i++) {
            $currentYear = $years[$i];
            $previousYear = $years[$i - 1];
            $currentValue = $offer[$currentYear] ?? 0;
            $previousValue = $offer[$previousYear] ?? 0;
            
            // Zero indicates contract ends - that's allowed
            if ($currentValue === 0) {
                continue;
            }
            
            // Salary decrease is not allowed (unless to zero)
            if ($currentValue < $previousValue) {
                $yearNumber = $i + 1;
                return [
                    'valid' => false,
                    'error' => "Sorry, you cannot decrease salary in later years of a contract. You offered {$currentValue} in the {$yearNumber} year, which is less than you offered in the previous year, {$previousValue}."
                ];
            }
        }
        
        return ['valid' => true, 'error' => null];
    }

    /**
     * @see CommonContractValidatorInterface::validateMaximumYearOne()
     */
    public function validateMaximumYearOne(array $offer, int $yearsExperience): array
    {
        $maxYearOneOffer = \ContractRules::getMaxContractSalary($yearsExperience);
        $yearOneOffer = $offer['year1'] ?? 0;
        
        if ($yearOneOffer > $maxYearOneOffer) {
            return [
                'valid' => false,
                'error' => 'Sorry, the first year of your offer is over the maximum allowed for a player with their years of service.'
            ];
        }
        
        return ['valid' => true, 'error' => null];
    }

    /**
     * @see CommonContractValidatorInterface::validateNoGaps()
     */
    public function validateNoGaps(array $offer): array
    {
        $years = ['year1', 'year2', 'year3', 'year4', 'year5', 'year6'];
        $contractEnded = false;
        
        foreach ($years as $index => $year) {
            $value = $offer[$year] ?? 0;
            $yearNumber = $index + 1;
            
            if ($value === 0) {
                $contractEnded = true;
            } elseif ($contractEnded) {
                // Contract resumed after ending - this is a gap
                $prevYearNumber = $yearNumber - 1;
                return [
                    'valid' => false,
                    'error' => "Sorry, you cannot have gaps in contract years. You offered 0 in year {$prevYearNumber} but offered {$value} in year {$yearNumber}."
                ];
            }
        }
        
        return ['valid' => true, 'error' => null];
    }

    /**
     * @see CommonContractValidatorInterface::calculateOfferValue()
     */
    public function calculateOfferValue(array $offer): array
    {
        $total = 0;
        $years = 0;
        
        $yearKeys = ['year1', 'year2', 'year3', 'year4', 'year5', 'year6'];
        
        foreach ($yearKeys as $key) {
            $value = $offer[$key] ?? 0;
            if ($value > 0) {
                $total += $value;
                $years++;
            }
        }
        
        // Ensure minimum 1 year to prevent division by zero
        if ($years === 0) {
            $years = 1;
        }
        
        return [
            'total' => $total,
            'years' => $years,
            'averagePerYear' => $years > 0 ? (float) ($total / $years) : 0.0
        ];
    }
}
