<?php

namespace Draft;

use Services\DatabaseService;

/**
 * Handles rendering of draft-related error messages
 * 
 * Responsibilities:
 * - Render validation error messages
 * - Format user-facing error displays
 */
class DraftView
{
    /**
     * Render a validation error message
     * 
     * @param string $errorMessage The error message to display
     * @return string HTML formatted error message
     */
    public function renderValidationError($errorMessage)
    {
        $errorMessage = DatabaseService::safeHtmlOutput($errorMessage);
        $retryInstructions = $this->getRetryInstructions($errorMessage);
        
        return "Oops, $errorMessage<p>
        <a href=\"/ibl5/modules.php?name=College_Scouting\">Click here to return to the Draft module</a>" 
        . $retryInstructions;
    }

    /**
     * Get the appropriate retry instructions based on the error message
     * 
     * @param string $errorMessage The error message
     * @return string The retry instructions to append
     */
    private function getRetryInstructions($errorMessage)
    {
        if (strpos($errorMessage, "didn't select") !== false) {
            return " and please select a player before hitting the Draft button.";
        }
        
        return " and if it's your turn, try drafting again.";
    }
}
