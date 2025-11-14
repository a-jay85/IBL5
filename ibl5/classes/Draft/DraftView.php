<?php

namespace Draft;

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
        $retryInstructions = $this->getRetryInstructions($errorMessage);
        
        ob_start();
        ?>
Oops, <?= $errorMessage ?><p>
<a href="/ibl5/modules.php?name=Draft">Click here to return to the Draft module</a><?= $retryInstructions ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the draft interface with player list
     * 
     * @param array $players Array of player records
     * @param string $teamLogo The current user's team
     * @param string $pickOwner The team that owns the current pick
     * @param int $draftRound The current draft round
     * @param int $draftPick The current draft pick number
     * @param int $seasonYear The draft season year
     * @param int $tid The team ID for logo display
     * @return string HTML formatted draft interface
     */
    public function renderDraftInterface($players, $teamLogo, $pickOwner, $draftRound, $draftPick, $seasonYear, $tid)
    {
        ob_start();
        ?>
<div style="text-align: center;"><img src="images/logo/<?= $tid ?>.jpg"><br>
<table>
    <tr>
        <th colspan="27">
            <div style="text-align: center;">Welcome to the <?= htmlspecialchars((string)$seasonYear) ?> IBL Draft!</div>
        </th>
    </tr>
</table>

<form name='draft_form' action='/ibl5/modules/Draft/draft_selection.php' method='POST'>
<input type='hidden' name='teamname' value='<?= DatabaseService::safeHtmlOutput($teamLogo) ?>'>
<input type='hidden' name='draft_round' value='<?= htmlspecialchars((string)$draftRound) ?>'>
<input type='hidden' name='draft_pick' value='<?= htmlspecialchars((string)$draftPick) ?>'>

<?= $this->renderPlayerTable($players, $teamLogo, $pickOwner) ?>

        <?php if ($teamLogo == $pickOwner && $this->hasUndraftedPlayers($players)): ?>
<div style="text-align: center;"><input type='submit' style="height:100px; width:150px" value='Draft' onclick="this.disabled=true;this.value='Submitting...'; this.form.submit();"></div>
        <?php endif; ?>

</form>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the player table for draft selection
     * 
     * @param array $players Array of player records
     * @param string $teamLogo The current user's team
     * @param string $pickOwner The team that owns the current pick
     * @return string HTML formatted player table
     */
    private function renderPlayerTable($players, $teamLogo, $pickOwner)
    {
        ob_start();
        ?>
<table class="sortable">
    <tr>
        <th>Draft</th>
        <th>Name</th>
        <th>Pos</th>
        <th>Team</th>
        <th>Age</th>
        <th>fga</th>
        <th>fgp</th>
        <th>fta</th>
        <th>ftp</th>
        <th>tga</th>
        <th>tgp</th>
        <th>orb</th>
        <th>drb</th>
        <th>ast</th>
        <th>stl</th>
        <th>to</th>
        <th>blk</th>
        <th>oo</th>
        <th>do</th>
        <th>po</th>
        <th>to</th>
        <th>od</th>
        <th>dd</th>
        <th>pd</th>
        <th>td</th>
        <th>Tal</th>
        <th>Skl</th>
        <th>Int</th>
    </tr>
        <?php
        $i = 0;
        foreach ($players as $player) {
            (($i % 2) == 0) ? $bgcolor = "EEEEEE" : $bgcolor = "DDDDDD";
            $i++;

            $isPlayerDrafted = $player['drafted'];
            $playerName = DatabaseService::safeHtmlOutput($player['name']);
            ?>
            <?php if ($teamLogo == $pickOwner && $isPlayerDrafted == 0): ?>
<tr style="background-color: #<?= $bgcolor ?>">
    <td style="text-align: center;"><input type='radio' name='player' value="<?= htmlspecialchars($player['name'], ENT_QUOTES) ?>"></td>
    <td style="white-space: nowrap;"><?= $playerName ?></td>
            <?php elseif ($isPlayerDrafted == 1): ?>
<tr>
    <td></td>
    <td style="white-space: nowrap;"><span style="text-decoration: line-through;"><i><?= $playerName ?></i></span></td>
            <?php else: ?>
<tr style="background-color: #<?= $bgcolor ?>">
    <td></td>
    <td style="white-space: nowrap;"><?= $playerName ?></td>
            <?php endif; ?>
    <td><?= DatabaseService::safeHtmlOutput($player['pos']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['team']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['age']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['fga']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['fgp']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['fta']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['ftp']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['tga']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['tgp']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['orb']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['drb']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['ast']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['stl']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['tvr']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['blk']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['offo']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['offd']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['offp']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['offt']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['defo']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['defd']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['defp']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['deft']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['tal']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['skl']) ?></td>
    <td><?= DatabaseService::safeHtmlOutput($player['int']) ?></td>
</tr>
            <?php
        endforeach;
        ?>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Get the appropriate retry instructions based on the error message
     * 
     * @param string $errorMessage The error message
     * @return string The retry instructions to append
     */
    private function getRetryInstructions($errorMessage)
    {
        if (strpos($errorMessage, "didn't select") !== false) {
            return " and please select a player before hitting the Draft button.";
        }
        
        return " and if it's your turn, try drafting again.";
    }

    /**
     * Check if there are any undrafted players available
     * 
     * @param array $players Array of player records
     * @return bool True if there is at least one undrafted player
     */
    private function hasUndraftedPlayers($players)
    {
        foreach ($players as $player) {
            if ($player['drafted'] == 0) {
                return true;
            }
        }
        return false;
    }
}
