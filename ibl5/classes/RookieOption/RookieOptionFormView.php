<?php

namespace RookieOption;

use Services\DatabaseService;

/**
 * Handles rendering of the rookie option form
 */
class RookieOptionFormView
{
    /**
     * Renders a generic error message with proper HTML escaping
     * 
     * @param string $errorMessage Error message to display
     */
    public function renderError(string $errorMessage): void
    {
        $errorMessageEscaped = DatabaseService::safeHtmlOutput($errorMessage);
        
        echo "{$errorMessageEscaped}<br>
            <a href=\"javascript:history.back()\">Go Back</a>";
    }
    
    /**
     * Renders the rookie option form for a player
     * 
     * @param object $player Player object with properties: playerID, position, name
     * @param string $teamName User's team name
     * @param int $rookieOptionValue Calculated rookie option value
     */
    public function renderForm($player, string $teamName, int $rookieOptionValue): void
    {
        // Escape all output for security
        $playerID = (int) $player->playerID;
        $playerPosition = DatabaseService::safeHtmlOutput($player->position);
        $playerName = DatabaseService::safeHtmlOutput($player->name);
        $teamNameEscaped = DatabaseService::safeHtmlOutput($teamName);
        $rookieOptionValueEscaped = DatabaseService::safeHtmlOutput((string) $rookieOptionValue);
        
        echo "<img align=left src=\"images/player/{$playerID}.jpg\">
        You may exercise the rookie extension option on <b>{$playerPosition} {$playerName}</b>.<br>
        Their contract value the season after this one will be <b>{$rookieOptionValueEscaped}</b>.<br>
        However, by exercising this option, <b>you can't use an in-season contract extension on them next season</b>.<br>
        <b>They will become a free agent</b>.<br>
        <form name=\"RookieExtend\" method=\"post\" action=\"/ibl5/modules/Player/rookieoption.php\">
            <input type=\"hidden\" name=\"teamname\" value=\"{$teamNameEscaped}\">
            <input type=\"hidden\" name=\"playerID\" value=\"{$playerID}\">
            <input type=\"hidden\" name=\"rookieOptionValue\" value=\"{$rookieOptionValueEscaped}\">
            <input type=\"submit\" value=\"Activate Rookie Extension\">
        </form>";
    }
}
