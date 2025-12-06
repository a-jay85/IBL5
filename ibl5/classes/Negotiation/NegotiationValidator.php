<?php

declare(strict_types=1);

namespace Negotiation;

use Negotiation\Contracts\NegotiationValidatorInterface;
use Player\Player;
use Player\PlayerContractValidator;
use Player\PlayerData;
use Services\CommonValidator;

/**
 * @see NegotiationValidatorInterface
 */
class NegotiationValidator implements NegotiationValidatorInterface
{
    private $db;
    private $contractValidator;
    
    public function __construct($db)
    {
        $this->db = $db;
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
    public function validateFreeAgencyNotActive(string $prefix): array
    {   
        $query = "SELECT active FROM {$prefix}_modules WHERE title = 'Free_Agency'";
        $result = $this->db->sql_query($query);
        $row = $this->db->sql_fetchrow($result);
        
        $isActive = isset($row['active']) && (int)$row['active'] === 1;
        
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
