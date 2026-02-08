<?php

declare(strict_types=1);

namespace RookieOption;

use Player\Player;
use Services\CommonValidator;
use RookieOption\Contracts\RookieOptionValidatorInterface;

/**
 * @see RookieOptionValidatorInterface
 */
class RookieOptionValidator implements RookieOptionValidatorInterface
{
    /**
     * @see RookieOptionValidatorInterface::validatePlayerOwnership()
     *
     * @return array{valid: bool, error?: string}
     */
    public function validatePlayerOwnership(Player $player, string $userTeamName): array
    {
        /** @var array{valid: bool, error?: string} */
        return CommonValidator::validatePlayerOwnership($player, $userTeamName);
    }

    /**
     * @see RookieOptionValidatorInterface::validateEligibilityAndGetSalary()
     *
     * @return array{valid: bool, error?: string, finalYearSalary?: int}
     */
    public function validateEligibilityAndGetSalary(Player $player, string $seasonPhase): array
    {
        $canRookieOption = $player->canRookieOption($seasonPhase);
        $finalYearSalary = $canRookieOption ? $player->getFinalYearRookieContractSalary() : 0;

        if (!$canRookieOption || $finalYearSalary === 0) {
            return [
                'valid' => false,
                'error' => $this->getIneligibilityError($player),
            ];
        }

        return [
            'valid' => true,
            'finalYearSalary' => $finalYearSalary,
        ];
    }

    private function getIneligibilityError(Player $player): string
    {
        $position = $player->position ?? 'Unknown';
        $name = $player->name ?? 'Unknown';
        return 'Sorry, ' . $position . ' ' . $name . ' is not eligible for a rookie option.' . "\n\n" .
        'Only first or second round draft picks with 3 or fewer years of experience are eligible for rookie options, and the option must be exercised before the final season of their rookie contract is underway.';
    }
}
