<?php

namespace Draft;

/**
 * Validates draft selection operations
 * 
 * Responsibilities:
 * - Validate player selection
 * - Validate draft pick availability
 * - Generate validation error messages
 */
class DraftValidator
{
    private $errors = [];

    /**
     * Validate a draft selection
     * 
     * @param string|null $playerName The name of the player to draft
     * @param string|null $currentDraftSelection The player already selected for this pick (if any)
     * @return bool True if validation passes, false otherwise
     */
    public function validateDraftSelection($playerName, $currentDraftSelection)
    {
        $this->clearErrors();

        // Validate player was selected
        if ($playerName === null || $playerName === '') {
            $this->errors[] = "You didn't select a player.";
            return false;
        }

        // Validate pick hasn't already been used
        if ($currentDraftSelection !== null && $currentDraftSelection !== '') {
            $this->errors[] = "It looks like you've already drafted a player with this draft pick.";
            return false;
        }

        return true;
    }

    /**
     * Get validation errors
     * 
     * @return array Array of error messages
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Clear all validation errors
     */
    public function clearErrors()
    {
        $this->errors = [];
    }
}
