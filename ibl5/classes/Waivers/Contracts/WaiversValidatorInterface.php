<?php

declare(strict_types=1);

namespace Waivers\Contracts;

use Validation\ValidationResult;

/**
 * WaiversValidatorInterface - Contract for waiver wire validation operations
 *
 * Defines the validation rules for waiver wire transactions. Enforces roster
 * limits, salary cap constraints, and other business rules that govern when
 * a team can add or drop players from the waiver wire.
 *
 * @package Waivers\Contracts
 */
interface WaiversValidatorInterface
{
    /**
     * Validates a drop to waivers operation
     *
     * Checks if a team can drop a player to waivers based on roster size
     * and salary cap constraints.
     *
     * @param int $rosterSlots Total roster slots currently filled (0-15)
     * @param int $totalSalary Total team salary in thousands
     * @return ValidationResult Success if drop is valid; failure with message if not
     *
     * **Business Rules:**
     * - Cannot drop if roster > 2 AND salary > hard cap max
     *   (This prevents dumping salary while over the cap with a full roster)
     *
     * **Usage:**
     * ```php
     * $result = $validator->validateDrop($slots, $salary);
     * if (!$result->isValid()) {
     *     echo implode(' ', $result->getErrors());
     * }
     * ```
     */
    public function validateDrop(int $rosterSlots, int $totalSalary): ValidationResult;

    /**
     * Validates an add from waivers operation
     *
     * Checks if a team can sign a player from waivers based on roster space,
     * salary cap constraints, and player salary.
     *
     * @param int|null $playerID Player ID being added (null or 0 is invalid)
     * @param int $openRosterSlots Open roster spots counting injured players (15 minus every rostered player)
     * @param int $healthyRosterSlots Healthy roster slots available (0-15); drives only the 12-healthy hard-cap rule
     * @param int $totalSalary Current team salary in thousands
     * @param int $playerSalary Salary of player being added in thousands
     * @param int $veteranMinimum Veteran minimum for this player's own experience
     * @return ValidationResult Success if add is valid; failure with message if not
     *
     * **Business Rules:**
     * - Player ID must be a valid positive integer
     * - Must have at least 1 open roster slot (injured players count against the roster)
     * - If 12+ healthy players: cannot exceed hard cap with signing
     * - If under 12 healthy players but over hard cap: can only sign at the player's own veteran minimum
     *
     * **Constants Used:**
     * - League::HARD_CAP_MAX - Maximum salary cap limit
     * - Veteran minimum: the player's own, passed in as $veteranMinimum
     */
    public function validateAdd(
        ?int $playerID,
        int $openRosterSlots,
        int $healthyRosterSlots,
        int $totalSalary,
        int $playerSalary,
        int $veteranMinimum
    ): ValidationResult;
}
