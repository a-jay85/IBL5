<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Player;
use Utilities\HtmlSanitizer;

/**
 * PlayerBioView - Renders player biography and ratings information
 * 
 * @since 2026-01-08
 */
class PlayerBioView
{
    /**
     * Render player bio section with age, height, weight, college, draft info, and ratings
     * 
     * @param Player $player The player object
     * @param string $contractDisplay Formatted contract string
     * @return string HTML for bio section
     */
    public static function render(Player $player, string $contractDisplay): string
    {
        ob_start();
        
        $age = HtmlSanitizer::safeHtmlOutput((string)$player->age);
        $heightFeet = HtmlSanitizer::safeHtmlOutput((string)$player->heightFeet);
        $heightInches = HtmlSanitizer::safeHtmlOutput((string)$player->heightInches);
        $weight = HtmlSanitizer::safeHtmlOutput((string)$player->weightPounds);
        $college = HtmlSanitizer::safeHtmlOutput((string)($player->collegeName ?? ''));
        $draftTeam = HtmlSanitizer::safeHtmlOutput((string)($player->draftTeamOriginalName ?? ''));
        $draftPick = HtmlSanitizer::safeHtmlOutput((string)$player->draftPickNumber);
        $draftRound = HtmlSanitizer::safeHtmlOutput((string)$player->draftRound);
        $draftYear = HtmlSanitizer::safeHtmlOutput((string)$player->draftYear);
        $birdYears = HtmlSanitizer::safeHtmlOutput((string)$player->birdYears);
        $contractSafe = HtmlSanitizer::safeHtmlOutput($contractDisplay);
        ?>
<div class="player-bio">
    <div class="player-info">
        Age: <?= $age ?> | Height: <?= $heightFeet ?>-<?= $heightInches ?> | Weight: <?= $weight ?> | College: <?= $college ?>
    </div>
    <div class="player-draft-info">
        <em>Drafted by the <?= $draftTeam ?> with the # <?= $draftPick ?> pick of round <?= $draftRound ?> in the <a href="/ibl5/pages/draftHistory.php?year=<?= $player->draftYear ?>"><?= $draftYear ?> Draft</a></em>
    </div>
    <div class="player-ratings-container">
        <?= self::renderRatingsTable($player) ?>
    </div>
    <div class="player-contract-info">
        <strong>BIRD YEARS:</strong> <?= $birdYears ?> | <strong>Remaining Contract:</strong> <?= $contractSafe ?>
    </div>
</div>
            </td>
        </tr>
    </table>
</td>
        <?php
        return ob_get_clean();
    }

    /**
     * Render complete ratings table with headers and values
     * 
     * @param Player $player The player whose ratings to display
     * @return string HTML for ratings table
     */
    private static function renderRatingsTable(Player $player): string
    {
        ob_start();
        ?>
<table class="player-ratings-table">
    <?= self::renderRatingsTableHeaders() ?>
    <?= self::renderRatingsTableValues($player) ?>
</table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render ratings table headers
     * 
     * @return string HTML for ratings table headers
     */
    private static function renderRatingsTableHeaders(): string
    {
        $headers = [
            '2ga', '2gp', 'fta', 'ftp', '3ga', '3gp',
            'orb', 'drb', 'ast', 'stl', 'tvr', 'blk', 'foul',
            'oo', 'do', 'po', 'to', 'od', 'dd', 'pd', 'td'
        ];
        
        ob_start();
        ?>
<tr class="ratings-header-row">
    <?php foreach ($headers as $header): ?>
    <th><?= HtmlSanitizer::safeHtmlOutput($header) ?></th>
    <?php endforeach; ?>
</tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Render ratings table values
     * 
     * @param Player $player The player whose ratings to display
     * @return string HTML for ratings table values
     */
    private static function renderRatingsTableValues(Player $player): string
    {
        $ratings = [
            $player->ratingFieldGoalAttempts,
            $player->ratingFieldGoalPercentage,
            $player->ratingFreeThrowAttempts,
            $player->ratingFreeThrowPercentage,
            $player->ratingThreePointAttempts,
            $player->ratingThreePointPercentage,
            $player->ratingOffensiveRebounds,
            $player->ratingDefensiveRebounds,
            $player->ratingAssists,
            $player->ratingSteals,
            $player->ratingTurnovers,
            $player->ratingBlocks,
            $player->ratingFouls,
            $player->ratingOutsideOffense,
            $player->ratingDriveOffense,
            $player->ratingPostOffense,
            $player->ratingTransitionOffense,
            $player->ratingOutsideDefense,
            $player->ratingDriveDefense,
            $player->ratingPostDefense,
            $player->ratingTransitionDefense
        ];
        
        ob_start();
        ?>
<tr class="ratings-values-row">
    <?php foreach ($ratings as $rating): ?>
    <td><?= HtmlSanitizer::safeHtmlOutput((string)$rating) ?></td>
    <?php endforeach; ?>
</tr>
        <?php
        return ob_get_clean();
    }
}
