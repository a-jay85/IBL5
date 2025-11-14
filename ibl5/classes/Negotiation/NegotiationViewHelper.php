<?php

namespace Negotiation;

use Player\Player;
use Services\DatabaseService;

/**
 * Negotiation View Helper
 * 
 * Handles HTML rendering for the negotiation page.
 * Separates presentation logic from business logic.
 */
class NegotiationViewHelper
{
    /**
     * Render the negotiation form
     * 
     * @param Player $player The player object
     * @param array $demands Calculated demands
     * @param int $capSpace Available cap space
     * @param int $maxYearOneSalary Maximum first year salary based on experience
     * @return string HTML output
     */
    public static function renderNegotiationForm(
        Player $player,
        array $demands,
        int $capSpace,
        int $maxYearOneSalary
    ): string {
        $playerName = DatabaseService::safeHtmlOutput($player->name);
        $playerID = (int)$player->playerID;
        $teamName = DatabaseService::safeHtmlOutput($player->teamName);
        
        // Build demand display
        $demandDisplay = self::buildDemandDisplay($demands);
        
        // Check if player demands exceed max
        $demandsExceedMax = $demands['year1'] >= $maxYearOneSalary;
        
        // Calculate max raises
        $birdYears = $player->birdYears ?? 0;
        $maxRaise = $birdYears >= 3 
            ? round($maxYearOneSalary * 0.125) 
            : round($maxYearOneSalary * 0.1);
        
        ob_start();
        ?>
<form name="ExtensionOffer" method="post" action="modules/Player/extension.php">
<p>Note that if you offer the max and I refuse, it means I am opting for Free Agency at the end of the season:</p>
<table style="border-spacing: 0; border: 1px solid black;">
<tr><td>My demands are:</td><td><?= $demandDisplay ?></td></tr>
<tr><td>Please enter your offer in this row:</td><td>
        <?php if (!$demandsExceedMax): ?>
<?= self::renderEditableOfferFields($demands) ?>
        <?php else: ?>
<?= self::renderMaxSalaryFields($maxYearOneSalary, $maxRaise, $demands) ?>
        <?php endif; ?>
</tr>
<tr><td colspan="6"><b>Notes/Reminders:</b><ul>
<li>You have <?= htmlspecialchars((string)$capSpace) ?> in cap space available; the amount you offer in year 1 cannot exceed this.</li>
<li>Based on my years of service, the maximum amount you can offer me in year 1 is <?= htmlspecialchars((string)$maxYearOneSalary) ?>.</li>
<li>Enter "0" for years you do not want to offer a contract.</li>
<li>Contract extensions must be at least three years in length.</li>
<li>The amounts offered each year must equal or exceed the previous year.</li>
        <?php if ($birdYears >= 3): ?>
<li>Because this player has Bird Rights, you may add no more than 12.5% of the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 75 between any two subsequent years.)</li>
        <?php else: ?>
<li>Because this player does not have Bird Rights, you may add no more than 10% of the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 50 between any two subsequent years.)</li>
        <?php endif; ?>
<li>When re-signing your own players, you can go over the soft cap and up to the hard cap (<?= \League::HARD_CAP_MAX ?>).</li>
</ul></td></tr>
<input type="hidden" name="maxyr1" value="<?= htmlspecialchars((string)$maxYearOneSalary) ?>">
<input type="hidden" name="demandsTotal" value="<?= htmlspecialchars((string)$demands['total']) ?>">
<input type="hidden" name="demandsYears" value="<?= htmlspecialchars((string)$demands['years']) ?>">
<input type="hidden" name="teamName" value="<?= $teamName ?>">
<input type="hidden" name="playerName" value="<?= $playerName ?>">
<input type="hidden" name="playerID" value="<?= $playerID ?>">
</table>
<input type="submit" value="Offer Extension!"></form>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Build demand display string
     * 
     * @param array $demands Demand amounts
     * @return string HTML formatted demand display
     */
    private static function buildDemandDisplay(array $demands): string
    {
        ob_start();
        echo htmlspecialchars((string)$demands['year1']);
        
        if ($demands['year2'] != 0) {
            echo "</td><td>" . htmlspecialchars((string)$demands['year2']);
        }
        if ($demands['year3'] != 0) {
            echo "</td><td>" . htmlspecialchars((string)$demands['year3']);
        }
        if ($demands['year4'] != 0) {
            echo "</td><td>" . htmlspecialchars((string)$demands['year4']);
        }
        if ($demands['year5'] != 0) {
            echo "</td><td>" . htmlspecialchars((string)$demands['year5']);
        }
        if ($demands['year6'] != 0) {
            echo "</td><td>" . htmlspecialchars((string)$demands['year6']);
        }
        
        return ob_get_clean();
    }
    
    /**
     * Render editable offer fields with demand defaults
     * 
     * @param array $demands Demand amounts
     * @return string HTML for input fields
     */
    private static function renderEditableOfferFields(array $demands): string
    {
        ob_start();
        ?>
<input type="number" style="width: 4em" name="offerYear1" size="4" value="<?= htmlspecialchars((string)$demands['year1']) ?>"></td>
<td><input type="number" style="width: 4em" name="offerYear2" size="4" value="<?= htmlspecialchars((string)$demands['year2']) ?>"></td>
<td><input type="number" style="width: 4em" name="offerYear3" size="4" value="<?= htmlspecialchars((string)$demands['year3']) ?>"></td>
<td><input type="number" style="width: 4em" name="offerYear4" size="4" value="<?= htmlspecialchars((string)$demands['year4']) ?>"></td>
<td><input type="number" style="width: 4em" name="offerYear5" size="4" value="<?= htmlspecialchars((string)$demands['year5']) ?>"></td>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render max salary fields
     * 
     * @param int $maxYearOne Maximum first year salary
     * @param int $maxRaise Maximum raise per year
     * @param array $demands Demand amounts (to determine which years to show)
     * @return string HTML for input fields
     */
    private static function renderMaxSalaryFields(int $maxYearOne, int $maxRaise, array $demands): string
    {
        $maxYr2 = ($demands['year2'] != 0) ? $maxYearOne + $maxRaise : 0;
        $maxYr3 = ($demands['year3'] != 0) ? $maxYr2 + $maxRaise : 0;
        $maxYr4 = ($demands['year4'] != 0) ? $maxYr3 + $maxRaise : 0;
        $maxYr5 = ($demands['year5'] != 0) ? $maxYr4 + $maxRaise : 0;
        
        ob_start();
        ?>
<input type="text" name="offerYear1" size="4" value="<?= htmlspecialchars((string)$maxYearOne) ?>"></td>
<td><input type="text" name="offerYear2" size="4" value="<?= htmlspecialchars((string)$maxYr2) ?>"></td>
<td><input type="text" name="offerYear3" size="4" value="<?= htmlspecialchars((string)$maxYr3) ?>"></td>
<td><input type="text" name="offerYear4" size="4" value="<?= htmlspecialchars((string)$maxYr4) ?>"></td>
<td><input type="text" name="offerYear5" size="4" value="<?= htmlspecialchars((string)$maxYr5) ?>"></td>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render error message
     * 
     * @param string $error Error message to display
     * @return string HTML output
     */
    public static function renderError(string $error): string
    {
        ob_start();
        ?>
<p><?= DatabaseService::safeHtmlOutput($error) ?></p>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render page header
     * 
     * @param Player $player The player object
     * @return string HTML output
     */
    public static function renderHeader(Player $player): string
    {
        $playerName = DatabaseService::safeHtmlOutput($player->name);
        $playerPos = DatabaseService::safeHtmlOutput($player->position);
        
        ob_start();
        ?>
<b><?= $playerPos ?> <?= $playerName ?></b> - Contract Demands:<br>
        <?php
        return ob_get_clean();
    }
}
