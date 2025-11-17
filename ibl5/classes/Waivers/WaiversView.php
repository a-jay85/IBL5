<?php

namespace Waivers;

/**
 * Renders waiver wire UI components
 */
class WaiversView
{
    /**
     * Renders the waiver wire form
     * 
     * @param string $teamName Team name
     * @param int $teamID Team ID
     * @param string $action Action (add or drop)
     * @param array $players Array of players to display in dropdown
     * @param int $openRosterSpots Number of open roster spots
     * @param int $healthyOpenRosterSpots Number of healthy open roster spots
     * @param string $errorMessage Error message to display (if any)
     */
    public function renderWaiverForm(
        string $teamName,
        int $teamID,
        string $action,
        array $players,
        int $openRosterSpots,
        int $healthyOpenRosterSpots,
        string $errorMessage = ''
    ): void {
        $teamNameEscaped = htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8');
        $actionEscaped = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');

        ob_start();
        ?>
        <?php if ($errorMessage): ?>
            <center>
            <font color="red"><b><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></b></font>
            </center>
        <?php endif; ?>
        <form name="Waiver_Move" method="post" action="">
            <input type="hidden" name="Team_Name" value="<?= $teamNameEscaped ?>">
            <center>
            <img src="images/logo/<?= $teamID ?>.jpg"><br>
            <table border="1" cellspacing="0" cellpadding="0">
                <tr>
                <th colspan="3">
                    <center>
                    WAIVER WIRE - YOUR TEAM CURRENTLY HAS <?= $openRosterSpots ?> EMPTY ROSTER SPOTS and <?= $healthyOpenRosterSpots ?> HEALTHY ROSTER SPOTS
                    </center>
                </th>
                </tr>
                <tr>
                <td valign="top">
                    <center>
                    <b><u><?= $teamNameEscaped ?></u></b>
                    <select name="Player_ID">
                        <option value="">Select player...</option>
                        <?php foreach ($players as $optionHtml): ?>
                        <?= $optionHtml ?>
                        <?php endforeach; ?>
                    </select>
                    </center>
                </td>
                </tr>
                <input type="hidden" name="Action" value="<?= $actionEscaped ?>">
                <input type="hidden" name="rosterslots" value="<?= $openRosterSpots ?>">
                <input type="hidden" name="healthyrosterslots" value="<?= $healthyOpenRosterSpots ?>">
                <tr>
                <td colspan="3">
                    <center>
                    <input type="submit" value="Click to <?= $actionEscaped ?> player(s) to/from Waiver Pool" onclick="this.disabled=true;this.value='Submitting...'; this.form.submit();">
                    </center>
                </td>
                </tr>
            </table>
            </center>
        </form>
        <?php
        echo ob_get_clean();
    }
    
    /**
     * Builds player option HTML for dropdown
     * 
     * @param int $playerID Player ID
     * @param string $playerName Player name
     * @param string $contract Contract display
     * @param string $waitTime Wait time display (for waiver claims)
     * @return string HTML option element
     */
    public function buildPlayerOption(
        int $playerID,
        string $playerName,
        string $contract,
        string $waitTime = ''
    ): string {
        $playerNameEscaped = htmlspecialchars($playerName, ENT_QUOTES, 'UTF-8');
        $contractEscaped = htmlspecialchars($contract, ENT_QUOTES, 'UTF-8');
        $waitTimeEscaped = htmlspecialchars($waitTime, ENT_QUOTES, 'UTF-8');
        
        $displayText = "$playerNameEscaped $contractEscaped";
        if ($waitTime) {
            $displayText .= " $waitTimeEscaped";
        }
        
        return "<option value=\"$playerID\">$displayText</option>";
    }
    
    /**
     * Renders the not logged in message
     * 
     * @param string $message Message to display
     */
    public function renderNotLoggedIn(string $message): void
    {
        \Nuke\Header::header();
        OpenTable();
        \UI::displaytopmenu($GLOBALS['db'], 0);
        echo "<center><font class=\"title\"><b>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</b></font></center>";
        loginbox();
        CloseTable();
        \Nuke\Footer::footer();
    }
    
    /**
     * Renders the waivers closed message
     */
    public function renderWaiversClosed(): void
    {
        \Nuke\Header::header();
        OpenTable();
        \UI::displaytopmenu($GLOBALS['db'], 0);
        echo "Sorry, but players may not be added from or dropped to waivers at the present time.";
        CloseTable();
        \Nuke\Footer::footer();
    }
}
