<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\CommonContractValidatorInterface;
use FreeAgency\Contracts\FreeAgencyOfferValidatorInterface;
use FreeAgency\Contracts\FreeAgencyRepositoryInterface;
use League\League;
use Team\Team;

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
    private ?Team $team;
    private ?FreeAgencyRepositoryInterface $repository;
    private int $playerId;
    private CommonContractValidatorInterface $contractValidator;

    public function __construct(
        ?Team $team = null,
        ?FreeAgencyRepositoryInterface $repository = null,
        int $playerId = 0,
        ?CommonContractValidatorInterface $contractValidator = null
    ) {
        $this->team = $team;
        $this->repository = $repository;
        $this->playerId = $playerId;
        $this->contractValidator = $contractValidator ?? new CommonContractValidator();
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
                'error' => "Sorry, you must enter an amount greater than the Veteran's Minimum in the first year of a free agency offer. Your offer in Year 1 was " . (int) $this->offerData['offer1'] . ", but should be at least " . (int) $this->offerData['vetmin'] . "."
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
     * Two rules enforced, in order:
     * 1. The team's MLE for this FA period has not already been consumed by
     *    an accepted signing (`ibl_team_info.has_mle = 0`).
     * 2. The team does not already have a pending MLE offer outstanding to
     *    a different player in `ibl_fa_offers`. A GM may only hold one
     *    pending MLE offer at any given time — they must rescind the prior
     *    offer before making a new one to a different player.
     *
     * @return array{valid: bool, error?: string}
     */
    private function validateMLEAvailability(): array
    {
        // Only check if using MLE offer type and team is provided
        if ($this->team === null || !OfferType::isMLE($this->offerData['offerType'])) {
            return ['valid' => true];
        }

        // Rule 1: MLE already consumed by an accepted signing this FA period
        $hasMLE = $this->team->has_mle;
        if ($hasMLE !== 1) {
            return [
                'valid' => false,
                'error' => "Sorry, your team has already used the Mid-Level Exception this free agency period. You cannot make another MLE offer."
            ];
        }

        // Rule 2: Team already has a pending MLE offer to another player
        if (
            $this->repository !== null
            && $this->repository->hasPendingMleOffer($this->team->teamid, $this->playerId)
        ) {
            return [
                'valid' => false,
                'error' => "Sorry, your team already has a pending Mid-Level Exception offer to another player. Rescind that offer on the Free Agency page before making a new MLE offer."
            ];
        }

        return ['valid' => true];
    }

    /**
     * Validate LLE (Lower-Level Exception) availability
     *
     * @see self::validateMLEAvailability() for the two-rule structure
     * (consumed-flag check + pending-offer check). Same rules, applied to
     * the Lower-Level Exception.
     *
     * @return array{valid: bool, error?: string}
     */
    private function validateLLEAvailability(): array
    {
        // Only check if using LLE offer type and team is provided
        if ($this->team === null || !OfferType::isLLE($this->offerData['offerType'])) {
            return ['valid' => true];
        }

        // Rule 1: LLE already consumed by an accepted signing this FA period
        $hasLLE = $this->team->has_lle;
        if ($hasLLE !== 1) {
            return [
                'valid' => false,
                'error' => "Sorry, your team has already used the Lower-Level Exception this free agency period. You cannot make another LLE offer."
            ];
        }

        // Rule 2: Team already has a pending LLE offer to another player
        if (
            $this->repository !== null
            && $this->repository->hasPendingLleOffer($this->team->teamid, $this->playerId)
        ) {
            return [
                'valid' => false,
                'error' => "Sorry, your team already has a pending Lower-Level Exception offer to another player. Rescind that offer on the Free Agency page before making a new LLE offer."
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
        $hardCapSpace1 = $this->offerData['amendedCapSpaceYear1'] + (League::HARD_CAP_MAX - League::SOFT_CAP_MAX);

        if ($this->offerData['offer1'] > $hardCapSpace1) {
            return [
                'valid' => false,
                'error' => "Sorry, you do not have sufficient cap space under the hard cap to make the offer. You offered " . (int) $this->offerData['offer1'] . " in the first year of the contract, which is more than {$hardCapSpace1}, the amount of hard cap space you have available."
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
                'error' => "Sorry, you do not have sufficient cap space under the soft cap to make the offer. You offered " . (int) $this->offerData['offer1'] . " in the first year of the contract, which is more than " . (int) $this->offerData['amendedCapSpaceYear1'] . ", the amount of soft cap space you have available."
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
                'error' => "Sorry, you tried to offer a contract larger than the maximum allowed for this player based on their years of service. The maximum you are allowed to offer this player is " . (int) $this->offerData['year1Max'] . " in the first year of their contract."
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
        // Build array of offer values keyed year1..year6 for the shared validator.
        $offers = [
            1 => $this->offerData['offer1'],
            2 => $this->offerData['offer2'],
            3 => $this->offerData['offer3'],
            4 => $this->offerData['offer4'],
            5 => $this->offerData['offer5'],
            6 => $this->offerData['offer6'],
        ];
        $offer = [
            'year1' => $offers[1], 'year2' => $offers[2], 'year3' => $offers[3],
            'year4' => $offers[4], 'year5' => $offers[5], 'year6' => $offers[6],
        ];

        // Gaps first: CommonContractValidator::validateRaises() has no "previous
        // year > 0" guard, so a gap (0 followed by a non-zero year) must be
        // reported before raises are evaluated.
        $gapResult = $this->contractValidator->validateNoGaps($offer);
        if ($gapResult['valid'] === false) {
            return ['valid' => false, 'error' => $gapResult['error'] ?? ''];
        }

        // Decreases stay local: CommonContractValidator::validateSalaryDecreases()
        // only scans years 1-5, so delegating would stop rejecting a decrease in
        // the sixth year. This loop preserves the original year-2..6 coverage and
        // error wording (locked by characterization tests).
        for ($year = 2; $year <= 6; $year++) {
            $currentOffer = $offers[$year];
            $previousOffer = $offers[$year - 1];

            if ($currentOffer > 0 && $previousOffer > 0 && $currentOffer < $previousOffer) {
                return [
                    'valid' => false,
                    'error' => "Sorry, you cannot decrease salary in later years of a contract. You offered {$currentOffer} in year {$year}, which is less than you offered in year " . ($year - 1) . ", {$previousOffer}."
                ];
            }
        }

        // Raises: identical formula and message to the shared validator.
        $raiseResult = $this->contractValidator->validateRaises($offer, $this->offerData['birdYears']);
        if ($raiseResult['valid'] === false) {
            return ['valid' => false, 'error' => $raiseResult['error'] ?? ''];
        }

        return ['valid' => true];
    }

}
