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
<table class="player-highs-table">
    <tr>
        <td class="highs-main-header" colspan="5">PLAYER HIGHS</td>
    </tr>
    <tr>
        <td class="highs-section-header"></td>
        <td class="highs-section-header" colspan="2">Regular Season</td>
        <td class="highs-section-header" colspan="2">Playoffs</td>
    </tr>
    <tr>
        <th class="highs-column-header"></th>
        <th class="highs-column-header">Ssn</th>
        <th class="highs-column-header">Car</th>
        <th class="highs-column-header">Ssn</th>
        <th class="highs-column-header">Car</th>
    </tr>
    <tr>
        <td class="stat-label">Points</td>
        <?= self::renderStatRow($playerStats->seasonHighPoints, $playerStats->careerSeasonHighPoints) ?>
        <?= self::renderStatRow($playerStats->seasonPlayoffHighPoints, $playerStats->careerPlayoffHighPoints) ?>
    </tr>
    <tr>
        <td class="stat-label">Rebounds</td>
        <?= self::renderStatRow($playerStats->seasonHighRebounds, $playerStats->careerSeasonHighRebounds) ?>
        <?= self::renderStatRow($playerStats->seasonPlayoffHighRebounds, $playerStats->careerPlayoffHighRebounds) ?>
    </tr>
    <tr>
        <td class="stat-label">Assists</td>
        <?= self::renderStatRow($playerStats->seasonHighAssists, $playerStats->careerSeasonHighAssists) ?>
        <?= self::renderStatRow($playerStats->seasonPlayoffHighAssists, $playerStats->careerPlayoffHighAssists) ?>
    </tr>
    <tr>
        <td class="stat-label">Steals</td>
        <?= self::renderStatRow($playerStats->seasonHighSteals, $playerStats->careerSeasonHighSteals) ?>
        <?= self::renderStatRow($playerStats->seasonPlayoffHighSteals, $playerStats->careerPlayoffHighSteals) ?>
    </tr>
    <tr>
        <td class="stat-label">Blocks</td>
        <?= self::renderStatRow($playerStats->seasonHighBlocks, $playerStats->careerSeasonHighBlocks) ?>
        <?= self::renderStatRow($playerStats->seasonPlayoffHighBlocks, $playerStats->careerPlayoffHighBlocks) ?>
    </tr>
    <tr>
        <td class="stat-label">Double Doubles</td>
        <?= self::renderStatRow($playerStats->seasonDoubleDoubles, $playerStats->careerDoubleDoubles) ?>
        <?= self::renderStatRow($playerStats->seasonPlayoffDoubleDoubles, $playerStats->careerPlayoffDoubleDoubles) ?>
    </tr>
    <tr>
        <td class="stat-label">Triple Doubles</td>
        <?= self::renderStatRow($playerStats->seasonTripleDoubles, $playerStats->careerTripleDoubles) ?>
        <?= self::renderStatRow($playerStats->seasonPlayoffTripleDoubles, $playerStats->careerPlayoffTripleDoubles) ?>
    </tr>
    <tr>
        <td class="highs-section-header" colspan="5">All-Star Weekend</td>
    </tr>
    <?= self::renderAllStarActivities($allStarGames, $threePointContests, $dunkContests, $rookieSophChallenges) ?>
</table>
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
    private static function renderStatRow(?int $seasonValue, ?int $careerValue): string
    {
        ob_start();
        
        $seasonSafe = HtmlSanitizer::safeHtmlOutput((string)($seasonValue ?? 0));
        $careerSafe = HtmlSanitizer::safeHtmlOutput((string)($careerValue ?? 0));
        ?>
    <td class="stat-value"><?= $seasonSafe ?></td>
    <td class="stat-value"><?= $careerSafe ?></td>
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
    <td class="stat-value" colspan="4"><?= $asgSafe ?></td>
</tr>
<tr>
    <td class="stat-label">Three-Point Contests</td>
    <td class="stat-value" colspan="4"><?= $threePtSafe ?></td>
</tr>
<tr>
    <td class="stat-label">Slam Dunk Competitions</td>
    <td class="stat-value" colspan="4"><?= $dunkSafe ?></td>
</tr>
<tr>
    <td class="stat-label">Rookie-Soph Challenges</td>
    <td class="stat-value" colspan="4"><?= $rookieSafe ?></td>
</tr>
        <?php
        return ob_get_clean();
    }
}
