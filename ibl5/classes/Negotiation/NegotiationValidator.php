<?php

declare(strict_types=1);

namespace Negotiation;

use Negotiation\Contracts\NegotiationValidatorInterface;
use Player\Player;
use Player\Contract\PlayerContractValidator;
use Player\PlayerData;
use Validation\CommonValidator;
use Validation\ValidationResult;

/**
 * @see NegotiationValidatorInterface
 */
class NegotiationValidator implements NegotiationValidatorInterface
{
    private PlayerContractValidator $contractValidator;
    private \Season\Season $season;

    public function __construct(\mysqli $db, ?\Season\Season $season = null)
    {
        $this->contractValidator = new PlayerContractValidator();
        $this->season = $season ?? new \Season\Season($db);
    }

    /**
     * @see NegotiationValidatorInterface::validateNegotiationEligibility()
     */
    public function validateNegotiationEligibility(Player $player, string $userTeamName): ValidationResult
    {
        $ownershipResult = CommonValidator::validatePlayerOwnership($player, $userTeamName);
        if (!$ownershipResult->isValid()) {
            return $ownershipResult;
        }

        $playerData = $this->createPlayerData($player);

        if (!$this->contractValidator->canRenegotiateContract($playerData)) {
            return ValidationResult::failure('Sorry, this player is not eligible for a contract extension at this time.');
        }

        return ValidationResult::success();
    }

    /**
     * @see NegotiationValidatorInterface::validateRenegotiationEligibility()
     */
    public function validateRenegotiationEligibility(Player $player): ValidationResult
    {
        $playerData = $this->createPlayerData($player);

        if (!$this->contractValidator->canRenegotiateContract($playerData)) {
            return ValidationResult::failure('Sorry, this player is not eligible for a contract extension at this time.');
        }

        return ValidationResult::success();
    }

    /**
     * @see NegotiationValidatorInterface::validateFreeAgencyNotActive()
     */
    public function validateFreeAgencyNotActive(): ValidationResult
    {
        if ($this->season->phase === 'Free Agency') {
            return ValidationResult::failure('Sorry, the contract extension feature is not available during free agency.');
        }

        return ValidationResult::success();
    }

    /**
     * Create a PlayerData object from Player for contract validation
     */
    private function createPlayerData(Player $player): PlayerData
    {
        $playerData = new PlayerData();

        $playerData->contractCurrentYear = $player->getContractCurrentYear() ?? 0;
        $playerData->contractYear1Salary = $player->getContractYear1Salary() ?? 0;
        $playerData->contractYear2Salary = $player->getContractYear2Salary() ?? 0;
        $playerData->contractYear3Salary = $player->getContractYear3Salary() ?? 0;
        $playerData->contractYear4Salary = $player->getContractYear4Salary() ?? 0;
        $playerData->contractYear5Salary = $player->getContractYear5Salary() ?? 0;
        $playerData->contractYear6Salary = $player->getContractYear6Salary() ?? 0;

        $playerData->draftRound = $player->getDraftRound() ?? 0;
        $playerData->yearsOfExperience = $player->getYearsOfExperience() ?? 0;

        return $playerData;
    }
}
