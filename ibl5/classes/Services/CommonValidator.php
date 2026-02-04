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
     * @return array{valid: bool, error?: string} Validation result with 'valid' boolean and optional 'error' message
     *
     * @example
     * $result = CommonValidator::validatePlayerOwnership($player, 'Seattle Supersonics');
     * if (!$result['valid']) {
     *     echo $result['error'];
     * }
     */
    public static function validatePlayerOwnership(object $player, string $userTeamName): array
    {
        /** @var \Player\Player $player */
        if ($player->teamName !== $userTeamName) {
            // Include player details in error message for better user feedback
            $position = $player->position ?? '';
            $name = $player->name ?? '';
            $playerDescription = ($position !== '' && $name !== '')
                ? "{$position} {$name}"
                : "This player";

            return [
                'valid' => false,
                'error' => "Sorry, {$playerDescription} is not on your team."
            ];
        }

        return ['valid' => true];
    }
}
