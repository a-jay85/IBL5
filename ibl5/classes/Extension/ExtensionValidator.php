<?php

declare(strict_types=1);

namespace Extension;

use Extension\Contracts\ExtensionValidatorInterface;

/**
 * ExtensionValidator - Validates contract extension offers
 *
 * Handles Extension-specific validation (offer amounts, team eligibility flags).
 *
 * @see ExtensionValidatorInterface
 */
class ExtensionValidator implements ExtensionValidatorInterface
{
    /**
     * @see ExtensionValidatorInterface::validateOfferAmounts()
     *
     * Extension-specific validation - NOT delegated to CommonContractValidator
     * because Free Agency, Rookie Options, and Waivers do not have this requirement
     */
    public function validateOfferAmounts(array $offer): array
    {
        $requiredYears = ['year1', 'year2', 'year3'];

        foreach ($requiredYears as $year) {
            $value = $offer[$year] ?? 0;
            if ($value === 0 || $value <= 0) {
                $yearLabel = ucfirst($year);
                return [
                    'valid' => false,
                    'error' => "Sorry, you must enter an amount greater than zero for each of the first three contract years when making an extension offer. Your offer in {$yearLabel} was zero, so this offer is not valid."
                ];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * @see ExtensionValidatorInterface::validateExtensionEligibility()
     *
     * Extension-specific validation - NOT delegated to CommonContractValidator
     *
     * @return array{valid: bool, error: string|null}
     */
    public function validateExtensionEligibility(object $team): array
    {
        /** @var \Team $team */
        if ($team->hasUsedExtensionThisSeason === 1) {
            return [
                'valid' => false,
                'error' => 'Sorry, you have already used your extension for this season.'
            ];
        }

        if ($team->hasUsedExtensionThisSim === 1) {
            return [
                'valid' => false,
                'error' => 'Sorry, you have already used your extension for this sim.'
            ];
        }

        return ['valid' => true, 'error' => null];
    }
}
