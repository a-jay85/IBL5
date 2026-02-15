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
