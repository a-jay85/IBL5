<?php

declare(strict_types=1);

namespace Draft\Contracts;

use Validation\ValidationResult;

/**
 * DraftValidatorInterface - Contract for draft selection validation
 *
 * Defines validation rules for draft selections, ensuring players exist,
 * picks are available, and players haven't been drafted already.
 */
interface DraftValidatorInterface
{
    /**
     * Validate a draft selection
     *
     * Performs multi-step validation to ensure:
     * 1. A player was selected
     * 2. The pick hasn't already been used
     * 3. The player hasn't already been drafted
     *
     * @param string|null $playerName The name of the player to draft (null or empty = not selected)
     * @param string|null $currentDraftSelection The player already selected for this pick (null = available)
     * @param bool $isPlayerAlreadyDrafted Whether the player has already been drafted by another team
     * @return ValidationResult Success if all checks pass; failure with message if any fail
     *
     * IMPORTANT BEHAVIORS:
     *  - Returns failure if playerName is null or empty string
     *  - Returns failure if currentDraftSelection is not null/empty (pick already used)
     *  - Returns failure if isPlayerAlreadyDrafted is true (player already drafted)
     *  - NEVER throws exceptions
     *
     * Error Messages (one per validation failure):
     *  - "You didn't select a player." – when playerName is null/empty
     *  - "It looks like you've already drafted a player with this draft pick." – when pick used
     *  - "This player has already been drafted by another team." – when player drafted
     *
     * Examples:
     *  $result = $validator->validateDraftSelection('John Smith', null, false);
     *  // $result->isValid() === true
     *  // $result->getErrors() === []
     *
     *  $result = $validator->validateDraftSelection(null, null, false);
     *  // $result->isValid() === false
     *  // $result->getErrors() === ["You didn't select a player."]
     *
     *  $result = $validator->validateDraftSelection('John Smith', 'Jane Doe', false);
     *  // $result->isValid() === false
     *  // $result->getErrors() === ["It looks like you've already drafted a player..."]
     */
    public function validateDraftSelection(?string $playerName, ?string $currentDraftSelection, bool $isPlayerAlreadyDrafted = false): ValidationResult;
}
