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
    private $db;
    private CommonContractValidator $contractValidator;

    public function __construct($db)
    {
        $this->db = $db;
        $this->contractValidator = new CommonContractValidator();
    }

    /**
     * @see ExtensionValidatorInterface::validateOfferAmounts()
     */
    public function validateOfferAmounts(array $offer): array
    {
        return $this->contractValidator->validateOfferAmounts($offer);
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
