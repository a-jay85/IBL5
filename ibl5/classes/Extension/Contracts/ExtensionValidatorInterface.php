<?php

namespace Extension\Contracts;

/**
 * ExtensionValidatorInterface - Contract for extension validation operations
 * 
 * Defines validation rules for contract extension offers. Enforces business rules
 * around offer amounts, maximum salaries, raise percentages, and team eligibility.
 * 
 * @package Extension\Contracts
 */
interface ExtensionValidatorInterface
{
    /**
     * Validates that the first three years of the offer have non-zero amounts
     * 
     * Extensions must include at least 3 guaranteed years. Years 4 and 5 are optional.
     * 
     * @param array $offer Array with keys: year1, year2, year3, year4, year5
     * @return array Validation result:
     *   - 'valid': bool - True if all required years have amounts
     *   - 'error': string|null - Error message if invalid, null if valid
     * 
     * **Business Rules:**
     * - year1, year2, year3 must all be greater than zero
     * - year4 and year5 can be zero (for 3 or 4-year deals)
     */
    public function validateOfferAmounts($offer);

    /**
     * Validates that the offer doesn't exceed the maximum allowed for player's experience
     * 
     * Year-one salary is capped based on years of service.
     * 
     * @param array $offer Offer array
     * @param int $yearsExperience Player's years of experience
     * @return array Validation result:
     *   - 'valid': bool - True if year1 is within maximum
     *   - 'error': string|null - Error message if over maximum
     * 
     * **Maximum Salary Tiers:**
     * - 0-6 years: 1063 ($10.63M)
     * - 7-9 years: 1275 ($12.75M)
     * - 10+ years: 1451 ($14.51M)
     */
    public function validateMaximumYearOneOffer($offer, $yearsExperience);

    /**
     * Validates that raises between years don't exceed allowed percentages
     * 
     * Raises are limited based on Bird rights years and the first year salary.
     * 
     * @param array $offer Offer array
     * @param int $birdYears Years with Bird rights (affects max raise percentage)
     * @return array Validation result:
     *   - 'valid': bool - True if all raises are within limits
     *   - 'error': string|null - Detailed error message if raise exceeded
     * 
     * **Raise Calculation:**
     * - Max raise = year1 * maxRaisePercentage (from ContractRules)
     * - Each year checked against previous year + max raise
     * - Error includes specific year and amounts for user feedback
     * 
     * **Bird Years Impact:**
     * - More bird years = higher allowed raise percentage
     */
    public function validateRaises($offer, $birdYears);

    /**
     * Validates that salaries don't decrease in later years (except to zero)
     * 
     * Contract salaries must be flat or increasing, except when a year is
     * set to zero (indicating end of contract).
     * 
     * @param array $offer Offer array
     * @return array Validation result:
     *   - 'valid': bool - True if no invalid decreases
     *   - 'error': string|null - Error message if salary decreased
     * 
     * **Business Rules:**
     * - year[n] < year[n-1] is invalid UNLESS year[n] == 0
     * - Zero indicates contract ends, not a salary decrease
     */
    public function validateSalaryDecreases($offer);

    /**
     * Validates extension eligibility using a Team object
     * 
     * Checks if the team is allowed to make extension offers based on
     * usage flags for the current season and sim.
     * 
     * @param \Team $team Team object with extension flags
     * @return array Validation result:
     *   - 'valid': bool - True if team can make offers
     *   - 'error': string|null - Error message if not eligible
     * 
     * **Business Rules:**
     * - Cannot extend if hasUsedExtensionThisSeason == 1
     * - Cannot extend if hasUsedExtensionThisSim == 1
     */
    public function validateExtensionEligibility($team);
}
