<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyOfferValidatorInterface;

/**
 * @see FreeAgencyOfferValidatorInterface
 *
 * @phpstan-type OfferValidationData array{offer1: int, offer2: int, offer3: int, offer4: int, offer5: int, offer6: int, birdYears: int, offerType: int, vetmin: int, year1Max: int, amendedCapSpaceYear1: int}
 */
class FreeAgencyOfferValidator implements FreeAgencyOfferValidatorInterface
{
    /** @var OfferValidationData */
    private array $offerData = [
        'offer1' => 0, 'offer2' => 0, 'offer3' => 0,
        'offer4' => 0, 'offer5' => 0, 'offer6' => 0,
        'birdYears' => 0, 'offerType' => 0, 'vetmin' => 0,
        'year1Max' => 0, 'amendedCapSpaceYear1' => 0,
    ];
    private ?object $team;

    public function __construct(?object $team = null)
    {
        $this->team = $team;
    }

    /**
     * @see FreeAgencyOfferValidatorInterface::validateOffer()
     */
    public function validateOffer(array $offerData): array
    {
        /** @var OfferValidationData $typedData */
        $typedData = $offerData;
        $this->offerData = $typedData;

        // Check for zero first year
        if ($this->offerData['offer1'] === 0) {
            return [
                'valid' => false,
                'error' => 'Sorry, you must enter an amount greater than zero in the first year of a free agency offer. Your offer in Year 1 was zero, so this offer is not valid.'
            ];
        }

        // Check MLE/LLE availability
        $mleCheckResult = $this->validateMLEAvailability();
        if (!$mleCheckResult['valid']) {
            return $mleCheckResult;
        }

        $lleCheckResult = $this->validateLLEAvailability();
        if (!$lleCheckResult['valid']) {
            return $lleCheckResult;
        }

        // Check veteran's minimum
        if ($this->offerData['offer1'] < $this->offerData['vetmin']) {
            return [
                'valid' => false,
                'error' => "Sorry, you must enter an amount greater than the Veteran's Minimum in the first year of a free agency offer.<br>Your offer in Year 1 was <b>{$this->offerData['offer1']}</b>, but should be at least <b>{$this->offerData['vetmin']}</b>."
            ];
        }

        // Check hard cap space
        $hardCapValidation = $this->validateHardCapSpace();
        if (!$hardCapValidation['valid']) {
            return $hardCapValidation;
        }

        // Check soft cap space (if no Bird Rights and not using exceptions)
        if (!\ContractRules::hasBirdRights($this->offerData['birdYears']) && $this->offerData['offerType'] === 0) {
            $softCapValidation = $this->validateSoftCapSpace();
            if (!$softCapValidation['valid']) {
                return $softCapValidation;
            }
        }

        // Check maximum contract value
        $maxContractValidation = $this->validateMaximumContract();
        if (!$maxContractValidation['valid']) {
            return $maxContractValidation;
        }

        // Check raises and contract continuity
        $raiseValidation = $this->validateRaisesAndContinuity();
        if (!$raiseValidation['valid']) {
            return $raiseValidation;
        }

        return ['valid' => true];
    }

    /**
     * Validate MLE (Mid-Level Exception) availability
     *
     * @return array{valid: bool, error?: string}
     */
    private function validateMLEAvailability(): array
    {
        // Only check if using MLE offer type and team is provided
        if ($this->team === null || !OfferType::isMLE($this->offerData['offerType'])) {
            return ['valid' => true];
        }

        // Check if team has already used their MLE
        $hasMLE = $this->getTeamProperty('hasMLE');
        if ($hasMLE !== 1) {
            return [
                'valid' => false,
                'error' => "Sorry, your team has already used the Mid-Level Exception this free agency period. You cannot make another MLE offer."
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate LLE (Lower-Level Exception) availability
     *
     * @return array{valid: bool, error?: string}
     */
    private function validateLLEAvailability(): array
    {
        // Only check if using LLE offer type and team is provided
        if ($this->team === null || !OfferType::isLLE($this->offerData['offerType'])) {
            return ['valid' => true];
        }

        // Check if team has already used their LLE
        $hasLLE = $this->getTeamProperty('hasLLE');
        if ($hasLLE !== 1) {
            return [
                'valid' => false,
                'error' => "Sorry, your team has already used the Lower-Level Exception this free agency period. You cannot make another LLE offer."
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate hard cap space for all contract years
     *
     * @return array{valid: bool, error?: string}
     */
    private function validateHardCapSpace(): array
    {
        $hardCapSpace1 = $this->offerData['amendedCapSpaceYear1'] + (\League::HARD_CAP_MAX - \League::SOFT_CAP_MAX);

        if ($this->offerData['offer1'] > $hardCapSpace1) {
            return [
                'valid' => false,
                'error' => "Sorry, you do not have sufficient cap space under the hard cap to make the offer. You offered {$this->offerData['offer1']} in the first year of the contract, which is more than {$hardCapSpace1}, the amount of hard cap space you have available."
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate soft cap space for first year
     *
     * @return array{valid: bool, error?: string}
     */
    private function validateSoftCapSpace(): array
    {
        if ($this->offerData['offer1'] > $this->offerData['amendedCapSpaceYear1']) {
            return [
                'valid' => false,
                'error' => "Sorry, you do not have sufficient cap space under the soft cap to make the offer. You offered {$this->offerData['offer1']} in the first year of the contract, which is more than {$this->offerData['amendedCapSpaceYear1']}, the amount of soft cap space you have available."
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate maximum contract value
     *
     * @return array{valid: bool, error?: string}
     */
    private function validateMaximumContract(): array
    {
        if ($this->offerData['offer1'] > $this->offerData['year1Max']) {
            return [
                'valid' => false,
                'error' => "Sorry, you tried to offer a contract larger than the maximum allowed for this player based on their years of service. The maximum you are allowed to offer this player is {$this->offerData['year1Max']} in the first year of their contract."
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate contract raises comply with CBA rules and contract has no gaps
     *
     * Ensures:
     * - Raises don't exceed allowed percentage (10% or 12.5% with Bird Rights)
     * - No salary decreases year-over-year
     * - No gaps in contract years (once a year is 0, all following years must be 0)
     *
     * @return array{valid: bool, error?: string}
     */
    private function validateRaisesAndContinuity(): array
    {
        // Determine max raise percentage
        $raisePercentage = \ContractRules::getMaxRaisePercentage($this->offerData['birdYears']);

        $maxRaise = (int) round($this->offerData['offer1'] * $raisePercentage);
        $contractEnded = false;

        // Build array of offer values for easy indexed access
        $offers = [
            1 => $this->offerData['offer1'],
            2 => $this->offerData['offer2'],
            3 => $this->offerData['offer3'],
            4 => $this->offerData['offer4'],
            5 => $this->offerData['offer5'],
            6 => $this->offerData['offer6'],
        ];

        // Check each year's raise and continuity
        for ($year = 2; $year <= 6; $year++) {
            $currentOffer = $offers[$year];
            $previousOffer = $offers[$year - 1];

            // Check if contract ended
            if ($previousOffer === 0) {
                $contractEnded = true;
            }

            // Cannot resume contract after it ends
            if ($contractEnded && $currentOffer > 0) {
                return [
                    'valid' => false,
                    'error' => "Sorry, you cannot have gaps in contract years. You offered 0 in year " . ($year - 1) . " but offered {$currentOffer} in year {$year}."
                ];
            }

            // Check raise amount
            if ($currentOffer > 0 && $previousOffer > 0 && $currentOffer > $previousOffer + $maxRaise) {
                $legalOffer = $previousOffer + $maxRaise;

                return [
                    'valid' => false,
                    'error' => "Sorry, you tried to offer a larger raise than is permitted. Your first year offer was {$this->offerData['offer1']} which means the maximum raise allowed each year is {$maxRaise}. Your offer in Year {$year} was {$currentOffer}, which is more than your Year " . ($year - 1) . " offer, {$previousOffer}, plus the max increase of {$maxRaise}. Given your offer in Year " . ($year - 1) . ", the most you can offer in Year {$year} is {$legalOffer}."
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Get a property value from the team object (supports both Team and stdClass)
     *
     * @param string $property Property name to retrieve
     * @return int Property value, or 0 if not found
     */
    private function getTeamProperty(string $property): int
    {
        if ($this->team instanceof \Team) {
            return match ($property) {
                'hasMLE' => $this->team->hasMLE,
                'hasLLE' => $this->team->hasLLE,
                default => 0,
            };
        }

        if ($this->team !== null) {
            /** @var array<string, int> $vars */
            $vars = get_object_vars($this->team);
            if (array_key_exists($property, $vars)) {
                return $vars[$property];
            }
        }

        return 0;
    }
}
