<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Player;
use Player\PlayerStats;

/**
 * PlayerTradingCardFlipView - Wrapper for flippable trading card
 * 
 * Renders both front and back of the trading card with flip animation.
 * Clicking the flip icon in the bottom-right corner flips the card.
 * 
 * @since 2026-01-08
 */
class PlayerTradingCardFlipView
{
    /**
     * Get styles and scripts for flip functionality
     * 
     * @return string HTML with CSS and JavaScript
     */
    public static function getFlipStyles(): string
    {
        return <<<'HTML'
<style>
/* Trading Card Flip Container */
.card-flip-container {
    perspective: 1000px;
    max-width: 420px;
    margin: 0 auto;
    position: relative;
}

.card-flip-inner {
    position: relative;
    width: 100%;
    transition: transform 0.6s;
    transform-style: preserve-3d;
}

.card-flip-container.flipped .card-flip-inner {
    transform: rotateY(180deg);
}

.card-front,
.card-back {
    width: 100%;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
    position: relative;
}

.card-back {
    position: absolute;
    top: 0;
    left: 0;
    transform: rotateY(180deg);
}

/* Flip Icon Indicator */
.flip-icon {
    position: absolute;
    bottom: 12px;
    right: 12px;
    width: 32px;
    height: 32px;
    background: rgba(212, 175, 55, 0.9);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.flip-icon:hover {
    background: rgba(212, 175, 55, 1);
    transform: scale(1.1) rotate(180deg);
    box-shadow: 0 4px 12px rgba(212, 175, 55, 0.5);
}

.flip-icon svg {
    width: 20px;
    height: 20px;
    fill: #0f1419;
}

/* Tooltip */
.flip-icon::before {
    content: 'Click to flip';
    position: absolute;
    bottom: 100%;
    right: 0;
    margin-bottom: 8px;
    padding: 4px 8px;
    background: rgba(0, 0, 0, 0.8);
    color: #D4AF37;
    font-size: 11px;
    white-space: nowrap;
    border-radius: 4px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s, transform 0.3s;
}

.flip-icon:hover::before {
    opacity: 1;
    transform: rotate(-180deg);
}

/* Pulse animation for first-time users */
@keyframes pulse-glow {
    0%, 100% {
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    50% {
        box-shadow: 0 2px 16px rgba(212, 175, 55, 0.6);
    }
}

.flip-icon.pulse {
    animation: pulse-glow 2s ease-in-out infinite;
}

/* Mobile adjustments */
@media (max-width: 480px) {
    .flip-icon {
        width: 28px;
        height: 28px;
        bottom: 8px;
        right: 8px;
    }
    
    .flip-icon svg {
        width: 16px;
        height: 16px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const flipContainers = document.querySelectorAll('.card-flip-container');
    
    flipContainers.forEach(function(container) {
        // Select ALL flip icons (both front and back)
        const flipIcons = container.querySelectorAll('.flip-icon');
        
        flipIcons.forEach(function(flipIcon) {
            flipIcon.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                container.classList.toggle('flipped');
                
                // Remove pulse animation from all flip icons after first flip
                flipIcons.forEach(function(icon) {
                    icon.classList.remove('pulse');
                });
            });
        });
        
        // Add pulse for first 5 seconds to draw attention (only to front icon)
        if (flipIcons.length > 0) {
            setTimeout(function() {
                flipIcons.forEach(function(icon) {
                    icon.classList.remove('pulse');
                });
            }, 5000);
        }
    });
});
</script>
HTML;
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
        int $rookieSophChallenges = 0
    ): string {
        ob_start();
        ?>
<div class="card-flip-container">
    <div class="card-flip-inner">
        <!-- Front of Card -->
        <div class="card-front">
            <?= PlayerTradingCardFrontView::render($player, $playerID, $contractDisplay) ?>
            
            <!-- Flip Icon -->
            <div class="flip-icon pulse" title="Click to see stats">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/>
                </svg>
            </div>
        </div>
        
        <!-- Back of Card -->
        <div class="card-back">
            <?= PlayerTradingCardBackView::render(
                $player,
                $playerStats,
                $playerID,
                $allStarGames,
                $threePointContests,
                $dunkContests,
                $rookieSophChallenges
            ) ?>
            
            <!-- Flip Icon (back side) -->
            <div class="flip-icon" title="Click to see ratings">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/>
                </svg>
            </div>
        </div>
    </div>
</div>
        <?php
        return ob_get_clean();
    }
}
