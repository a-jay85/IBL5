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
        $colorScheme = CardBaseStyles::getColorSchemeForTeam($db, $player->teamid ?? 0);
        $playerData = CardBaseStyles::preparePlayerData($player, $playerID);

        $cssProps = CardBaseStyles::getCardCssProperties($colorScheme);
        $cardTop = CardBaseStyles::renderCardTop($playerData);

        // Render rating cells inline
        $rc2ga  = self::renderRatingCell('2ga', $player->ratingFieldGoalAttempts);
        $rc2gp  = self::renderRatingCell('2gp', $player->ratingFieldGoalPercentage);
        $rcFta  = self::renderRatingCell('fta', $player->ratingFreeThrowAttempts);
        $rcFtp  = self::renderRatingCell('ftp', $player->ratingFreeThrowPercentage);
        $rc3ga  = self::renderRatingCell('3ga', $player->ratingThreePointAttempts);
        $rc3gp  = self::renderRatingCell('3gp', $player->ratingThreePointPercentage);
        $rcOrb  = self::renderRatingCell('orb', $player->ratingOffensiveRebounds);
        $rcDrb  = self::renderRatingCell('drb', $player->ratingDefensiveRebounds);
        $rcAst  = self::renderRatingCell('ast', $player->ratingAssists);
        $rcStl  = self::renderRatingCell('stl', $player->ratingSteals);
        $rcTvr  = self::renderRatingCell('tvr', $player->ratingTurnovers);
        $rcBlk  = self::renderRatingCell('blk', $player->ratingBlocks);
        $rcFoul = self::renderRatingCell('foul', $player->ratingFouls);
        $rcOo   = self::renderRatingCell('oo', $player->ratingOutsideOffense);
        $rcDo   = self::renderRatingCell('do', $player->ratingDriveOffense);
        $rcPo   = self::renderRatingCell('po', $player->ratingPostOffense);
        $rcTo   = self::renderRatingCell('to', $player->ratingTransitionOffense);
        $rcOd   = self::renderRatingCell('od', $player->ratingOutsideDefense);
        $rcDd   = self::renderRatingCell('dd', $player->ratingDriveDefense);
        $rcPd   = self::renderRatingCell('pd', $player->ratingPostDefense);
        $rcTd   = self::renderRatingCell('td', $player->ratingTransitionDefense);

        // Render pills inline
        $pTal = self::renderPill('TAL', $player->ratingTalent);
        $pSkl = self::renderPill('SKL', $player->ratingSkill);
        $pInt = self::renderPill('INT', $player->ratingIntangibles);
        $pClu = self::renderPill('CLU', $player->ratingClutch);
        $pCon = self::renderPill('CON', $player->ratingConsistency);
        $pLoy = self::renderPill('LOY', $player->freeAgencyLoyalty, true);
        $pWin = self::renderPill('WIN', $player->freeAgencyPlayForWinner, true);
        $pPt  = self::renderPill('PT', $player->freeAgencyPlayingTime, true);
        $pSec = self::renderPill('SEC', $player->freeAgencySecurity, true);
        $pTrd = self::renderPill('TRD', $player->freeAgencyTradition, true);

        // Contract data
        $expYears = $player->yearsOfExperience ?? 0;
        $birdYears = $player->birdYears ?? 0;
        $contractSafe = HtmlSanitizer::e($contractDisplay);

        return '<div class="trading-card" style="' . $cssProps . '">'
            . "\n" . $cardTop
            . "\n\n    <!-- RATINGS SECTION -->"
            . "\n    <div>"
            . "\n        <h3 class=\"section-title\">Player Ratings</h3>"
            . "\n        <!-- Row 1: Shooting (2ga 2gp fta ftp 3ga 3gp) -->"
            . "\n        <div class=\"rating-row shooting\">"
            . "\n            " . $rc2ga
            . "\n            " . $rc2gp
            . "\n            " . $rcFta
            . "\n            " . $rcFtp
            . "\n            " . $rc3ga
            . "\n            " . $rc3gp
            . "\n        </div>"
            . "\n\n        <!-- Row 2: Rebounding/Defense (orb drb ast stl tvr blk foul) -->"
            . "\n        <div class=\"rating-row rebounding\">"
            . "\n            " . $rcOrb
            . "\n            " . $rcDrb
            . "\n            " . $rcAst
            . "\n            " . $rcStl
            . "\n            " . $rcTvr
            . "\n            " . $rcBlk
            . "\n            " . $rcFoul
            . "\n        </div>"
            . "\n\n        <!-- Row 3: Offense/Defense (oo do po to od dd pd td) -->"
            . "\n        <div class=\"rating-row offense-defense\">"
            . "\n            " . $rcOo
            . "\n            " . $rcDo
            . "\n            " . $rcPo
            . "\n            " . $rcTo
            . "\n            <div class=\"rating-cell\"></div>"
            . "\n            " . $rcOd
            . "\n            " . $rcDd
            . "\n            " . $rcPd
            . "\n            " . $rcTd
            . "\n        </div>"
            . "\n    </div>"
            . "\n\n    <!-- Intangibles Row -->"
            . "\n    <div class=\"pills-row\">"
            . "\n        " . $pTal
            . "\n        " . $pSkl
            . "\n        " . $pInt
            . "\n        " . $pClu
            . "\n        " . $pCon
            . "\n    </div>"
            . "\n\n    <!-- Free Agency Preferences -->"
            . "\n    <div>"
            . "\n        <h3 class=\"section-title\">Free Agency Preferences</h3>"
            . "\n        <div class=\"pills-row\">"
            . "\n            " . $pLoy
            . "\n            " . $pWin
            . "\n            " . $pPt
            . "\n            " . $pSec
            . "\n            " . $pTrd
            . "\n        </div>"
            . "\n    </div>"
            . "\n\n    <!-- Contract Info Footer -->"
            . "\n    <div class=\"contract-bar\">"
            . "\n        <div class=\"contract-flex\">"
            . "\n            <div>"
            . "\n                <span class=\"contract-label\">Exp:Bird Years:</span>"
            . "\n                <span class=\"contract-value\">" . $expYears . ':' . $birdYears . '</span>'
            . "\n            </div>"
            . "\n            <div>"
            . "\n                <span class=\"contract-label\">Contract:</span>"
            . "\n                <span class=\"contract-amount\">" . $contractSafe . '</span>'
            . "\n            </div>"
            . "\n        </div>"
            . "\n    </div>"
            . "\n</div>";
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
