<?php

declare(strict_types=1);

namespace Services\Contracts;

/**
 * CommonContractValidatorInterface - Shared contract validation logic
 * 
 * Provides reusable validation methods for contract offers used across
 * Extension, FreeAgency, and Negotiation modules. Centralizes CBA rules
 * for offer amount validation, raise calculations, and salary decreases.
 * 
 * @package Services\Contracts
 */
interface CommonContractValidatorInterface
{
    /**
     * Validate that the first three years of a contract offer have non-zero amounts
     * 
     * All IBL contracts require a minimum of 3 guaranteed years.
     * Years 4, 5, and 6 are optional (can be zero).
     * 
     * @param array{year1: int, year2: int, year3: int, year4?: int, year5?: int, year6?: int} $offer 
     *        Contract offer with yearly salary amounts in thousands
     * @return array{valid: bool, error: string|null} Validation result:
     *         - 'valid': bool - True if years 1-3 all have amounts > 0
     *         - 'error': string|null - Error message identifying which year failed, null if valid
     * 
     * IMPORTANT BEHAVIORS:
     *  - Only checks year1, year2, year3 (required years)
     *  - Zero or negative amounts in years 1-3 fail validation
     *  - Years 4-6 are NOT validated by this method (can be zero)
     *  - Returns specific error message identifying the failing year
     * 
     * @example
     * // Valid: all required years present
     * validateOfferAmounts(['year1' => 500, 'year2' => 550, 'year3' => 600]) 
     * // Returns: ['valid' => true, 'error' => null]
     * 
     * // Invalid: year2 is zero
     * validateOfferAmounts(['year1' => 500, 'year2' => 0, 'year3' => 600])
     * // Returns: ['valid' => false, 'error' => 'Sorry, you must enter...Year2 was zero...']
     */
    public function validateOfferAmounts(array $offer): array;

    /**
     * Validate that raises between contract years don't exceed allowed percentages
     * 
     * Raises are limited based on Bird Rights status. The maximum raise
     * is calculated from the first year salary and applied to all subsequent years.
     * 
     * @param array{year1: int, year2: int, year3: int, year4?: int, year5?: int, year6?: int} $offer
     *        Contract offer with yearly salary amounts in thousands
     * @param int $birdYears Years of Bird Rights with current team (0-10+)
     * @return array{valid: bool, error: string|null} Validation result:
     *         - 'valid': bool - True if all raises within limits
     *         - 'error': string|null - Detailed error with amounts if exceeded, null if valid
     * 
     * IMPORTANT BEHAVIORS:
     *  - Max raise = year1 * raise_percentage (10% standard, 12.5% with Bird Rights)
     *  - Bird Rights threshold is 3+ years (uses ContractRules::BIRD_RIGHTS_THRESHOLD)
     *  - Only checks non-zero years (year4/5/6 of 0 are skipped)
     *  - Error message includes specific year, amounts offered, and legal maximum
     *  - Uses ContractRules::getMaxRaisePercentage() for raise calculation
     * 
     * @see \ContractRules::getMaxRaisePercentage()
     * @see \ContractRules::BIRD_RIGHTS_THRESHOLD
     * 
     * @example
     * // With Bird Rights (12.5% max raise)
     * validateRaises(['year1' => 1000, 'year2' => 1125, 'year3' => 1250], 3)
     * // Returns: ['valid' => true, 'error' => null]
     * 
     * // Without Bird Rights (10% max raise) - year2 exceeds limit
     * validateRaises(['year1' => 1000, 'year2' => 1200, 'year3' => 1300], 0)
     * // Returns: ['valid' => false, 'error' => '...max raise is 100...Year 2 was 1200...']
     */
    public function validateRaises(array $offer, int $birdYears): array;

    /**
     * Validate that salaries don't decrease in later contract years (except to zero)
     * 
     * Contract salaries must be flat or increasing year-over-year.
     * A year with zero salary indicates the contract ends (allowed).
     * 
     * @param array{year1: int, year2: int, year3: int, year4?: int, year5?: int, year6?: int} $offer
     *        Contract offer with yearly salary amounts in thousands
     * @return array{valid: bool, error: string|null} Validation result:
     *         - 'valid': bool - True if no illegal decreases
     *         - 'error': string|null - Error identifying the decrease, null if valid
     * 
     * IMPORTANT BEHAVIORS:
     *  - Salary decrease (year[n] < year[n-1]) is INVALID
     *  - EXCEPTION: Zero salary is allowed (indicates contract ends)
     *  - Checks year2 vs year1, year3 vs year2, year4 vs year3, year5 vs year4
     *  - Error message includes the year and amounts involved
     * 
     * @example
     * // Valid: increasing salaries
     * validateSalaryDecreases(['year1' => 500, 'year2' => 550, 'year3' => 600])
     * // Returns: ['valid' => true, 'error' => null]
     * 
     * // Valid: contract ends in year 4 (zero allowed)
     * validateSalaryDecreases(['year1' => 500, 'year2' => 550, 'year3' => 600, 'year4' => 0])
     * // Returns: ['valid' => true, 'error' => null]
     * 
     * // Invalid: year3 decreases from year2
     * validateSalaryDecreases(['year1' => 500, 'year2' => 550, 'year3' => 400])
     * // Returns: ['valid' => false, 'error' => '...offered 400 in year 3...less than 550...']
     */
    public function validateSalaryDecreases(array $offer): array;

    /**
     * Validate that first year salary doesn't exceed maximum for player's experience
     * 
     * Maximum first-year contract salary is tiered by years of service.
     * 
     * @param array{year1: int, year2?: int, year3?: int, year4?: int, year5?: int, year6?: int} $offer
     *        Contract offer with yearly salary amounts in thousands
     * @param int $yearsExperience Player's years of NBA experience (0-20+)
     * @return array{valid: bool, error: string|null} Validation result:
     *         - 'valid': bool - True if year1 within maximum
     *         - 'error': string|null - Error message if over maximum, null if valid
     * 
     * IMPORTANT BEHAVIORS:
     *  - Only validates year1 (first year of new contract)
     *  - Uses ContractRules::getMaxContractSalary() for tier lookup
     *  - Maximum tiers: 0-6 years = 1063, 7-9 years = 1275, 10+ years = 1451
     * 
     * @see \ContractRules::getMaxContractSalary()
     * @see \ContractRules::MAX_CONTRACT_SALARIES
     * 
     * @example
     * // Valid: within max for 5-year player (max 1063)
     * validateMaximumYearOne(['year1' => 1000], 5)
     * // Returns: ['valid' => true, 'error' => null]
     * 
     * // Invalid: over max for 5-year player
     * validateMaximumYearOne(['year1' => 1200], 5)
     * // Returns: ['valid' => false, 'error' => '...first year over maximum allowed...']
     */
    public function validateMaximumYearOne(array $offer, int $yearsExperience): array;

    /**
     * Validate contract has no gaps (once a year is zero, all following must be zero)
     * 
     * Contracts cannot have gaps - if year N has zero salary, all years
     * after N must also have zero salary.
     * 
     * @param array{year1: int, year2: int, year3: int, year4?: int, year5?: int, year6?: int} $offer
     *        Contract offer with yearly salary amounts in thousands
     * @return array{valid: bool, error: string|null} Validation result:
     *         - 'valid': bool - True if no gaps in contract
     *         - 'error': string|null - Error identifying the gap, null if valid
     * 
     * IMPORTANT BEHAVIORS:
     *  - Once a year has 0 salary, all subsequent years must also be 0
     *  - Gaps (0 followed by non-zero) are INVALID
     *  - Missing keys (year4, year5, year6) treated as 0
     * 
     * @example
     * // Valid: 3-year contract
     * validateNoGaps(['year1' => 500, 'year2' => 550, 'year3' => 600, 'year4' => 0])
     * // Returns: ['valid' => true, 'error' => null]
     * 
     * // Invalid: gap in year 4
     * validateNoGaps(['year1' => 500, 'year2' => 550, 'year3' => 0, 'year4' => 600])
     * // Returns: ['valid' => false, 'error' => '...cannot have gaps...year 3 was 0 but year 4 was 600']
     */
    public function validateNoGaps(array $offer): array;

    /**
     * Calculate contract value metrics from offer array
     * 
     * Utility method to calculate total value and number of years from an offer.
     * 
     * @param array{year1: int, year2: int, year3: int, year4?: int, year5?: int, year6?: int} $offer
     *        Contract offer with yearly salary amounts in thousands
     * @return array{total: int, years: int, averagePerYear: float} Contract metrics:
     *         - 'total': int - Sum of all non-zero years
     *         - 'years': int - Count of non-zero years (1-6)
     *         - 'averagePerYear': float - Average salary per year (total / years)
     * 
     * IMPORTANT BEHAVIORS:
     *  - Only counts non-zero years toward total and count
     *  - Minimum 1 year returned (even if year1 is 0)
     *  - averagePerYear is 0 if years is 0 to prevent division by zero
     */
    public function calculateOfferValue(array $offer): array;
}
