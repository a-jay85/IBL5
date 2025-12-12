<?php

declare(strict_types=1);

namespace RookieOption;

use RookieOption\Contracts\RookieOptionViewInterface;

/**
 * @see RookieOptionViewInterface
 */
class RookieOptionView implements RookieOptionViewInterface
{
    /**
     * @see RookieOptionViewInterface::renderSuccessPage()
     */
    public function renderSuccessPage(string $teamName, int $teamID, string $phase, bool $emailSuccess): void
    {
        $teamID = (int) $teamID;
        $teamNameEscaped = htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8');
        
        echo "<html><head><title>Rookie Option Page</title></head><body>\n\n";
        echo "Your rookie option has been updated in the database and should reflect on your team pages immediately.<br>";
        
        if ($phase == "Free Agency") {
            echo "Please <a href=\"/ibl5/modules.php?name=Free_Agency\">click here to return to the Free Agency Screen</a>.";
        } else {
            echo "Please <a href=\"/ibl5/modules.php?name=Team&op=team&teamID=" . $teamID . "\">click here to return to your team page</a>.";
        }
        
        if ($emailSuccess) {
            echo "<center>An e-mail regarding this extension has been successfully sent to the commissioner's office. Thank you.</center>";
        } else {
            echo "<center>Message failed to e-mail properly; please notify the commissioner of the error.</center>";
        }
    }
}
