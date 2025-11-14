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
        $errorMessageEscaped = nl2br(DatabaseService::safeHtmlOutput($errorMessage));
        
        ob_start();
        ?>
<?= $errorMessageEscaped ?><p>
<a href="javascript:history.back()">Go Back</a>
        <?php
        echo ob_get_clean();
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
        
        ob_start();
        ?>
<img style="float: left;" src="images/player/<?= $playerID ?>.jpg"><p>
You may exercise the rookie option on <b><?= $playerPosition ?> <?= $playerName ?></b>.<p>
Their contract value the season after this one will be <b><?= $rookieOptionValueEscaped ?></b>.<p>
WARNING: By exercising this option, <b>you can't use an in-season contract extension on them next season</b>.<p>
<b>They will become a free agent</b>.<p>
<form name="RookieExtend" method="post" action="/ibl5/modules/Player/rookieoption.php">
    <input type="hidden" name="teamname" value="<?= $teamNameEscaped ?>">
    <input type="hidden" name="playerID" value="<?= $playerID ?>">
    <input type="hidden" name="rookieOptionValue" value="<?= $rookieOptionValueEscaped ?>">
    <input type="submit" value="Exercise Rookie Option">
</form>
        <?php
        echo ob_get_clean();
    }
}
