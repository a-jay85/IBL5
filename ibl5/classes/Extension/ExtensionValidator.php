<?php

declare(strict_types=1);

namespace Extension;

use Extension\Contracts\ExtensionValidatorInterface;
use Services\CommonContractValidator;

/**
 * ExtensionValidator - Validates contract extension offers
 * 
 * Delegates common validation logic to CommonContractValidator while
 * handling Extension-specific validation (team eligibility flags).
 * 
 * @see ExtensionValidatorInterface
 */
class ExtensionValidator implements ExtensionValidatorInterface
{
    private CommonContractValidator $contractValidator;

    public function __construct()
    {
        $this->contractValidator = new CommonContractValidator();
    }

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
            if (empty($value) || $value <= 0) {
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
     * @see ExtensionValidatorInterface::validateMaximumYearOneOffer()
     */
    public function validateMaximumYearOneOffer(array $offer, int $yearsExperience): array
    {
        return $this->contractValidator->validateMaximumYearOne($offer, $yearsExperience);
    }

    /**
     * @see ExtensionValidatorInterface::validateRaises()
     */
    public function validateRaises(array $offer, int $birdYears): array
    {
        return $this->contractValidator->validateRaises($offer, $birdYears);
    }

    /**
     * @see ExtensionValidatorInterface::validateSalaryDecreases()
     */
    public function validateSalaryDecreases(array $offer): array
    {
        return $this->contractValidator->validateSalaryDecreases($offer);
    }

    /**
     * @see ExtensionValidatorInterface::validateExtensionEligibility()
     * 
     * Extension-specific validation - NOT delegated to CommonContractValidator
     */
    public function validateExtensionEligibility(object $team): array
    {
        if ($team->hasUsedExtensionThisSeason == 1) {
            return [
                'valid' => false,
                'error' => 'Sorry, you have already used your extension for this season.'
            ];
        }
        
        if ($team->hasUsedExtensionThisSim == 1) {
            return [
                'valid' => false,
                'error' => 'Sorry, you have already used your extension for this sim.'
            ];
        }
        
        return ['valid' => true, 'error' => null];
    }
}
