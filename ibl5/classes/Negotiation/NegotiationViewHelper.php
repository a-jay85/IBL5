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
        $playerPos = DatabaseService::safeHtmlOutput($player->position);
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
        
        $output = "<form name=\"ExtensionOffer\" method=\"post\" action=\"modules/Player/extension.php\">";
        $output .= "<p>Note that if you offer the max and I refuse, it means I am opting for Free Agency at the end of the season:</p>";
        $output .= "<table cellspacing=0 border=1>";
        $output .= "<tr><td>My demands are:</td><td>$demandDisplay</td></tr>";
        $output .= "<tr><td>Please enter your offer in this row:</td><td>";
        
        if (!$demandsExceedMax) {
            // Player demands are below max - show editable fields with demands as defaults
            $output .= self::renderEditableOfferFields($demands);
        } else {
            // Player demands exceed max - show max salary fields
            $output .= self::renderMaxSalaryFields($maxYearOneSalary, $maxRaise, $demands);
        }
        
        $output .= "</tr>";
        $output .= "<tr><td colspan=6><b>Notes/Reminders:</b><ul>";
        $output .= "<li>You have $capSpace in cap space available; the amount you offer in year 1 cannot exceed this.</li>";
        $output .= "<li>Based on my years of service, the maximum amount you can offer me in year 1 is $maxYearOneSalary.</li>";
        $output .= "<li>Enter \"0\" for years you do not want to offer a contract.</li>";
        $output .= "<li>Contract extensions must be at least three years in length.</li>";
        $output .= "<li>The amounts offered each year must equal or exceed the previous year.</li>";
        
        if ($birdYears >= 3) {
            $raisePercent = '12.5%';
            $output .= "<li>Because this player has Bird Rights, you may add no more than $raisePercent of the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 75 between any two subsequent years.)</li>";
        } else {
            $raisePercent = '10%';
            $output .= "<li>Because this player does not have Bird Rights, you may add no more than $raisePercent of the amount you offer in the first year as a raise between years (for instance, if you offer 500 in Year 1, you cannot offer a raise of more than 50 between any two subsequent years.)</li>";
        }
        
        $output .= "<li>When re-signing your own players, you can go over the soft cap and up to the hard cap (" . \League::HARD_CAP_MAX . ").</li>";
        $output .= "</ul></td></tr>";
        
        // Hidden fields
        $output .= "<input type=\"hidden\" name=\"maxyr1\" value=\"$maxYearOneSalary\">";
        $output .= "<input type=\"hidden\" name=\"demandsTotal\" value=\"{$demands['total']}\">";
        $output .= "<input type=\"hidden\" name=\"demandsYears\" value=\"{$demands['years']}\">";
        $output .= "<input type=\"hidden\" name=\"teamName\" value=\"$teamName\">";
        $output .= "<input type=\"hidden\" name=\"playerName\" value=\"$playerName\">";
        $output .= "<input type=\"hidden\" name=\"playerID\" value=\"$playerID\">";
        $output .= "</table>";
        $output .= "<input type=\"submit\" value=\"Offer Extension!\"></form>";
        
        return $output;
    }
    
    /**
     * Build demand display string
     * 
     * @param array $demands Demand amounts
     * @return string HTML formatted demand display
     */
    private static function buildDemandDisplay(array $demands): string
    {
        $display = (string)$demands['year1'];
        
        if ($demands['year2'] != 0) {
            $display .= "</td><td>" . $demands['year2'];
        }
        if ($demands['year3'] != 0) {
            $display .= "</td><td>" . $demands['year3'];
        }
        if ($demands['year4'] != 0) {
            $display .= "</td><td>" . $demands['year4'];
        }
        if ($demands['year5'] != 0) {
            $display .= "</td><td>" . $demands['year5'];
        }
        if ($demands['year6'] != 0) {
            $display .= "</td><td>" . $demands['year6'];
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
        $output = "<INPUT TYPE=\"number\" style=\"width: 4em\" NAME=\"offerYear1\" SIZE=\"4\" VALUE=\"{$demands['year1']}\"></td>";
        $output .= "<td><INPUT TYPE=\"number\" style=\"width: 4em\" NAME=\"offerYear2\" SIZE=\"4\" VALUE=\"{$demands['year2']}\"></td>";
        $output .= "<td><INPUT TYPE=\"number\" style=\"width: 4em\" NAME=\"offerYear3\" SIZE=\"4\" VALUE=\"{$demands['year3']}\"></td>";
        $output .= "<td><INPUT TYPE=\"number\" style=\"width: 4em\" NAME=\"offerYear4\" SIZE=\"4\" VALUE=\"{$demands['year4']}\"></td>";
        $output .= "<td><INPUT TYPE=\"number\" style=\"width: 4em\" NAME=\"offerYear5\" SIZE=\"4\" VALUE=\"{$demands['year5']}\"></td>";
        
        return $output;
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
        
        $output = "<INPUT TYPE=\"text\" NAME=\"offerYear1\" SIZE=\"4\" VALUE=\"$maxYearOne\"></td>";
        $output .= "<td><INPUT TYPE=\"text\" NAME=\"offerYear2\" SIZE=\"4\" VALUE=\"$maxYr2\"></td>";
        $output .= "<td><INPUT TYPE=\"text\" NAME=\"offerYear3\" SIZE=\"4\" VALUE=\"$maxYr3\"></td>";
        $output .= "<td><INPUT TYPE=\"text\" NAME=\"offerYear4\" SIZE=\"4\" VALUE=\"$maxYr4\"></td>";
        $output .= "<td><INPUT TYPE=\"text\" NAME=\"offerYear5\" SIZE=\"4\" VALUE=\"$maxYr5\"></td>";
        
        return $output;
    }
    
    /**
     * Render error message
     * 
     * @param string $error Error message to display
     * @return string HTML output
     */
    public static function renderError(string $error): string
    {
        return "<p>" . DatabaseService::safeHtmlOutput($error) . "</p>";
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
        
        return "<b>$playerPos $playerName</b> - Contract Demands:<br>";
    }
}
