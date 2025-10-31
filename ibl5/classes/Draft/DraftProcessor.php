<?php

namespace Draft;

/**
 * Handles business logic for draft operations
 * 
 * Responsibilities:
 * - Format draft announcement messages
 * - Build Discord notifications
 * - Process draft selection information
 */
class DraftProcessor
{
    /**
     * Create a draft announcement message
     * 
     * @param int $draftPick The overall pick number
     * @param int $draftRound The round number
     * @param string $seasonYear The draft year
     * @param string $teamName The drafting team
     * @param string $playerName The drafted player
     * @return string The formatted announcement message
     */
    public function createDraftAnnouncement($draftPick, $draftRound, $seasonYear, $teamName, $playerName)
    {
        return "With pick #$draftPick in round $draftRound of the $seasonYear IBL Draft, the **" 
            . $teamName . "** select **" . $playerName . "!**";
    }

    /**
     * Create the message for the next team on the clock
     * 
     * @param string $baseMessage The base draft announcement
     * @param string|null $discordID The Discord ID of the next team's owner (null if draft is complete)
     * @param string|null $seasonYear The draft year (null if draft is complete)
     * @return string The complete message with next team or draft completion notice
     */
    public function createNextTeamMessage($baseMessage, $discordID, $seasonYear)
    {
        if ($discordID !== null) {
            return $baseMessage . '
    **<@!' . $discordID . '>** is on the clock!
https://www.iblhoops.net/ibl5/modules.php?name=College_Scouting';
        } else {
            return $baseMessage . "
    **🏁 __The $seasonYear IBL Draft has officially concluded!__ 🏁**";
        }
    }

    /**
     * Get the success message HTML for display
     * 
     * @param string $message The draft announcement message
     * @return string HTML formatted success message
     */
    public function getSuccessMessage($message)
    {
        return "$message<p>
        <a href=\"/ibl5/modules.php?name=College_Scouting\">Go back to the Draft module</a>";
    }

    /**
     * Get the error message HTML for failed database update
     * 
     * @return string HTML formatted error message
     */
    public function getDatabaseErrorMessage()
    {
        return "Oops, something went wrong, and at least one of the draft database tables wasn't updated.<p>
            Let A-Jay know what happened and he'll look into it.<p>
            
            <a href=\"/ibl5/modules.php?name=College_Scouting\">Go back to the Draft module</a>";
    }
}
