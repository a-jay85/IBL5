<?php

namespace Waivers;

use Season;
use Player\Player;
use Player\PlayerContractCalculator;
use Services\PlayerDataConverter;
use Waivers\Contracts\WaiversProcessorInterface;

/**
 * @see WaiversProcessorInterface
 */
class WaiversProcessor implements WaiversProcessorInterface
{
    private PlayerContractCalculator $contractCalculator;
    
    public function __construct()
    {
        $this->contractCalculator = new PlayerContractCalculator();
    }
    
    /**
     * @see WaiversProcessorInterface::calculateVeteranMinimumSalary()
     */
    public function calculateVeteranMinimumSalary(int $experience): int
    {
        return \ContractRules::getVeteranMinimumSalary($experience);
    }
    
    /**
     * @see WaiversProcessorInterface::getPlayerContractDisplay()
     */
    public function getPlayerContractDisplay(Player $player, Season $season): string
    {
        $playerArray = [
            'cy' => $player->contractCurrentYear,
            'cyt' => $player->contractTotalYears,
            'cy1' => $player->contractYear1Salary,
            'cy2' => $player->contractYear2Salary,
            'cy3' => $player->contractYear3Salary,
            'cy4' => $player->contractYear4Salary,
            'cy5' => $player->contractYear5Salary,
            'cy6' => $player->contractYear6Salary,
            'exp' => $player->yearsOfExperience,
        ];
        $playerData = PlayerDataConverter::arrayToPlayerData($playerArray);
        
        if ($season->phase === 'Free Agency') {
            $currentSeasonSalary = $this->contractCalculator->getNextSeasonSalary($playerData);
            $experience = $playerData->yearsOfExperience + 1;
        } else {
            $currentSeasonSalary = $this->contractCalculator->getCurrentSeasonSalary($playerData);
            $experience = $playerData->yearsOfExperience;
        }
        
        if ($currentSeasonSalary == 0) {
            return (string) $this->calculateVeteranMinimumSalary($experience);
        }
        
        $remainingContract = $this->contractCalculator->getRemainingContractArray($playerData);
        return implode(" ", $remainingContract);
    }
    
    /**
     * @see WaiversProcessorInterface::getWaiverWaitTime()
     */
    public function getWaiverWaitTime(int $dropTime, int $currentTime): string
    {
        $timeDiff = $currentTime - $dropTime;
        $waitPeriod = 86400; // 24 hours in seconds
        
        if ($timeDiff >= $waitPeriod) {
            return '';
        }
        
        $remainingTime = $waitPeriod - $timeDiff;
        $hours = floor($remainingTime / 3600);
        $minutes = floor(($remainingTime - $hours * 3600) / 60);
        $seconds = $remainingTime % 60;
        
        return "(Clears in $hours h, $minutes m, $seconds s)";
    }
    
    /**
     * @see WaiversProcessorInterface::determineContractData()
     */
    public function determineContractData(array $playerData, Season $season): array
    {
        $playerDataObj = PlayerDataConverter::arrayToPlayerData($playerData);
        
        // Determine current season salary and experience based on phase
        if ($season->phase === 'Free Agency') {
            $currentSeasonSalary = $this->contractCalculator->getNextSeasonSalary($playerDataObj);
            $experience = $playerDataObj->yearsOfExperience + 1;
        } else {
            $currentSeasonSalary = $this->contractCalculator->getCurrentSeasonSalary($playerDataObj);
            $experience = $playerDataObj->yearsOfExperience;
        }
    
        $hasExistingContract = $currentSeasonSalary > 0;
        
        if ($hasExistingContract) {
            return [
                'hasExistingContract' => true,
                'salary' => $currentSeasonSalary
            ];
        }
            
        // No existing contract: assign veteran minimum based on experience
        $vetMinSalary = $this->calculateVeteranMinimumSalary($experience);
        
        return [
            'hasExistingContract' => false,
            'salary' => $vetMinSalary
        ];
    }
}
