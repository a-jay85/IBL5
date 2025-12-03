<?php

declare(strict_types=1);

namespace Draft\Contracts;

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
     * @return bool True if all validation checks pass, false if any fail
     *
     * IMPORTANT BEHAVIORS:
     *  - Returns false if playerName is null or empty string
     *  - Returns false if currentDraftSelection is not null/empty (pick already used)
     *  - Returns false if isPlayerAlreadyDrafted is true (player already drafted)
     *  - Stores first error message found for later retrieval via getErrors()
     *  - Clears previous errors before validation starts
     *  - NEVER throws exceptions
     *
     * Side Effects:
     *  - Populates internal errors array accessible via getErrors()
     *  - Clears previous errors via clearErrors()
     *
     * Error Messages Stored (one message per validation failure):
     *  - "You didn't select a player." – when playerName is null/empty
     *  - "It looks like you've already drafted a player with this draft pick." – when pick used
     *  - "This player has already been drafted by another team." – when player drafted
     *
     * Examples:
     *  $valid = $validator->validateDraftSelection('John Smith', null, false);
     *  // Returns true (all checks pass)
     *  // $validator->getErrors() returns []
     *
     *  $valid = $validator->validateDraftSelection(null, null, false);
     *  // Returns false (no player selected)
     *  // $validator->getErrors() returns ["You didn't select a player."]
     *
     *  $valid = $validator->validateDraftSelection('John Smith', 'Jane Doe', false);
     *  // Returns false (pick already used)
     *  // $validator->getErrors() returns ["It looks like you've already drafted a player..."]
     */
    public function validateDraftSelection(?string $playerName, ?string $currentDraftSelection, bool $isPlayerAlreadyDrafted = false): bool;

    /**
     * Get validation errors from the last validation attempt
     *
     * Returns array of error messages that were populated during the most recent
     * call to validateDraftSelection(). Empty array means validation passed.
     *
     * @return array<int, string> Array of error messages (empty array if no errors)
     *
     * IMPORTANT BEHAVIORS:
     *  - Returns empty array after successful validation
     *  - Returns array with 1-3 error messages after failed validation
     *  - Messages are user-facing and suitable for display
     *  - Each message is a complete sentence ready for HTML display
     *
     * Examples:
     *  $validator->validateDraftSelection('John Smith', null, false);
     *  $errors = $validator->getErrors();
     *  // Returns []
     *
     *  $validator->validateDraftSelection(null, null, false);
     *  $errors = $validator->getErrors();
     *  // Returns ["You didn't select a player."]
     */
    public function getErrors(): array;

    /**
     * Clear all validation errors
     *
     * Empties the internal errors array, preparing for a new validation attempt.
     * This is automatically called at the start of validateDraftSelection().
     *
     * @return void
     *
     * IMPORTANT BEHAVIORS:
     *  - Clears all error messages from previous validation attempts
     *  - Automatically called by validateDraftSelection() before validation
     *  - Safe to call multiple times
     *  - No side effects beyond clearing the internal array
     *
     * Examples:
     *  $validator->clearErrors();
     *  $errors = $validator->getErrors();
     *  // Returns [] (empty array)
     */
    public function clearErrors(): void;
}
