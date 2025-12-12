<?php

declare(strict_types=1);

namespace Extension\Contracts;

/**
 * ExtensionValidatorInterface - Contract for extension validation operations
 * 
 * Defines validation rules for contract extension offers. Enforces business rules
 * around offer amounts, maximum salaries, raise percentages, and team eligibility.
 * 
 * Note: validateOfferAmounts() is Extension-specific because other contract types
 * (Free Agency, Rookie Options, Waivers) do not require the first three years to
 * have non-zero amounts.
 * 
 * Delegates other validation logic to Services\CommonContractValidator for
 * consistency across Extension, FreeAgency, and Negotiation modules.
 * 
 * @package Extension\Contracts
 * @see \Services\Contracts\CommonContractValidatorInterface
 */
interface ExtensionValidatorInterface
{
    /**
     * Validates that the first three years of the offer have non-zero amounts
     * 
     * Extension-specific validation - ONLY applicable to extensions.
     * Free Agency, Rookie Options, and Waivers do not have this requirement.
     * 
     * Extensions must include at least 3 guaranteed years. Years 4 and 5 are optional.
     * 
     * @param array{year1: int, year2: int, year3: int, year4?: int, year5?: int} $offer 
     *        Contract offer with yearly salary amounts in thousands
     * @return array{valid: bool, error: string|null} Validation result:
     *         - 'valid': bool - True if all required years have amounts
     *         - 'error': string|null - Error message if invalid, null if valid
     * 
     * IMPORTANT BEHAVIORS:
     *  - Extension-specific rule - NOT shared with other contract types
     *  - year1, year2, year3 must all be greater than zero
     *  - year4 and year5 can be zero (for 3 or 4-year deals)
     */
    public function validateOfferAmounts(array $offer): array;

    /**
     * Validates that the offer doesn't exceed the maximum allowed for player's experience
     * 
     * Year-one salary is capped based on years of service.
     * 
     * @param array{year1: int, year2?: int, year3?: int, year4?: int, year5?: int} $offer
     *        Contract offer with yearly salary amounts in thousands
     * @param int $yearsExperience Player's years of experience
     * @return array{valid: bool, error: string|null} Validation result:
     *         - 'valid': bool - True if year1 is within maximum
     *         - 'error': string|null - Error message if over maximum
     * 
     * IMPORTANT BEHAVIORS:
     *  - Delegates to CommonContractValidator::validateMaximumYearOne()
     *  - Maximum Salary Tiers: 0-6 years = 1063, 7-9 years = 1275, 10+ years = 1451
     * 
     * @see \Services\CommonContractValidator::validateMaximumYearOne()
     * @see \ContractRules::MAX_CONTRACT_SALARIES
     */
    public function validateMaximumYearOneOffer(array $offer, int $yearsExperience): array;

    /**
     * Validates that raises between years don't exceed allowed percentages
     * 
     * Raises are limited based on Bird rights years and the first year salary.
     * 
     * @param array{year1: int, year2: int, year3: int, year4?: int, year5?: int} $offer
     *        Contract offer with yearly salary amounts in thousands
     * @param int $birdYears Years with Bird rights (affects max raise percentage)
     * @return array{valid: bool, error: string|null} Validation result:
     *         - 'valid': bool - True if all raises are within limits
     *         - 'error': string|null - Detailed error message if raise exceeded
     * 
     * IMPORTANT BEHAVIORS:
     *  - Delegates to CommonContractValidator::validateRaises()
     *  - Max raise = year1 * maxRaisePercentage (from ContractRules)
     *  - Each year checked against previous year + max raise
     *  - Error includes specific year and amounts for user feedback
     *  - Bird Years Impact: 3+ years = 12.5% max raise, <3 years = 10% max raise
     * 
     * @see \Services\CommonContractValidator::validateRaises()
     * @see \ContractRules::getMaxRaisePercentage()
     */
    public function validateRaises(array $offer, int $birdYears): array;

    /**
     * Validates that salaries don't decrease in later years (except to zero)
     * 
     * Contract salaries must be flat or increasing, except when a year is
     * set to zero (indicating end of contract).
     * 
     * @param array{year1: int, year2: int, year3: int, year4?: int, year5?: int} $offer
     *        Contract offer with yearly salary amounts in thousands
     * @return array{valid: bool, error: string|null} Validation result:
     *         - 'valid': bool - True if no invalid decreases
     *         - 'error': string|null - Error message if salary decreased
     * 
     * IMPORTANT BEHAVIORS:
     *  - Delegates to CommonContractValidator::validateSalaryDecreases()
     *  - year[n] < year[n-1] is invalid UNLESS year[n] == 0
     *  - Zero indicates contract ends, not a salary decrease
     * 
     * @see \Services\CommonContractValidator::validateSalaryDecreases()
     */
    public function validateSalaryDecreases(array $offer): array;

    /**
     * Validates extension eligibility using a Team object
     * 
     * Checks if the team is allowed to make extension offers based on
     * usage flags for the current season and sim.
     * 
     * @param object $team Team object with extension flags:
     *        - hasUsedExtensionThisSeason: int (0 or 1)
     *        - hasUsedExtensionThisSim: int (0 or 1)
     * @return array{valid: bool, error: string|null} Validation result:
     *         - 'valid': bool - True if team can make offers
     *         - 'error': string|null - Error message if not eligible
     * 
     * IMPORTANT BEHAVIORS:
     *  - This is Extension-specific logic (NOT delegated to CommonContractValidator)
     *  - Cannot extend if hasUsedExtensionThisSeason == 1
     *  - Cannot extend if hasUsedExtensionThisSim == 1
     */
    public function validateExtensionEligibility(object $team): array;
}
