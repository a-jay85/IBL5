<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Player;
use Utilities\HtmlSanitizer;

/**
 * PlayerTradingCardFrontView - Renders the front side of player trading card
 * 
 * Shows player ratings, intangibles, free agency preferences, and contract info.
 * Uses CardBaseStyles for shared styling with PlayerTradingCardBackView.
 * 
 * @see CardBaseStyles for shared CSS
 * @see PlayerTradingCardBackView for the back side of the card
 * @since 2026-01-08
 */
class PlayerTradingCardFrontView
{
    /**
     * Get scoped custom styles for trading card with team colors
     *
     * @deprecated CSS is now centralized in design/components/player-cards.css.
     *             Custom properties are set inline on the container element in render().
     *
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string}|null $colorScheme Optional color scheme from TeamColorHelper
     * @return string Empty string — styles are in centralized CSS
     */
    public static function getStyles(?array $colorScheme = null): string
    {
        return '';
    }

    /**
     * Render the complete trading card front
     * 
     * @param Player $player The player object
     * @param int $playerID The player's ID
     * @param string $contractDisplay Formatted contract string
     * @param \mysqli|null $db Optional database connection for team colors
     * @return string HTML for trading card front
     */
    public static function render(Player $player, int $playerID, string $contractDisplay, ?\mysqli $db = null): string
    {
        // Get color scheme and prepare player data
        $colorScheme = CardBaseStyles::getColorSchemeForTeam($db, $player->teamID ?? 0);
        $playerData = CardBaseStyles::preparePlayerData($player, $playerID);
        
        // Additional front-card specific data
        $expYears = (string) ($player->yearsOfExperience ?? 0);
        $birdYears = (string) ($player->birdYears ?? 0);
        /** @var string $contractSafe */
        $contractSafe = HtmlSanitizer::safeHtmlOutput($contractDisplay);

        $cssProps = CardBaseStyles::getCardCssProperties($colorScheme);

        ob_start();
        ?>
<div class="trading-card" style="<?= $cssProps ?>">
<?= CardBaseStyles::renderCardTop($playerData) ?>

    <!-- RATINGS SECTION -->
    <div>
        <h3 class="section-title">Player Ratings</h3>
        
        <!-- Row 1: Shooting (2ga 2gp fta ftp 3ga 3gp) -->
        <div class="rating-row shooting">
            <?= self::renderRatingCell('2ga', $player->ratingFieldGoalAttempts) ?>
            <?= self::renderRatingCell('2gp', $player->ratingFieldGoalPercentage) ?>
            <?= self::renderRatingCell('fta', $player->ratingFreeThrowAttempts) ?>
            <?= self::renderRatingCell('ftp', $player->ratingFreeThrowPercentage) ?>
            <?= self::renderRatingCell('3ga', $player->ratingThreePointAttempts) ?>
            <?= self::renderRatingCell('3gp', $player->ratingThreePointPercentage) ?>
        </div>

        <!-- Row 2: Rebounding/Defense (orb drb ast stl tvr blk foul) -->
        <div class="rating-row rebounding">
            <?= self::renderRatingCell('orb', $player->ratingOffensiveRebounds) ?>
            <?= self::renderRatingCell('drb', $player->ratingDefensiveRebounds) ?>
            <?= self::renderRatingCell('ast', $player->ratingAssists) ?>
            <?= self::renderRatingCell('stl', $player->ratingSteals) ?>
            <?= self::renderRatingCell('tvr', $player->ratingTurnovers) ?>
            <?= self::renderRatingCell('blk', $player->ratingBlocks) ?>
            <?= self::renderRatingCell('foul', $player->ratingFouls) ?>
        </div>

        <!-- Row 3: Offense/Defense (oo do po to od dd pd td) -->
        <div class="rating-row offense-defense">
            <?= self::renderRatingCell('oo', $player->ratingOutsideOffense) ?>
            <?= self::renderRatingCell('do', $player->ratingDriveOffense) ?>
            <?= self::renderRatingCell('po', $player->ratingPostOffense) ?>
            <?= self::renderRatingCell('to', $player->ratingTransitionOffense) ?>
            <div class="rating-cell"></div>
            <?= self::renderRatingCell('od', $player->ratingOutsideDefense) ?>
            <?= self::renderRatingCell('dd', $player->ratingDriveDefense) ?>
            <?= self::renderRatingCell('pd', $player->ratingPostDefense) ?>
            <?= self::renderRatingCell('td', $player->ratingTransitionDefense) ?>
        </div>
    </div>

    <!-- Intangibles Row -->
    <div class="pills-row">
        <?= self::renderPill('TAL', $player->ratingTalent) ?>
        <?= self::renderPill('SKL', $player->ratingSkill) ?>
        <?= self::renderPill('INT', $player->ratingIntangibles) ?>
        <?= self::renderPill('CLU', $player->ratingClutch) ?>
        <?= self::renderPill('CON', $player->ratingConsistency) ?>
    </div>

    <!-- Free Agency Preferences -->
    <div>
        <h3 class="section-title">Free Agency Preferences</h3>
        <div class="pills-row">
            <?= self::renderPill('LOY', $player->freeAgencyLoyalty, true) ?>
            <?= self::renderPill('WIN', $player->freeAgencyPlayForWinner, true) ?>
            <?= self::renderPill('PT', $player->freeAgencyPlayingTime, true) ?>
            <?= self::renderPill('SEC', $player->freeAgencySecurity, true) ?>
            <?= self::renderPill('TRD', $player->freeAgencyTradition, true) ?>
        </div>
    </div>

    <!-- Contract Info Footer -->
    <div class="contract-bar">
        <div class="contract-flex">
            <div>
                <span class="contract-label">Exp:Bird Years:</span>
                <span class="contract-value"><?= $expYears ?>:<?= $birdYears ?></span>
            </div>
            <div>
                <span class="contract-label">Contract:</span>
                <span class="contract-amount"><?= $contractSafe ?></span>
            </div>
        </div>
    </div>
</div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Render a single rating cell
     */
    private static function renderRatingCell(string $label, ?int $value): string
    {
        $safeValue = (string) ($value ?? 0);
        return <<<HTML
<div class="rating-cell">
    <div class="rating-label">{$label}</div>
    <div class="rating-value">{$safeValue}</div>
</div>
HTML;
    }

    /**
     * Render a stat pill
     */
    private static function renderPill(string $label, ?int $value, bool $isPreference = false): string
    {
        $safeValue = (string) ($value ?? 0);
        $class = $isPreference ? 'stat-pill preference' : 'stat-pill intangible';
        return <<<HTML
<div class="{$class}">
    <span class="pill-label">{$label}</span> 
    <span class="pill-value">{$safeValue}</span>
</div>
HTML;
    }
}
