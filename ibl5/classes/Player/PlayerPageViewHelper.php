<?php

declare(strict_types=1);

namespace Player;

use PlayerStats;

/**
 * PlayerPageViewHelper - HTML generation for player page display
 * 
 * Handles all HTML rendering for the player page, keeping presentation
 * logic separate from business logic.
 */
class PlayerPageViewHelper
{
    /**
     * Generate player header HTML (name, nickname, team)
     * 
     * @param Player $player The player to display
     * @param int $playerID The player's ID
     * @return string HTML for player header
     */
    public function renderPlayerHeader(Player $player, int $playerID): string
    {
        $html = "<table>\n    <tr>\n        <td valign=top><font class=\"title\">$player->position $player->name ";
        
        if ($player->nickname != NULL) {
            $html .= "- Nickname: \"$player->nickname\" ";
        }
        
        $html .= "(<a href=\"modules.php?name=Team&op=team&teamID=$player->teamID\">$player->teamName</a>)</font>\n";
        $html .= "    <hr>\n";
        $html .= "    <table>\n";
        $html .= "        <tr>\n";
        $html .= "            <td valign=center><img src=\"images/player/$playerID.jpg\" height=\"90\" width=\"65\"></td>\n";
        $html .= "            <td>";
        
        return $html;
    }

    /**
     * Generate rookie option used message HTML
     * 
     * @return string HTML for rookie option used message
     */
    public function renderRookieOptionUsedMessage(): string
    {
        return "<table align=right bgcolor=#ff0000>\n" .
               "    <tr>\n" .
               "        <td align=center>ROOKIE OPTION<br>USED; RENEGOTIATION<br>IMPOSSIBLE</td>\n" .
               "    </tr>\n" .
               "</table>";
    }

    /**
     * Generate renegotiation button HTML
     * 
     * @param int $playerID The player's ID
     * @return string HTML for renegotiation button
     */
    public function renderRenegotiationButton(int $playerID): string
    {
        return "<table align=right bgcolor=#ff0000>\n" .
               "    <tr>\n" .
               "        <td align=center><a href=\"modules.php?name=Player&pa=negotiate&pid=$playerID\">RENEGOTIATE<BR>CONTRACT</a></td>\n" .
               "    </tr>\n" .
               "</table>";
    }

    /**
     * Generate rookie option button HTML
     * 
     * @param int $playerID The player's ID
     * @return string HTML for rookie option button
     */
    public function renderRookieOptionButton(int $playerID): string
    {
        return "<table align=right bgcolor=#ffbb00>\n" .
               "    <tr>\n" .
               "        <td align=center><a href=\"modules.php?name=Player&pa=rookieoption&pid=$playerID\">ROOKIE<BR>OPTION</a></td>\n" .
               "    </tr>\n" .
               "</table>";
    }

    /**
     * Generate player bio and info section HTML
     * 
     * @param Player $player The player to display
     * @param string $contractDisplay Formatted contract string
     * @return string HTML for bio/info section
     */
    public function renderPlayerBioSection(Player $player, string $contractDisplay): string
    {
        $html = "<font class=\"content\">Age: $player->age | Height: $player->heightFeet-$player->heightInches | Weight: $player->weightPounds | College: $player->collegeName<br>\n";
        $html .= "    <i>Drafted by the $player->draftTeamOriginalName with the # $player->draftPickNumber pick of round $player->draftRound in the <a href=\"draft.php?year=$player->draftYear\">$player->draftYear Draft</a></i><br>\n";
        $html .= "    <center><table>\n";
        $html .= $this->renderRatingsTableHeaders();
        $html .= $this->renderRatingsTableValues($player);
        $html .= "    </table></center>\n";
        $html .= "<b>BIRD YEARS:</b> $player->birdYears | <b>Remaining Contract:</b> $contractDisplay </td>";
        
        return $html;
    }

    /**
     * Generate ratings table headers HTML
     * 
     * @return string HTML for ratings table headers
     */
    private function renderRatingsTableHeaders(): string
    {
        return "        <tr>\n" .
               "            <td align=center><b>2ga</b></td>\n" .
               "            <td align=center><b>2gp</b></td>\n" .
               "            <td align=center><b>fta</b></td>\n" .
               "            <td align=center><b>ftp</b></td>\n" .
               "            <td align=center><b>3ga</b></td>\n" .
               "            <td align=center><b>3gp</b></td>\n" .
               "            <td align=center><b>orb</b></td>\n" .
               "            <td align=center><b>drb</b></td>\n" .
               "            <td align=center><b>ast</b></td>\n" .
               "            <td align=center><b>stl</b></td>\n" .
               "            <td align=center><b>tvr</b></td>\n" .
               "            <td align=center><b>blk</b></td>\n" .
               "            <td align=center><b>foul</b></td>\n" .
               "            <td align=center><b>oo</b></td>\n" .
               "            <td align=center><b>do</b></td>\n" .
               "            <td align=center><b>po</b></td>\n" .
               "            <td align=center><b>to</b></td>\n" .
               "            <td align=center><b>od</b></td>\n" .
               "            <td align=center><b>dd</b></td>\n" .
               "            <td align=center><b>pd</b></td>\n" .
               "            <td align=center><b>td</b></td>\n" .
               "        </tr>\n";
    }

    /**
     * Generate ratings table values HTML
     * 
     * @param Player $player The player whose ratings to display
     * @return string HTML for ratings table values
     */
    private function renderRatingsTableValues(Player $player): string
    {
        return "        <tr>\n" .
               "            <td align=center>$player->ratingFieldGoalAttempts</td>\n" .
               "            <td align=center>$player->ratingFieldGoalPercentage</td>\n" .
               "            <td align=center>$player->ratingFreeThrowAttempts</td>\n" .
               "            <td align=center>$player->ratingFreeThrowPercentage</td>\n" .
               "            <td align=center>$player->ratingThreePointAttempts</td>\n" .
               "            <td align=center>$player->ratingThreePointPercentage</td>\n" .
               "            <td align=center>$player->ratingOffensiveRebounds</td>\n" .
               "            <td align=center>$player->ratingDefensiveRebounds</td>\n" .
               "            <td align=center>$player->ratingAssists</td>\n" .
               "            <td align=center>$player->ratingSteals</td>\n" .
               "            <td align=center>$player->ratingTurnovers</td>\n" .
               "            <td align=center>$player->ratingBlocks</td>\n" .
               "            <td align=center>$player->ratingFouls</td>\n" .
               "            <td align=center>$player->ratingOutsideOffense</td>\n" .
               "            <td align=center>$player->ratingDriveOffense</td>\n" .
               "            <td align=center>$player->ratingPostOffense</td>\n" .
               "            <td align=center>$player->ratingTransitionOffense</td>\n" .
               "            <td align=center>$player->ratingOutsideDefense</td>\n" .
               "            <td align=center>$player->ratingDriveDefense</td>\n" .
               "            <td align=center>$player->ratingPostDefense</td>\n" .
               "            <td align=center>$player->ratingTransitionDefense</td>\n" .
               "        </tr>\n";
    }

    /**
     * Generate player highs table HTML
     * 
     * @param PlayerStats $playerStats The player's statistics
     * @return string HTML for player highs table
     */
    public function renderPlayerHighsTable(PlayerStats $playerStats): string
    {
        $html = "<td rowspan=3 valign=top>\n";
        $html .= "    <table border=1 cellspacing=0 cellpadding=0>\n";
        $html .= "        <tr bgcolor=#0000cc>\n";
        $html .= "            <td align=center colspan=3><font color=#ffffff><b>PLAYER HIGHS</b></font></td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr bgcolor=#0000cc>\n";
        $html .= "            <td align=center colspan=3><font color=#ffffff><b>Regular-Season</b></font></td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr bgcolor=#0000cc>\n";
        $html .= "            <td></td>\n";
        $html .= "            <td><font color=#ffffff>Ssn</font></td>\n";
        $html .= "            <td><font color=#ffffff>Car</td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr>\n";
        $html .= "            <td><b>Points</b></td>\n";
        $html .= "            <td>$playerStats->seasonHighPoints</td>\n";
        $html .= "            <td>$playerStats->careerSeasonHighPoints</td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr>\n";
        $html .= "            <td><b>Rebounds</b></td>\n";
        $html .= "            <td>$playerStats->seasonHighRebounds</td>\n";
        $html .= "            <td>$playerStats->careerSeasonHighRebounds</td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr>\n";
        $html .= "            <td><b>Assists</b></td>\n";
        $html .= "            <td>$playerStats->seasonHighAssists</td>\n";
        $html .= "            <td>$playerStats->careerSeasonHighAssists</td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr>\n";
        $html .= "            <td><b>Steals</b></td>\n";
        $html .= "            <td>$playerStats->seasonHighSteals</td>\n";
        $html .= "            <td>$playerStats->careerSeasonHighSteals</td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr>\n";
        $html .= "            <td><b>Blocks</b></td>\n";
        $html .= "            <td>$playerStats->seasonHighBlocks</td>\n";
        $html .= "            <td>$playerStats->careerSeasonHighBlocks</td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr>\n";
        $html .= "            <td>Double-Doubles</td>\n";
        $html .= "            <td>$playerStats->seasonDoubleDoubles</td>\n";
        $html .= "            <td>$playerStats->careerDoubleDoubles</td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr>\n";
        $html .= "            <td>Triple-Doubles</td>\n";
        $html .= "            <td>$playerStats->seasonTripleDoubles</td>\n";
        $html .= "            <td>$playerStats->careerTripleDoubles</td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr bgcolor=#0000cc>\n";
        $html .= "            <td align=center colspan=3><font color=#ffffff><b>Playoffs</b></font></td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr bgcolor=#0000cc>\n";
        $html .= "            <td></td>\n";
        $html .= "            <td><font color=#ffffff>Ssn</font></td>\n";
        $html .= "            <td><font color=#ffffff>Car</td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr>\n";
        $html .= "            <td><b>Points</b></td>\n";
        $html .= "            <td>$playerStats->seasonPlayoffHighPoints</td>\n";
        $html .= "            <td>$playerStats->careerPlayoffHighPoints</td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr>\n";
        $html .= "            <td><b>Rebounds</b></td>\n";
        $html .= "            <td>$playerStats->seasonPlayoffHighRebounds</td>\n";
        $html .= "            <td>$playerStats->careerPlayoffHighRebounds</td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr>\n";
        $html .= "            <td><b>Assists</b></td>\n";
        $html .= "            <td>$playerStats->seasonPlayoffHighAssists</td>\n";
        $html .= "            <td>$playerStats->careerPlayoffHighAssists</td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr>\n";
        $html .= "            <td><b>Steals</b></td>\n";
        $html .= "            <td>$playerStats->seasonPlayoffHighSteals</td>\n";
        $html .= "            <td>$playerStats->careerPlayoffHighSteals</td>\n";
        $html .= "        </tr>\n";
        $html .= "        <tr>\n";
        $html .= "            <td><b>Blocks</b></td>\n";
        $html .= "            <td>$playerStats->seasonPlayoffHighBlocks</td>\n";
        $html .= "            <td>$playerStats->careerPlayoffHighBlocks</td>\n";
        $html .= "        </tr>\n";
        $html .= "    </table></td>";
        
        return $html;
    }

    /**
     * Generate player menu navigation HTML
     * 
     * @param int $playerID The player's ID
     * @return string HTML for player menu
     */
    public function renderPlayerMenu(int $playerID): string
    {
        $html = "<tr>\n";
        $html .= "    <td colspan=2><hr></td>\n";
        $html .= "</tr>\n";
        $html .= "<tr>\n";
        $html .= "    <td colspan=2><b><center>PLAYER MENU</center></b><br>\n";
        $html .= "        <center>\n";
        $html .= "        <a href=\"" . \PlayerPageType::getUrl($playerID, \PlayerPageType::OVERVIEW) . "\">" . \PlayerPageType::getDescription(\PlayerPageType::OVERVIEW) . "</a> | ";
        $html .= "<a href=\"" . \PlayerPageType::getUrl($playerID, \PlayerPageType::AWARDS_AND_NEWS) . "\">" . \PlayerPageType::getDescription(\PlayerPageType::AWARDS_AND_NEWS) . "</a><br>\n";
        $html .= "        <a href=\"" . \PlayerPageType::getUrl($playerID, \PlayerPageType::ONE_ON_ONE) . "\">" . \PlayerPageType::getDescription(\PlayerPageType::ONE_ON_ONE) . "</a> | ";
        $html .= "<a href=\"" . \PlayerPageType::getUrl($playerID, \PlayerPageType::SIM_STATS) . "\">" . \PlayerPageType::getDescription(\PlayerPageType::SIM_STATS) . "</a><br>\n";
        $html .= "        <a href=\"" . \PlayerPageType::getUrl($playerID, \PlayerPageType::REGULAR_SEASON_TOTALS) . "\">" . \PlayerPageType::getDescription(\PlayerPageType::REGULAR_SEASON_TOTALS) . "</a> | ";
        $html .= "<a href=\"" . \PlayerPageType::getUrl($playerID, \PlayerPageType::REGULAR_SEASON_AVERAGES) . "\">" . \PlayerPageType::getDescription(\PlayerPageType::REGULAR_SEASON_AVERAGES) . "</a><br>\n";
        $html .= "        <a href=\"" . \PlayerPageType::getUrl($playerID, \PlayerPageType::PLAYOFF_TOTALS) . "\">" . \PlayerPageType::getDescription(\PlayerPageType::PLAYOFF_TOTALS) . "</a> | ";
        $html .= "<a href=\"" . \PlayerPageType::getUrl($playerID, \PlayerPageType::PLAYOFF_AVERAGES) . "\">" . \PlayerPageType::getDescription(\PlayerPageType::PLAYOFF_AVERAGES) . "</a><br>\n";
        $html .= "        <a href=\"" . \PlayerPageType::getUrl($playerID, \PlayerPageType::HEAT_TOTALS) . "\">" . \PlayerPageType::getDescription(\PlayerPageType::HEAT_TOTALS) . "</a> | ";
        $html .= "<a href=\"" . \PlayerPageType::getUrl($playerID, \PlayerPageType::HEAT_AVERAGES) . "\">" . \PlayerPageType::getDescription(\PlayerPageType::HEAT_AVERAGES) . "</a><br>\n";
        $html .= "        <a href=\"" . \PlayerPageType::getUrl($playerID, \PlayerPageType::OLYMPIC_TOTALS) . "\">" . \PlayerPageType::getDescription(\PlayerPageType::OLYMPIC_TOTALS) . "</a> | ";
        $html .= "<a href=\"" . \PlayerPageType::getUrl($playerID, \PlayerPageType::OLYMPIC_AVERAGES) . "\">" . \PlayerPageType::getDescription(\PlayerPageType::OLYMPIC_AVERAGES) . "</a><br>\n";
        $html .= "        <a href=\"" . \PlayerPageType::getUrl($playerID, \PlayerPageType::RATINGS_AND_SALARY) . "\">" . \PlayerPageType::getDescription(\PlayerPageType::RATINGS_AND_SALARY) . "</a>\n";
        $html .= "        </center>\n";
        $html .= "    </td>\n";
        $html .= "</tr>\n";
        $html .= "<tr>\n";
        $html .= "    <td colspan=3><hr></td>\n";
        $html .= "</tr>";
        
        return $html;
    }
}
