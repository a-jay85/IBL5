<?php

declare(strict_types=1);

namespace Negotiation;

use Negotiation\Contracts\NegotiationViewHelperInterface;
use Player\Player;
use Services\DatabaseService;

/**
 * @see NegotiationViewHelperInterface
 */
class NegotiationViewHelper implements NegotiationViewHelperInterface
{
    /**
     * @see NegotiationViewHelperInterface::renderNegotiationForm()
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
            ? (int) round($maxYearOneSalary * 0.125) 
            : (int) round($maxYearOneSalary * 0.1);
        
        ob_start();
        ?>
<form name="ExtensionOffer" method="post" action="modules/Player/extension.php">
    <p>Note that if you offer the max and I refuse, it means I am opting for Free Agency at the end of the season:</p>
    <table cellspacing="0" border="1">
        <tr>
            <td>My demands are:</td>
            <?= $demandDisplay ?>
        </tr>
        <tr>
            <td>Please enter your offer in this row:</td>
            <?php if (!$demandsExceedMax): ?>
                <?= self::renderEditableOfferFields($demands) ?>
            <?php else: ?>
                <?= self::renderMaxSalaryFields($maxYearOneSalary, $maxRaise, $demands) ?>
            <?php endif; ?>
        </tr>
        <tr>
            <td colspan="6">
                <b>Notes/Reminders:</b>
                <ul>
                    <li>You have <?= $capSpace ?> in cap space available; the amount you offer in year 1 cannot exceed this.</li>
                    <li>Based on my years of service, the maximum amount you can offer me in year 1 is <?= $maxYearOneSalary ?>.</li>
                    <li>Enter "0" for years you do not want to offer a contract.</li>
                    <li>Contract extensions must be at least three years in length.</li>
                    <li>The amounts offered each year must equal or exceed the previous year.</li>
                    <?php if ($birdYears >= 3): ?>
                        <li>Because this player has Bird Rights, you may add no more than 12.5% of the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 75 between any two subsequent years.)</li>
                    <?php else: ?>
                        <li>Because this player does not have Bird Rights, you may add no more than 10% of the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 50 between any two subsequent years.)</li>
                    <?php endif; ?>
                    <li>When re-signing your own players, you can go over the soft cap and up to the hard cap (<?= \League::HARD_CAP_MAX ?>).</li>
                </ul>
            </td>
        </tr>
    </table>
    <input type="hidden" name="maxyr1" value="<?= $maxYearOneSalary ?>">
    <input type="hidden" name="demandsTotal" value="<?= $demands['total'] ?>">
    <input type="hidden" name="demandsYears" value="<?= $demands['years'] ?>">
    <input type="hidden" name="teamName" value="<?= $teamName ?>">
    <input type="hidden" name="playerName" value="<?= $playerName ?>">
    <input type="hidden" name="playerID" value="<?= $playerID ?>">
    <input type="submit" value="Offer Extension!">
</form>
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
        $display = "<td>" . (string)$demands['year1'] . "</td>";
        
        if ($demands['year2'] != 0) {
            $display .= "<td>" . $demands['year2'] . "</td>";
        }
        if ($demands['year3'] != 0) {
            $display .= "<td>" . $demands['year3'] . "</td>";
        }
        if ($demands['year4'] != 0) {
            $display .= "<td>" . $demands['year4'] . "</td>";
        }
        if ($demands['year5'] != 0) {
            $display .= "<td>" . $demands['year5'] . "</td>";
        }
        if ($demands['year6'] != 0) {
            $display .= "<td>" . $demands['year6'] . "</td>";
        }
        
        return $display;
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
<td><input type="number" style="width: 4em" name="offerYear1" size="4" value="<?= $demands['year1'] ?>"></td>
<td><input type="number" style="width: 4em" name="offerYear2" size="4" value="<?= $demands['year2'] ?>"></td>
<td><input type="number" style="width: 4em" name="offerYear3" size="4" value="<?= $demands['year3'] ?>"></td>
<td><input type="number" style="width: 4em" name="offerYear4" size="4" value="<?= $demands['year4'] ?>"></td>
<td><input type="number" style="width: 4em" name="offerYear5" size="4" value="<?= $demands['year5'] ?>"></td>
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
    private static function renderMaxSalaryFields(int $maxYear1, int $maxRaise, array $demands): string
    {
        $maxYear2 = ($demands['year2'] != 0) ? $maxYear1 + $maxRaise : 0;
        $maxYear3 = ($demands['year3'] != 0) ? $maxYear2 + $maxRaise : 0;
        $maxYear4 = ($demands['year4'] != 0) ? $maxYear3 + $maxRaise : 0;
        $maxYear5 = ($demands['year5'] != 0) ? $maxYear4 + $maxRaise : 0;
        
        ob_start();
        ?>
<td><input type="text" name="offerYear1" size="4" value="<?= $maxYear1 ?>"></td>
<td><input type="text" name="offerYear2" size="4" value="<?= $maxYear2 ?>"></td>
<td><input type="text" name="offerYear3" size="4" value="<?= $maxYear3 ?>"></td>
<td><input type="text" name="offerYear4" size="4" value="<?= $maxYear4 ?>"></td>
<td><input type="text" name="offerYear5" size="4" value="<?= $maxYear5 ?>"></td>
        <?php
        return ob_get_clean();
    }
    
    /**
     * @see NegotiationViewHelperInterface::renderError()
     */
    public static function renderError(string $error): string
    {
        return "<p>" . DatabaseService::safeHtmlOutput($error) . "</p>";
    }

    /**
     * @see NegotiationViewHelperInterface::renderHeader()
     */
    public static function renderHeader(Player $player): string
    {
        $playerPos = DatabaseService::safeHtmlOutput($player->position);
        $playerName = DatabaseService::safeHtmlOutput($player->name);
        
        return "<b>$playerPos $playerName</b> - Contract Demands:<br>";
    }
}
