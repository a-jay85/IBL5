<?php

namespace RookieOption;

/**
 * Handles rendering of the rookie option form
 */
class RookieOptionFormView
{
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
        $playerPosition = htmlspecialchars($player->position, ENT_QUOTES, 'UTF-8');
        $playerName = htmlspecialchars($player->name, ENT_QUOTES, 'UTF-8');
        $teamNameEscaped = htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8');
        $rookieOptionValueEscaped = htmlspecialchars((string) $rookieOptionValue, ENT_QUOTES, 'UTF-8');
        
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
    
    /**
     * Renders an error message when player is not on user's team
     * 
     * @param object $player Player object with properties: position, name
     */
    public function renderNotOnTeamError($player): void
    {
        $playerPosition = htmlspecialchars($player->position, ENT_QUOTES, 'UTF-8');
        $playerName = htmlspecialchars($player->name, ENT_QUOTES, 'UTF-8');
        
        echo "{$playerPosition} {$playerName} is not on your team.<br>
            <a href=\"javascript:history.back()\">Go Back</a>";
    }
    
    /**
     * Renders an error message when player is not eligible for rookie option
     * 
     * @param object $player Player object with properties: position, name
     */
    public function renderNotEligibleError($player): void
    {
        $playerPosition = htmlspecialchars($player->position, ENT_QUOTES, 'UTF-8');
        $playerName = htmlspecialchars($player->name, ENT_QUOTES, 'UTF-8');
        
        echo "Sorry, {$playerPosition} {$playerName} is not eligible for a rookie option.<p>
            Only draft picks are eligible for rookie options, and the option must be exercised
            before the final season of their rookie contract is underway.<p>
    		<a href=\"javascript:history.back()\">Go Back</a>";
    }
}
