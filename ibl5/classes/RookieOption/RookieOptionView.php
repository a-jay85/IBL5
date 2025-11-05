<?php

namespace RookieOption;

/**
 * Handles rendering for rookie option operations
 */
class RookieOptionView
{
    /**
     * Renders the success page after processing rookie option
     * 
     * @param string $teamName Team name
     * @param int $teamID Team ID
     * @param string $phase Current season phase
     * @param bool $emailSuccess Whether email was sent successfully
     */
    public function renderSuccessPage(string $teamName, int $teamID, string $phase, bool $emailSuccess): void
    {
        $teamNameSafe = htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8');
        $teamID = (int) $teamID;
        
        echo "<html><head><title>Rookie Option Page</title></head><body>\n\n";
        echo "Your rookie option has been updated in the database and should reflect on your team pages immediately.<br>";
        
        if ($phase == "Free Agency") {
            echo "Please <a href=\"/ibl5/modules.php?name=Free_Agency\">click here to return to the Free Agency Screen</a>.";
        } else {
            echo "Please <a href=\"/ibl5/modules.php?name=Team&op=team&teamID=$teamID\">click here to return to your team page</a>.";
        }
        
        if ($emailSuccess) {
            echo "<center>An e-mail regarding this extension has been successfully sent to the commissioner's office. Thank you.</center>";
        } else {
            echo "Message failed to e-mail properly; please notify the commissioner of the error.</center>";
        }
    }
}
