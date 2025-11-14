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
        $teamID = (int) $teamID;
        
        ob_start();
        ?>
<html><head><title>Rookie Option Page</title></head><body>

Your rookie option has been updated in the database and should reflect on your team pages immediately.<br>
        <?php if ($phase == "Free Agency"): ?>
Please <a href="/ibl5/modules.php?name=Free_Agency">click here to return to the Free Agency Screen</a>.
        <?php else: ?>
Please <a href="/ibl5/modules.php?name=Team&op=team&teamID=<?= htmlspecialchars((string)$teamID) ?>">click here to return to your team page</a>.
        <?php endif; ?>

        <?php if ($emailSuccess): ?>
<div style="text-align: center;">An e-mail regarding this extension has been successfully sent to the commissioner's office. Thank you.</div>
        <?php else: ?>
<div style="text-align: center;">Message failed to e-mail properly; please notify the commissioner of the error.</div>
        <?php endif; ?>
        <?php
        echo ob_get_clean();
    }
}
