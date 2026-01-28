<?php

declare(strict_types=1);

namespace Waivers;

use Waivers\Contracts\WaiversViewInterface;

/**
 * @see WaiversViewInterface
 */
class WaiversView implements WaiversViewInterface
{
    /**
     * @see WaiversViewInterface::renderWaiverForm()
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
            <div class="waivers-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form name="Waiver_Move" method="post" action="" class="waivers-form">
            <input type="hidden" name="Team_Name" value="<?= $teamNameEscaped ?>">
            <div class="text-center">
                <img src="images/logo/<?= $teamID ?>.jpg" alt="Team Logo" width="415" height="50" style="max-width: 100%; height: auto; margin-bottom: 1rem;"><br>
                <div class="waivers-form-card">
                    <div class="waivers-form-header">
                        WAIVER WIRE - <?= $openRosterSpots ?> EMPTY ROSTER SPOTS / <?= $healthyOpenRosterSpots ?> HEALTHY ROSTER SPOTS
                    </div>
                    <div class="waivers-form-body">
                        <div class="waivers-team-title"><?= $teamNameEscaped ?></div>
                        <select name="Player_ID" class="waivers-select">
                            <option value="">Select player...</option>
                            <?php foreach ($players as $optionHtml): ?>
                            <?= $optionHtml ?>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="Action" value="<?= $actionEscaped ?>">
                        <input type="hidden" name="rosterslots" value="<?= $openRosterSpots ?>">
                        <input type="hidden" name="healthyrosterslots" value="<?= $healthyOpenRosterSpots ?>">
                        <button type="submit" class="waivers-submit-btn" onclick="this.disabled=true;this.textContent='Submitting...'; this.form.submit();">
                            Click to <?= $actionEscaped ?> player(s) to/from Waiver Pool
                        </button>
                    </div>
                </div>
            </div>
        </form>
        <?php
        echo ob_get_clean();
    }
    
    /**
     * @see WaiversViewInterface::buildPlayerOption()
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
     * @see WaiversViewInterface::renderNotLoggedIn()
     */
    public function renderNotLoggedIn(string $message): void
    {
        \Nuke\Header::header();
        \UI::displaytopmenu($GLOBALS['db'], 0);
        echo '<div class="text-center"><strong class="ibl-title">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</strong></div>';
        loginbox();
        \Nuke\Footer::footer();
    }
    
    /**
     * @see WaiversViewInterface::renderWaiversClosed()
     */
    public function renderWaiversClosed(): void
    {
        \Nuke\Header::header();
        \UI::displaytopmenu($GLOBALS['db'], 0);
        echo "Sorry, but players may not be added from or dropped to waivers at the present time.";
        \Nuke\Footer::footer();
    }
}
