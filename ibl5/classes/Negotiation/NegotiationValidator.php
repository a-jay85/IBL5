<?php

declare(strict_types=1);

namespace Negotiation;

use Negotiation\Contracts\NegotiationValidatorInterface;
use Player\Player;
use Player\PlayerContractValidator;
use Player\PlayerData;
use Services\ValidationResult;

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

        $playerData->contractCurrentYear = $player->contractCurrentYear ?? 0;
        $playerData->contractYear1Salary = $player->contractYear1Salary ?? 0;
        $playerData->contractYear2Salary = $player->contractYear2Salary ?? 0;
        $playerData->contractYear3Salary = $player->contractYear3Salary ?? 0;
        $playerData->contractYear4Salary = $player->contractYear4Salary ?? 0;
        $playerData->contractYear5Salary = $player->contractYear5Salary ?? 0;
        $playerData->contractYear6Salary = $player->contractYear6Salary ?? 0;

        $playerData->draftRound = $player->draftRound ?? 0;
        $playerData->yearsOfExperience = $player->yearsOfExperience ?? 0;

        return $playerData;
    }
}
