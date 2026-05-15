<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Player;
use Player\PlayerStats;

/**
 * PlayerTradingCardFlipView - Wrapper for flippable trading card
 * 
 * Renders both front and back of the trading card with flip animation.
 * Uses CardFlipStyles for shared flip animation CSS/JS.
 * 
 * @see CardFlipStyles for shared flip animation
 * @see PlayerTradingCardFrontView for front card content
 * @see PlayerTradingCardBackView for back card content
 * @since 2026-01-08
 */
class PlayerTradingCardFlipView
{
    /**
     * Get scripts for flip functionality
     *
     * CSS is now centralized in design/components/player-cards.css.
     * Only the JavaScript for flip interaction is returned.
     *
     * @return string HTML script tag with JavaScript
     */
    public static function getFlipStyles(): string
    {
        return CardFlipStyles::getTradingCardFlipStyles();
    }

    /**
     * Render the complete flippable trading card
     * 
     * @param Player $player The player object
     * @param PlayerStats $playerStats The player's statistics
     * @param int $playerID The player's ID
     * @param string $contractDisplay Formatted contract string
     * @param int $allStarGames Number of All-Star Games
     * @param int $threePointContests Number of Three-Point Contests
     * @param int $dunkContests Number of Slam Dunk Competitions
     * @param int $rookieSophChallenges Number of Rookie-Sophomore Challenges
     * @param \mysqli|null $db Optional database connection for team colors
     * @return string HTML for flippable trading card
     */
    public static function render(
        Player $player,
        PlayerStats $playerStats,
        int $playerID,
        string $contractDisplay,
        int $allStarGames = 0,
        int $threePointContests = 0,
        int $dunkContests = 0,
        int $rookieSophChallenges = 0,
        ?\mysqli $db = null
    ): string {
        $flipIcon = CardFlipStyles::getFlipIcon();
        $frontHtml = PlayerTradingCardFrontView::render($player, $playerID, $contractDisplay, $db);
        $backHtml = PlayerTradingCardBackView::render(
            $player, $playerStats, $playerID,
            $allStarGames, $threePointContests, $dunkContests, $rookieSophChallenges, $db
        );

        return '<div class="card-flip-container">'
            . '<div class="card-flip-inner">'
            . '<div class="card-front">'
            . $frontHtml
            . '<div class="flip-icon pulse" title="Click to see stats">' . $flipIcon . '</div>'
            . '</div>'
            . '<div class="card-back">'
            . $backHtml
            . '<div class="flip-icon" title="Click to see ratings">' . $flipIcon . '</div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }
}
