<?php

declare(strict_types=1);

namespace Services;

/**
 * Common Validator Service
 *
 * Centralized validation logic for common business rules that appear across
 * multiple validators. This eliminates duplication and ensures consistency.
 */
class CommonValidator
{
    /**
     * Validates that a player belongs to the specified team
     *
     * @param object&\Player\Player $player Player object with teamName, position, and name properties
     * @param string $userTeamName The team name to validate against
     */
    public static function validatePlayerOwnership(object $player, string $userTeamName): ValidationResult
    {
        /** @var \Player\Player $player */
        if ($player->teamName !== $userTeamName) {
            $position = $player->position ?? '';
            $name = $player->name ?? '';
            $playerDescription = ($position !== '' && $name !== '')
                ? "{$position} {$name}"
                : "This player";

            return ValidationResult::failure("Sorry, {$playerDescription} is not on your team.");
        }

        return ValidationResult::success();
    }
}
