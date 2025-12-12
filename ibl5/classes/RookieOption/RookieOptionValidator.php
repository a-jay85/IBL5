<?php

declare(strict_types=1);

namespace RookieOption;

use Services\CommonValidator;
use RookieOption\Contracts\RookieOptionValidatorInterface;

/**
 * @see RookieOptionValidatorInterface
 */
class RookieOptionValidator implements RookieOptionValidatorInterface
{
    /**
     * @see RookieOptionValidatorInterface::validatePlayerOwnership()
     */
    public function validatePlayerOwnership($player, string $userTeamName): array
    {
        return CommonValidator::validatePlayerOwnership($player, $userTeamName);
    }
    
    /**
     * @see RookieOptionValidatorInterface::validateEligibilityAndGetSalary()
     */
    public function validateEligibilityAndGetSalary($player, string $seasonPhase): array
    {
        $canRookieOption = $player->canRookieOption($seasonPhase);
        $finalYearSalary = $canRookieOption ? $player->getFinalYearRookieContractSalary() : 0;
        
        if (!$canRookieOption || $finalYearSalary === 0) {
            return [
                'valid' => false,
                'error' => $this->getIneligibilityError($player)
            ];
        }
        
        return [
            'valid' => true,
            'finalYearSalary' => $finalYearSalary
        ];
    }

    private function getIneligibilityError($player): string
    {
        return 'Sorry, ' . $player->position . ' ' . $player->name . ' is not eligible for a rookie option.' . "\n\n" .
        'Only first or second round draft picks with 3 or fewer years of experience are eligible for rookie options, and the option must be exercised before the final season of their rookie contract is underway.';
    }
}
