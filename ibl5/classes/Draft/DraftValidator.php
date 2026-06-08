<?php

declare(strict_types=1);

namespace Draft;

use Draft\Contracts\DraftValidatorInterface;
use Validation\ValidationResult;

/**
 * @see DraftValidatorInterface
 */
class DraftValidator implements DraftValidatorInterface
{
    /**
     * @see DraftValidatorInterface::validateDraftSelection()
     */
    public function validateDraftSelection(?string $playerName, ?string $currentDraftSelection, bool $isPlayerAlreadyDrafted = false): ValidationResult
    {
        if ($playerName === null || $playerName === '') {
            return ValidationResult::failure("You didn't select a player.");
        }
        if ($currentDraftSelection !== null && $currentDraftSelection !== '') {
            return ValidationResult::failure("It looks like you've already drafted a player with this draft pick.");
        }
        if ($isPlayerAlreadyDrafted) {
            return ValidationResult::failure("This player has already been drafted by another team.");
        }

        return ValidationResult::success();
    }
}
