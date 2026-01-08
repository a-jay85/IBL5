<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerStats;
use Utilities\HtmlSanitizer;

/**
 * PlayerStatsView - Renders player statistics tables
 * 
 * @since 2026-01-08
 */
class PlayerStatsView
{
    /**
     * Render player highs table with season and career statistics
     * 
     * @param PlayerStats $playerStats The player's statistics
     * @param int $allStarGames Number of All-Star Games
     * @param int $threePointContests Number of Three-Point Contests
     * @param int $dunkContests Number of Slam Dunk Competitions
     * @param int $rookieSophChallenges Number of Rookie-Sophomore Challenges
     * @return string HTML for player highs table
     */
    public static function renderPlayerHighsTable(
        PlayerStats $playerStats,
        int $allStarGames = 0,
        int $threePointContests = 0,
        int $dunkContests = 0,
        int $rookieSophChallenges = 0
    ): string
    {
        ob_start();
        ?>
<td rowspan="3" class="player-highs-container">
    <table class="player-highs-table">
        <tr>
            <td class="highs-main-header" colspan="3">PLAYER HIGHS</td>
        </tr>
        <tr>
            <td class="highs-section-header" colspan="3">Regular-Season</td>
        </tr>
        <tr>
            <th class="highs-column-header"></th>
            <th class="highs-column-header">Ssn</th>
            <th class="highs-column-header">Car</th>
        </tr>
        <?= self::renderStatRow('Points', $playerStats->seasonHighPoints, $playerStats->careerSeasonHighPoints) ?>
        <?= self::renderStatRow('Rebounds', $playerStats->seasonHighRebounds, $playerStats->careerSeasonHighRebounds) ?>
        <?= self::renderStatRow('Assists', $playerStats->seasonHighAssists, $playerStats->careerSeasonHighAssists) ?>
        <?= self::renderStatRow('Steals', $playerStats->seasonHighSteals, $playerStats->careerSeasonHighSteals) ?>
        <?= self::renderStatRow('Blocks', $playerStats->seasonHighBlocks, $playerStats->careerSeasonHighBlocks) ?>
        <?= self::renderStatRow('Double-Doubles', $playerStats->seasonDoubleDoubles, $playerStats->careerDoubleDoubles) ?>
        <?= self::renderStatRow('Triple-Doubles', $playerStats->seasonTripleDoubles, $playerStats->careerTripleDoubles) ?>
        <tr>
            <td class="highs-section-header" colspan="3">Playoffs</td>
        </tr>
        <tr>
            <th class="highs-column-header"></th>
            <th class="highs-column-header">Ssn</th>
            <th class="highs-column-header">Car</th>
        </tr>
        <?= self::renderStatRow('Points', $playerStats->seasonPlayoffHighPoints, $playerStats->careerPlayoffHighPoints) ?>
        <?= self::renderStatRow('Rebounds', $playerStats->seasonPlayoffHighRebounds, $playerStats->careerPlayoffHighRebounds) ?>
        <?= self::renderStatRow('Assists', $playerStats->seasonPlayoffHighAssists, $playerStats->careerPlayoffHighAssists) ?>
        <?= self::renderStatRow('Steals', $playerStats->seasonPlayoffHighSteals, $playerStats->careerPlayoffHighSteals) ?>
        <?= self::renderStatRow('Blocks', $playerStats->seasonPlayoffHighBlocks, $playerStats->careerPlayoffHighBlocks) ?>
        <?= self::renderStatRow('Double-Doubles', $playerStats->seasonPlayoffDoubleDoubles, $playerStats->careerPlayoffDoubleDoubles) ?>
        <?= self::renderStatRow('Triple-Doubles', $playerStats->seasonPlayoffTripleDoubles, $playerStats->careerPlayoffTripleDoubles) ?>
        <tr>
            <td class="highs-section-header" colspan="3">All-Star Weekend</td>
        </tr>
        <?= self::renderAllStarActivities($allStarGames, $threePointContests, $dunkContests, $rookieSophChallenges) ?>
    </table>
</td>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single stat row
     * 
     * @param string $label Stat label
     * @param int|null $seasonValue Season value
     * @param int|null $careerValue Career value
     * @return string HTML for stat row
     */
    private static function renderStatRow(string $label, ?int $seasonValue, ?int $careerValue): string
    {
        ob_start();
        
        $labelSafe = HtmlSanitizer::safeHtmlOutput($label);
        $seasonSafe = HtmlSanitizer::safeHtmlOutput((string)($seasonValue ?? 0));
        $careerSafe = HtmlSanitizer::safeHtmlOutput((string)($careerValue ?? 0));
        ?>
<tr>
    <td class="stat-label"><?= $labelSafe ?></td>
    <td class="stat-value"><?= $seasonSafe ?></td>
    <td class="stat-value"><?= $careerSafe ?></td>
</tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Render All-Star activities section
     * 
     * @param int $allStarGames Number of All-Star Games
     * @param int $threePointContests Number of Three-Point Contests
     * @param int $dunkContests Number of Slam Dunk Competitions
     * @param int $rookieSophChallenges Number of Rookie-Sophomore Challenges
     * @return string HTML for All-Star activities
     */
    private static function renderAllStarActivities(
        int $allStarGames,
        int $threePointContests,
        int $dunkContests,
        int $rookieSophChallenges
    ): string
    {
        ob_start();
        
        $asgSafe = HtmlSanitizer::safeHtmlOutput((string)$allStarGames);
        $threePtSafe = HtmlSanitizer::safeHtmlOutput((string)$threePointContests);
        $dunkSafe = HtmlSanitizer::safeHtmlOutput((string)$dunkContests);
        $rookieSafe = HtmlSanitizer::safeHtmlOutput((string)$rookieSophChallenges);
        ?>
<tr>
    <td class="stat-label">All-Star Games</td>
    <td class="stat-value" colspan="2"><?= $asgSafe ?></td>
</tr>
<tr>
    <td class="stat-label">Three-Point Contests</td>
    <td class="stat-value" colspan="2"><?= $threePtSafe ?></td>
</tr>
<tr>
    <td class="stat-label">Slam Dunk Competitions</td>
    <td class="stat-value" colspan="2"><?= $dunkSafe ?></td>
</tr>
<tr>
    <td class="stat-label">Rookie-Sophomore Challenges</td>
    <td class="stat-value" colspan="2"><?= $rookieSafe ?></td>
</tr>
        <?php
        return ob_get_clean();
    }
}
