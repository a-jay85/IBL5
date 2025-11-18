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
        $playerImageUrl = PlayerImageHelper::getImageUrl($playerID);
        ?>
<table>
    <tr>
        <td valign=top><h2 class="player-title"><?= htmlspecialchars($player->position) ?> <?= htmlspecialchars($player->name) ?>
        <?php if ($player->nickname != NULL): ?>
            - Nickname: "<?= htmlspecialchars($player->nickname) ?>"
        <?php endif; ?>
            (<a href="modules.php?name=Team&op=team&teamID=<?= $player->teamID ?>"><?= htmlspecialchars($player->teamName) ?></a>)</h2>
        <hr>
        <table>
            <tr>
                <td valign=center><img src="<?= $playerImageUrl ?>" height="90" width="65" alt="<?= htmlspecialchars($player->name) ?>"></td>
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
<table class="rookie-option-used" style="float: right; background-color: #ff0000;">
    <tr>
        <td style="text-align: center;">ROOKIE OPTION<br>USED; RENEGOTIATION<br>IMPOSSIBLE</td>
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
<table class="renegotiation-button" style="float: right; background-color: #ff0000;">
    <tr>
        <td style="text-align: center;"><a href="modules.php?name=Player&pa=negotiate&pid=<?= $playerID ?>">RENEGOTIATE<BR>CONTRACT</a></td>
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
<table class="rookie-option-button" style="float: right; background-color: #ffbb00;">
    <tr>
        <td style="text-align: center;"><a href="modules.php?name=Player&pa=rookieoption&pid=<?= $playerID ?>">ROOKIE<BR>OPTION</a></td>
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
<span class="player-bio">Age: <?= htmlspecialchars((string)$player->age) ?> | Height: <?= htmlspecialchars((string)$player->heightFeet) ?>-<?= htmlspecialchars((string)$player->heightInches) ?> | Weight: <?= htmlspecialchars((string)$player->weightPounds) ?> | College: <?= htmlspecialchars((string)($player->collegeName ?? '')) ?><br>
    <em>Drafted by the <?= htmlspecialchars((string)($player->draftTeamOriginalName ?? '')) ?> with the # <?= htmlspecialchars((string)$player->draftPickNumber) ?> pick of round <?= htmlspecialchars((string)$player->draftRound) ?> in the <a href="draft.php?year=<?= $player->draftYear ?>"><?= htmlspecialchars((string)$player->draftYear) ?> Draft</a></em><br>
    <center><table>
        <?php
        echo $this->renderRatingsTableHeaders();
        echo $this->renderRatingsTableValues($player);
        ?>
    </table></center>
<strong>BIRD YEARS:</strong> <?= htmlspecialchars((string)$player->birdYears) ?> | <strong>Remaining Contract:</strong> <?= htmlspecialchars($contractDisplay) ?> </td>
            </tr>
        </table>
    </td>
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
    <td style="text-align: center;"><strong>2ga</strong></td>
    <td style="text-align: center;"><strong>2gp</strong></td>
    <td style="text-align: center;"><strong>fta</strong></td>
    <td style="text-align: center;"><strong>ftp</strong></td>
    <td style="text-align: center;"><strong>3ga</strong></td>
    <td style="text-align: center;"><strong>3gp</strong></td>
    <td style="text-align: center;"><strong>orb</strong></td>
    <td style="text-align: center;"><strong>drb</strong></td>
    <td style="text-align: center;"><strong>ast</strong></td>
    <td style="text-align: center;"><strong>stl</strong></td>
    <td style="text-align: center;"><strong>tvr</strong></td>
    <td style="text-align: center;"><strong>blk</strong></td>
    <td style="text-align: center;"><strong>foul</strong></td>
    <td style="text-align: center;"><strong>oo</strong></td>
    <td style="text-align: center;"><strong>do</strong></td>
    <td style="text-align: center;"><strong>po</strong></td>
    <td style="text-align: center;"><strong>to</strong></td>
    <td style="text-align: center;"><strong>od</strong></td>
    <td style="text-align: center;"><strong>dd</strong></td>
    <td style="text-align: center;"><strong>pd</strong></td>
    <td style="text-align: center;"><strong>td</strong></td>
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
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingFieldGoalAttempts) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingFieldGoalPercentage) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingFreeThrowAttempts) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingFreeThrowPercentage) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingThreePointAttempts) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingThreePointPercentage) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingOffensiveRebounds) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingDefensiveRebounds) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingAssists) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingSteals) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingTurnovers) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingBlocks) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingFouls) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingOutsideOffense) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingDriveOffense) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingPostOffense) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingTransitionOffense) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingOutsideDefense) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingDriveDefense) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingPostDefense) ?></td>
    <td style="text-align: center;"><?= htmlspecialchars((string)$player->ratingTransitionDefense) ?></td>
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
<td rowspan=3 style="vertical-align: top;">
    <table border=1 cellspacing=0 cellpadding=0 class="player-highs">
        <tr style="background-color: #0000cc;">
            <td style="text-align: center; color: white;" colspan=3><strong>PLAYER HIGHS</strong></td>
        </tr>
        <tr style="background-color: #0000cc;">
            <td style="text-align: center; color: white;" colspan=3><strong>Regular-Season</strong></td>
        </tr>
        <tr style="background-color: #0000cc;">
            <td></td>
            <td style="color: white;"><strong>Ssn</strong></td>
            <td style="color: white;"><strong>Car</strong></td>
        </tr>
        <tr>
            <td><strong>Points</strong></td>
            <td><?= htmlspecialchars((string)$playerStats->seasonHighPoints) ?></td>
            <td><?= htmlspecialchars((string)$playerStats->careerSeasonHighPoints) ?></td>
        </tr>
        <tr>
            <td><strong>Rebounds</strong></td>
            <td><?= htmlspecialchars((string)$playerStats->seasonHighRebounds) ?></td>
            <td><?= htmlspecialchars((string)$playerStats->careerSeasonHighRebounds) ?></td>
        </tr>
        <tr>
            <td><strong>Assists</strong></td>
            <td><?= htmlspecialchars((string)$playerStats->seasonHighAssists) ?></td>
            <td><?= htmlspecialchars((string)$playerStats->careerSeasonHighAssists) ?></td>
        </tr>
        <tr>
            <td><strong>Steals</strong></td>
            <td><?= htmlspecialchars((string)$playerStats->seasonHighSteals) ?></td>
            <td><?= htmlspecialchars((string)$playerStats->careerSeasonHighSteals) ?></td>
        </tr>
        <tr>
            <td><strong>Blocks</strong></td>
            <td><?= htmlspecialchars((string)$playerStats->seasonHighBlocks) ?></td>
            <td><?= htmlspecialchars((string)$playerStats->careerSeasonHighBlocks) ?></td>
        </tr>
        <tr>
            <td>Double-Doubles</td>
            <td><?= htmlspecialchars((string)$playerStats->seasonDoubleDoubles) ?></td>
            <td><?= htmlspecialchars((string)$playerStats->careerDoubleDoubles) ?></td>
        </tr>
        <tr>
            <td>Triple-Doubles</td>
            <td><?= htmlspecialchars((string)$playerStats->seasonTripleDoubles) ?></td>
            <td><?= htmlspecialchars((string)$playerStats->careerTripleDoubles) ?></td>
        </tr>
        <tr style="background-color: #0000cc;">
            <td style="text-align: center; color: white;" colspan=3><strong>Playoffs</strong></td>
        </tr>
        <tr style="background-color: #0000cc;">
            <td></td>
            <td style="color: white;"><strong>Ssn</strong></td>
            <td style="color: white;"><strong>Car</strong></td>
        </tr>
        <tr>
            <td><strong>Points</strong></td>
            <td><?= htmlspecialchars((string)$playerStats->seasonPlayoffHighPoints) ?></td>
            <td><?= htmlspecialchars((string)$playerStats->careerPlayoffHighPoints) ?></td>
        </tr>
        <tr>
            <td><strong>Rebounds</strong></td>
            <td><?= htmlspecialchars((string)$playerStats->seasonPlayoffHighRebounds) ?></td>
            <td><?= htmlspecialchars((string)$playerStats->careerPlayoffHighRebounds) ?></td>
        </tr>
        <tr>
            <td><strong>Assists</strong></td>
            <td><?= htmlspecialchars((string)$playerStats->seasonPlayoffHighAssists) ?></td>
            <td><?= htmlspecialchars((string)$playerStats->careerPlayoffHighAssists) ?></td>
        </tr>
        <tr>
            <td><strong>Steals</strong></td>
            <td><?= htmlspecialchars((string)$playerStats->seasonPlayoffHighSteals) ?></td>
            <td><?= htmlspecialchars((string)$playerStats->careerPlayoffHighSteals) ?></td>
        </tr>
        <tr>
            <td><strong>Blocks</strong></td>
            <td><?= htmlspecialchars((string)$playerStats->seasonPlayoffHighBlocks) ?></td>
            <td><?= htmlspecialchars((string)$playerStats->careerPlayoffHighBlocks) ?></td>
        </tr>
    </table>
</td>
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
    <td colspan=2 style="text-align: center;"><strong>PLAYER MENU</strong><br>
        <div style="text-align: center;">
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::OVERVIEW) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::OVERVIEW) ?></a> | <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::AWARDS_AND_NEWS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::AWARDS_AND_NEWS) ?></a><br>
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::ONE_ON_ONE) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::ONE_ON_ONE) ?></a> | <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::SIM_STATS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::SIM_STATS) ?></a><br>
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::REGULAR_SEASON_TOTALS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::REGULAR_SEASON_TOTALS) ?></a> | <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::REGULAR_SEASON_AVERAGES) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::REGULAR_SEASON_AVERAGES) ?></a><br>
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::PLAYOFF_TOTALS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::PLAYOFF_TOTALS) ?></a> | <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::PLAYOFF_AVERAGES) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::PLAYOFF_AVERAGES) ?></a><br>
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::HEAT_TOTALS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::HEAT_TOTALS) ?></a> | <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::HEAT_AVERAGES) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::HEAT_AVERAGES) ?></a><br>
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::OLYMPIC_TOTALS) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::OLYMPIC_TOTALS) ?></a> | <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::OLYMPIC_AVERAGES) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::OLYMPIC_AVERAGES) ?></a><br>
        <a href="<?= \PlayerPageType::getUrl($playerID, \PlayerPageType::RATINGS_AND_SALARY) ?>"><?= \PlayerPageType::getDescription(\PlayerPageType::RATINGS_AND_SALARY) ?></a>
        </div>
    </td>
</tr>
<tr>
    <td colspan=3><hr></td>
</tr>
        <?php
        return ob_get_clean();
    }
}
