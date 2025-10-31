<?php

namespace Draft;

require_once __DIR__ . '/../Services/DatabaseService.php';

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
        
        return "Oops, $errorMessage<p>
        <a href=\"/ibl5/modules.php?name=College_Scouting\">Click here to return to the Draft module</a>" 
        . ($this->shouldShowRetryInstructions($errorMessage) ? " and please select a player before hitting the Draft button." : " and if it's your turn, try drafting again.");
    }

    /**
     * Determine if retry instructions should be shown
     * 
     * @param string $errorMessage The error message
     * @return bool True if retry instructions should be shown
     */
    private function shouldShowRetryInstructions($errorMessage)
    {
        return strpos($errorMessage, "didn't select") !== false;
    }
}
