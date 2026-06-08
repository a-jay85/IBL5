<?php

declare(strict_types=1);

namespace Waivers;

use League\League;
use Validation\ValidationResult;
use Waivers\Contracts\WaiversValidatorInterface;

/**
 * @see WaiversValidatorInterface
 */
class WaiversValidator implements WaiversValidatorInterface
{
    /**
     * @see WaiversValidatorInterface::validateDrop()
     */
    public function validateDrop(int $rosterSlots, int $totalSalary): ValidationResult
    {
        if ($rosterSlots > 2 && $totalSalary > League::HARD_CAP_MAX) {
            return ValidationResult::failure("You have 12 players and are over the hard cap. Therefore you can't drop a player!");
        }

        return ValidationResult::success();
    }

    /**
     * @see WaiversValidatorInterface::validateAdd()
     */
    public function validateAdd(
        ?int $playerID,
        int $healthyRosterSlots,
        int $totalSalary,
        int $playerSalary
    ): ValidationResult {
        if ($playerID === null || $playerID === 0) {
            return ValidationResult::failure("You didn't select a valid player. Please select a player and try again.");
        }

        if ($healthyRosterSlots < 1) {
            return ValidationResult::failure("You have a full roster. You can't sign another player at this time!");
        }

        $newTotalSalary = $totalSalary + $playerSalary;

        // If 12+ healthy players and signing puts over hard cap
        if ($healthyRosterSlots < 4 && $newTotalSalary > League::HARD_CAP_MAX) {
            return ValidationResult::failure("You have 12 or more healthy players and this signing will put you over the hard cap. Therefore you cannot make this signing.");
        }

        // If under 12 healthy players but over hard cap and player salary > vet min
        if ($healthyRosterSlots > 3 && $newTotalSalary > League::HARD_CAP_MAX && $playerSalary > \ContractRules::getVeteranMinimumSalary(10)) {
            return ValidationResult::failure("You are over the hard cap and therefore can only sign players who are making veteran minimum!");
        }

        return ValidationResult::success();
    }
}
