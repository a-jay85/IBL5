<?php

declare(strict_types=1);

namespace Player;

use Player\Contracts\PlayerPageViewHelperInterface;
use Player\PlayerStats;

/**
 * @see PlayerPageViewHelperInterface
 */
class PlayerPageViewHelper implements PlayerPageViewHelperInterface
{
    /**
     * @see PlayerPageViewHelperInterface::renderPlayerHeader()
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
     * @see PlayerPageViewHelperInterface::renderRookieOptionUsedMessage()
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
     * @see PlayerPageViewHelperInterface::renderRenegotiationButton()
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
     * @see PlayerPageViewHelperInterface::renderRookieOptionButton()
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
    <em>Drafted by the <?= htmlspecialchars((string)($player->draftTeamOriginalName ?? '')) ?> with the # <?= htmlspecialchars((string)$player->draftPickNumber) ?> pick of round <?= htmlspecialchars((string)$player->draftRound) ?> in the <a href="/ibl5/pages/draftHistory.php?year=<?= $player->draftYear ?>"><?= htmlspecialchars((string)$player->draftYear) ?> Draft</a></em><br>
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
     * @param int $allStarGames Number of All-Star Games
     * @param int $threePointContests Number of Three-Point Contests
     * @param int $dunkContests Number of Slam Dunk Competitions
     * @param int $rookieSophChallenges Number of Rookie-Sophomore Challenges
     * @return string HTML for player highs table
     */
    public function renderPlayerHighsTable(
        PlayerStats $playerStats,
        int $allStarGames = 0,
        int $threePointContests = 0,
        int $dunkContests = 0,
        int $rookieSophChallenges = 0
    ): string
    {
        ob_start();
        ?>
<style>
.player-highs { border: 1px solid black; border-collapse: collapse; }
.player-highs td { padding: 0; }
.player-highs .header-main { background-color: #0000cc; color: white; text-align: center; font-weight: bold; }
.player-highs .header-sub { background-color: #0000cc; color: white; text-align: center; }
.player-highs .header-label { background-color: #0000cc; color: white; }
.player-highs .stat-label { text-align: right; padding-left: 2px; padding-right: 2px; font-weight: bold; }
.player-highs .stat-value { padding-left: 2px; padding-right: 2px; }
</style>
<td rowspan=3 style="vertical-align: top;">
    <table border=1 cellspacing=0 cellpadding=0 class="player-highs">
        <tr>
            <td class="header-main" colspan=3>PLAYER HIGHS</td>
        </tr>
        <tr>
            <td class="header-sub" colspan=3>Regular-Season</td>
        </tr>
        <tr>
            <td class="header-label"></td>
            <td class="header-label"><strong>Ssn</strong></td>
            <td class="header-label"><strong>Car</strong></td>
        </tr>
        <tr>
            <td class="stat-label">Points</td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->seasonHighPoints) ?></td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->careerSeasonHighPoints) ?></td>
        </tr>
        <tr>
            <td class="stat-label">Rebounds</td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->seasonHighRebounds) ?></td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->careerSeasonHighRebounds) ?></td>
        </tr>
        <tr>
            <td class="stat-label">Assists</td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->seasonHighAssists) ?></td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->careerSeasonHighAssists) ?></td>
        </tr>
        <tr>
            <td class="stat-label">Steals</td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->seasonHighSteals) ?></td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->careerSeasonHighSteals) ?></td>
        </tr>
        <tr>
            <td class="stat-label">Blocks</td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->seasonHighBlocks) ?></td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->careerSeasonHighBlocks) ?></td>
        </tr>
        <tr>
            <td class="stat-label">Double-Doubles</td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->seasonDoubleDoubles) ?></td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->careerDoubleDoubles) ?></td>
        </tr>
        <tr>
            <td class="stat-label">Triple-Doubles</td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->seasonTripleDoubles) ?></td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->careerTripleDoubles) ?></td>
        </tr>
        <tr>
            <td class="header-main" colspan=3>Playoffs</td>
        </tr>
        <tr>
            <td class="header-label"></td>
            <td class="header-label"><strong>Ssn</strong></td>
            <td class="header-label"><strong>Car</strong></td>
        </tr>
        <tr>
            <td class="stat-label">Points</td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->seasonPlayoffHighPoints) ?></td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->careerPlayoffHighPoints) ?></td>
        </tr>
        <tr>
            <td class="stat-label">Rebounds</td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->seasonPlayoffHighRebounds) ?></td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->careerPlayoffHighRebounds) ?></td>
        </tr>
        <tr>
            <td class="stat-label">Assists</td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->seasonPlayoffHighAssists) ?></td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->careerPlayoffHighAssists) ?></td>
        </tr>
        <tr>
            <td class="stat-label">Steals</td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->seasonPlayoffHighSteals) ?></td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->careerPlayoffHighSteals) ?></td>
        </tr>
        <tr>
            <td class="stat-label">Blocks</td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->seasonPlayoffHighBlocks) ?></td>
            <td class="stat-value"><?= htmlspecialchars((string)$playerStats->careerPlayoffHighBlocks) ?></td>
        </tr>
        <tr>
            <td class="header-main" colspan=3>All-Star Activity</td>
        </tr>
        <tr>
            <td colspan=2><strong>All Star Games:</strong></td>
            <td><?= htmlspecialchars((string)$allStarGames) ?></td>
        </tr>
        <tr>
            <td colspan=2><strong>Three-Point Contests:</strong></td>
            <td><?= htmlspecialchars((string)$threePointContests) ?></td>
        </tr>
        <tr>
            <td colspan=2><strong>Slam Dunk Competitions:</strong></td>
            <td><?= htmlspecialchars((string)$dunkContests) ?></td>
        </tr>
        <tr>
            <td colspan=2><strong>Rookie-Soph Challenges:</strong></td>
            <td><?= htmlspecialchars((string)$rookieSophChallenges) ?></td>
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
