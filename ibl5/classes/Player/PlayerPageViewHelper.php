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
        ob_start();
        ?>
<table>
    <tr>
        <td valign=top><font class="title"><?= $player->position ?> <?= $player->name ?>
        <?php if ($player->nickname != NULL): ?>
            - Nickname: "<?= $player->nickname ?>"
        <?php endif; ?>
            (<a href="modules.php?name=Team&op=team&teamID=<?= $player->teamID ?>"><?= $player->teamName ?></a>)</font>
        <hr>
        <table>
            <tr>
                <td valign=center><img src="images/player/<?= $playerID ?>.jpg" height="90" width="65"></td>
                <td>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate rookie option used message HTML
     * 
     * @return string HTML for rookie option used message
     */
    public function renderRookieOptionUsedMessage(): string
    {
        ob_start();
        ?>
<table align=right bgcolor=#ff0000>
    <tr>
        <td align=center>ROOKIE OPTION<br>USED; RENEGOTIATION<br>IMPOSSIBLE</td>
    </tr>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate renegotiation button HTML
     * 
     * @param int $playerID The player's ID
     * @return string HTML for renegotiation button
     */
    public function renderRenegotiationButton(int $playerID): string
    {
        ob_start();
        ?>
<table align=right bgcolor=#ff0000>
    <tr>
        <td align=center><a href="modules.php?name=Player&pa=negotiate&pid=<?= $playerID ?>">RENEGOTIATE<BR>CONTRACT</a></td>
    </tr>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate rookie option button HTML
     * 
     * @param int $playerID The player's ID
     * @return string HTML for rookie option button
     */
    public function renderRookieOptionButton(int $playerID): string
    {
        ob_start();
        ?>
<table align=right bgcolor=#ffbb00>
    <tr>
        <td align=center><a href="modules.php?name=Player&pa=rookieoption&pid=<?= $playerID ?>">ROOKIE<BR>OPTION</a></td>
    </tr>
</table>
        <?php
        return ob_get_clean();
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
        ob_start();
        ?>
<font class="content">Age: <?= $player->age ?> | Height: <?= $player->heightFeet ?>-<?= $player->heightInches ?> | Weight: <?= $player->weightPounds ?> | College: <?= $player->collegeName ?><br>
    <i>Drafted by the <?= $player->draftTeamOriginalName ?> with the # <?= $player->draftPickNumber ?> pick of round <?= $player->draftRound ?> in the <a href="draft.php?year=<?= $player->draftYear ?>"><?= $player->draftYear ?> Draft</a></i><br>
    <center><table>
        <?php
        echo $this->renderRatingsTableHeaders();
        echo $this->renderRatingsTableValues($player);
        ?>
    </table></center>
<b>BIRD YEARS:</b> <?= $player->birdYears ?> | <b>Remaining Contract:</b> <?= $contractDisplay ?> </td>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate ratings table headers HTML
     * 
     * @return string HTML for ratings table headers
     */
    private function renderRatingsTableHeaders(): string
    {
        ob_start();
        ?>
<tr>
    <td align=center><b>2ga</b></td>
    <td align=center><b>2gp</b></td>
    <td align=center><b>fta</b></td>
    <td align=center><b>ftp</b></td>
    <td align=center><b>3ga</b></td>
    <td align=center><b>3gp</b></td>
    <td align=center><b>orb</b></td>
    <td align=center><b>drb</b></td>
    <td align=center><b>ast</b></td>
    <td align=center><b>stl</b></td>
    <td align=center><b>tvr</b></td>
    <td align=center><b>blk</b></td>
    <td align=center><b>foul</b></td>
    <td align=center><b>oo</b></td>
    <td align=center><b>do</b></td>
    <td align=center><b>po</b></td>
    <td align=center><b>to</b></td>
    <td align=center><b>od</b></td>
    <td align=center><b>dd</b></td>
    <td align=center><b>pd</b></td>
    <td align=center><b>td</b></td>
</tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate ratings table values HTML
     * 
     * @param Player $player The player whose ratings to display
     * @return string HTML for ratings table values
     */
    private function renderRatingsTableValues(Player $player): string
    {
        ob_start();
        ?>
<tr>
    <td align=center><?= $player->ratingFieldGoalAttempts ?></td>
    <td align=center><?= $player->ratingFieldGoalPercentage ?></td>
    <td align=center><?= $player->ratingFreeThrowAttempts ?></td>
    <td align=center><?= $player->ratingFreeThrowPercentage ?></td>
    <td align=center><?= $player->ratingThreePointAttempts ?></td>
    <td align=center><?= $player->ratingThreePointPercentage ?></td>
    <td align=center><?= $player->ratingOffensiveRebounds ?></td>
    <td align=center><?= $player->ratingDefensiveRebounds ?></td>
    <td align=center><?= $player->ratingAssists ?></td>
    <td align=center><?= $player->ratingSteals ?></td>
    <td align=center><?= $player->ratingTurnovers ?></td>
    <td align=center><?= $player->ratingBlocks ?></td>
    <td align=center><?= $player->ratingFouls ?></td>
    <td align=center><?= $player->ratingOutsideOffense ?></td>
    <td align=center><?= $player->ratingDriveOffense ?></td>
    <td align=center><?= $player->ratingPostOffense ?></td>
    <td align=center><?= $player->ratingTransitionOffense ?></td>
    <td align=center><?= $player->ratingOutsideDefense ?></td>
    <td align=center><?= $player->ratingDriveDefense ?></td>
    <td align=center><?= $player->ratingPostDefense ?></td>
    <td align=center><?= $player->ratingTransitionDefense ?></td>
</tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate player highs table HTML
     * 
     * @param PlayerStats $playerStats The player's statistics
     * @return string HTML for player highs table
     */
    public function renderPlayerHighsTable(PlayerStats $playerStats): string
    {
        ob_start();
        ?>
<td rowspan=3 valign=top>
    <table border=1 cellspacing=0 cellpadding=0>
        <tr bgcolor=#0000cc>
            <td align=center colspan=3><font color=#ffffff><b>PLAYER HIGHS</b></font></td>
        </tr>
        <tr bgcolor=#0000cc>
            <td align=center colspan=3><font color=#ffffff><b>Regular-Season</b></font></td>
        </tr>
        <tr bgcolor=#0000cc>
            <td></td>
            <td><font color=#ffffff>Ssn</font></td>
            <td><font color=#ffffff>Car</td>
        </tr>
        <tr>
            <td><b>Points</b></td>
            <td><?= $playerStats->seasonHighPoints ?></td>
            <td><?= $playerStats->careerSeasonHighPoints ?></td>
        </tr>
        <tr>
            <td><b>Rebounds</b></td>
            <td><?= $playerStats->seasonHighRebounds ?></td>
            <td><?= $playerStats->careerSeasonHighRebounds ?></td>
        </tr>
        <tr>
            <td><b>Assists</b></td>
            <td><?= $playerStats->seasonHighAssists ?></td>
            <td><?= $playerStats->careerSeasonHighAssists ?></td>
        </tr>
        <tr>
            <td><b>Steals</b></td>
            <td><?= $playerStats->seasonHighSteals ?></td>
            <td><?= $playerStats->careerSeasonHighSteals ?></td>
        </tr>
        <tr>
            <td><b>Blocks</b></td>
            <td><?= $playerStats->seasonHighBlocks ?></td>
            <td><?= $playerStats->careerSeasonHighBlocks ?></td>
        </tr>
        <tr>
            <td>Double-Doubles</td>
            <td><?= $playerStats->seasonDoubleDoubles ?></td>
            <td><?= $playerStats->careerDoubleDoubles ?></td>
        </tr>
        <tr>
            <td>Triple-Doubles</td>
            <td><?= $playerStats->seasonTripleDoubles ?></td>
            <td><?= $playerStats->careerTripleDoubles ?></td>
        </tr>
        <tr bgcolor=#0000cc>
            <td align=center colspan=3><font color=#ffffff><b>Playoffs</b></font></td>
        </tr>
        <tr bgcolor=#0000cc>
            <td></td>
            <td><font color=#ffffff>Ssn</font></td>
            <td><font color=#ffffff>Car</td>
        </tr>
        <tr>
            <td><b>Points</b></td>
            <td><?= $playerStats->seasonPlayoffHighPoints ?></td>
            <td><?= $playerStats->careerPlayoffHighPoints ?></td>
        </tr>
        <tr>
            <td><b>Rebounds</b></td>
            <td><?= $playerStats->seasonPlayoffHighRebounds ?></td>
            <td><?= $playerStats->careerPlayoffHighRebounds ?></td>
        </tr>
        <tr>
            <td><b>Assists</b></td>
            <td><?= $playerStats->seasonPlayoffHighAssists ?></td>
            <td><?= $playerStats->careerPlayoffHighAssists ?></td>
        </tr>
        <tr>
            <td><b>Steals</b></td>
            <td><?= $playerStats->seasonPlayoffHighSteals ?></td>
            <td><?= $playerStats->careerPlayoffHighSteals ?></td>
        </tr>
        <tr>
            <td><b>Blocks</b></td>
            <td><?= $playerStats->seasonPlayoffHighBlocks ?></td>
            <td><?= $playerStats->careerPlayoffHighBlocks ?></td>
        </tr>
    </table></td>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate player menu navigation HTML
     * 
     * @param int $playerID The player's ID
     * @return string HTML for player menu
     */
    public function renderPlayerMenu(int $playerID): string
    {
        ob_start();
        ?>
<tr>
    <td colspan=2><hr></td>
</tr>
<tr>
    <td colspan=2><b><center>PLAYER MENU</center></b><br>
        <center>
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::OVERVIEW) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::OVERVIEW) ?></a> | <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::AWARDS_AND_NEWS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::AWARDS_AND_NEWS) ?></a><br>
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::ONE_ON_ONE) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::ONE_ON_ONE) ?></a> | <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::SIM_STATS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::SIM_STATS) ?></a><br>
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::REGULAR_SEASON_TOTALS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::REGULAR_SEASON_TOTALS) ?></a> | <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::REGULAR_SEASON_AVERAGES) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::REGULAR_SEASON_AVERAGES) ?></a><br>
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::PLAYOFF_TOTALS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::PLAYOFF_TOTALS) ?></a> | <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::PLAYOFF_AVERAGES) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::PLAYOFF_AVERAGES) ?></a><br>
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::HEAT_TOTALS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::HEAT_TOTALS) ?></a> | <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::HEAT_AVERAGES) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::HEAT_AVERAGES) ?></a><br>
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::OLYMPIC_TOTALS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::OLYMPIC_TOTALS) ?></a> | <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::OLYMPIC_AVERAGES) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::OLYMPIC_AVERAGES) ?></a><br>
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::RATINGS_AND_SALARY) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::RATINGS_AND_SALARY) ?></a>
        </center>
    </td>
</tr>
<tr>
    <td colspan=3><hr></td>
</tr>
        <?php
        return ob_get_clean();
    }
}
