<?php

declare(strict_types=1);

namespace Negotiation;

use Negotiation\Contracts\NegotiationValidatorInterface;
use Negotiation\Contracts\NegotiationRepositoryInterface;
use Player\Player;
use Player\PlayerContractValidator;
use Player\PlayerData;
use Services\CommonValidator;

/**
 * @see NegotiationValidatorInterface
 */
class NegotiationValidator implements NegotiationValidatorInterface
{
    private object $db;
    private NegotiationRepositoryInterface $repository;
    private PlayerContractValidator $contractValidator;
    
    public function __construct(object $db)
    {
        $this->db = $db;
        $this->repository = new NegotiationRepository($db);
        $this->contractValidator = new PlayerContractValidator();
    }
    
    /**
     * @see NegotiationValidatorInterface::validateNegotiationEligibility()
     */
    public function validateNegotiationEligibility(Player $player, string $userTeamName): array
    {
        // Check if player is on user's team using common validator
        $ownershipResult = CommonValidator::validatePlayerOwnership($player, $userTeamName);
        if (!$ownershipResult['valid']) {
            return $ownershipResult;
        }
        
        // Create PlayerData object for contract validator
        $playerData = $this->createPlayerData($player);
        
        // Check if player can renegotiate contract
        if (!$this->contractValidator->canRenegotiateContract($playerData)) {
            return [
                'valid' => false,
                'error' => 'Sorry, this player is not eligible for a contract extension at this time.'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * @see NegotiationValidatorInterface::validateFreeAgencyNotActive()
     */
    public function validateFreeAgencyNotActive(): array
    {   
        $isActive = $this->repository->isFreeAgencyActive();
        
        if ($isActive) {
            return [
                'valid' => false,
                'error' => 'Sorry, the contract extension feature is not available during free agency.'
            ];
        }
        
        return ['valid' => true];
    }
    
    /**
     * Create a PlayerData object from Player for contract validation
     */
    private function createPlayerData(Player $player): PlayerData
    {
        $playerData = new PlayerData();
        
        // Map contract fields
        $playerData->contractCurrentYear = $player->contractCurrentYear ?? 0;
        $playerData->contractYear1Salary = $player->contractYear1Salary ?? 0;
        $playerData->contractYear2Salary = $player->contractYear2Salary ?? 0;
        $playerData->contractYear3Salary = $player->contractYear3Salary ?? 0;
        $playerData->contractYear4Salary = $player->contractYear4Salary ?? 0;
        $playerData->contractYear5Salary = $player->contractYear5Salary ?? 0;
        $playerData->contractYear6Salary = $player->contractYear6Salary ?? 0;
        
        // Map fields needed for rookie option check
        $playerData->draftRound = $player->draftRound ?? 0;
        $playerData->yearsOfExperience = $player->yearsOfExperience ?? 0;
        
        return $playerData;
    }
}
