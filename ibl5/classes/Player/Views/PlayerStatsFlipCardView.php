<?php

declare(strict_types=1);

namespace Player\Views;

/**
 * PlayerStatsFlipCardView - Flippable stats card for Averages/Totals pairs
 * 
 * Provides a flip animation to toggle between Averages and Totals views
 * for statistics that have both view types (Regular Season, Playoffs, 
 * Olympics, and H.E.A.T.).
 * 
 * Similar to PlayerTradingCardFlipView but optimized for horizontal stats tables.
 * 
 * @since 2026-01-08
 */
class PlayerStatsFlipCardView
{
    /**
     * Stats types that support flip functionality
     */
    public const FLIP_SUPPORTED_TYPES = [
        'regular-season',
        'playoffs',
        'olympics',
        'heat',
    ];

    /**
     * Get styles and scripts for stats flip functionality
     * 
     * Uses separate class names to avoid conflicts with PlayerTradingCardFlipView
     * 
     * @param array|null $colorScheme Optional color scheme from TeamColorHelper
     * @return string HTML with CSS and JavaScript
     */
    public static function getFlipStyles(?array $colorScheme = null): string
    {
        // Use default colors if no scheme provided
        if ($colorScheme === null) {
            $colorScheme = TeamColorHelper::getDefaultColorScheme();
        }
        
        $border = $colorScheme['border'];
        $borderRgb = $colorScheme['border_rgb'];
        $accent = $colorScheme['accent'];
        $gradMid = $colorScheme['gradient_mid'];
        
        return <<<HTML
<style>
/* Stats Card Flip Container - Horizontal Layout */
.stats-flip-container {
    perspective: 2000px;
    margin: 16px auto;
    position: relative;
    min-height: 200px;
}

.stats-flip-inner {
    position: relative;
    width: 100%;
    transition: transform 0.6s ease-in-out;
    transform-style: preserve-3d;
}

.stats-flip-container.flipped .stats-flip-inner {
    transform: rotateX(180deg);
}

.stats-front,
.stats-back {
    width: 100%;
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}

.stats-front {
    position: relative;
}

.stats-back {
    position: absolute;
    top: 0;
    left: 0;
    transform: rotateX(180deg);
}

/* Stats Flip Toggle Button */
.stats-flip-toggle {
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba({$borderRgb}, 0.95);
    color: #{$gradMid};
    font-size: 11px;
    font-weight: 700;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    z-index: 20;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    border: none;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stats-flip-toggle:hover {
    background: rgba({$borderRgb}, 1);
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba({$borderRgb}, 0.5);
}

.stats-flip-toggle svg {
    width: 14px;
    height: 14px;
    fill: #{$gradMid};
    transition: transform 0.3s ease;
}

.stats-flip-toggle:hover svg {
    transform: rotate(180deg);
}

.stats-flip-toggle .toggle-label {
    display: inline-block;
}

/* Active state indicator */
.stats-flip-container.flipped .stats-flip-toggle .toggle-label::before {
    content: 'View ';
}

.stats-flip-container:not(.flipped) .stats-flip-toggle .toggle-label::before {
    content: 'View ';
}

/* Stats Type Label in Card Header */
.stats-view-label {
    position: absolute;
    top: 8px;
    left: 8px;
    background: rgba(0, 0, 0, 0.6);
    color: #{$accent};
    font-size: 10px;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    z-index: 15;
}

/* Tooltip */
.stats-flip-toggle::after {
    content: 'Click to toggle view';
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    padding: 4px 8px;
    background: rgba(0, 0, 0, 0.9);
    color: #{$accent};
    font-size: 10px;
    font-weight: 400;
    text-transform: none;
    white-space: nowrap;
    border-radius: 4px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s;
}

.stats-flip-toggle:hover::after {
    opacity: 1;
}

/* Pulse animation for first-time users */
@keyframes stats-pulse-glow {
    0%, 100% {
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    }
    50% {
        box-shadow: 0 2px 16px rgba({$borderRgb}, 0.7);
    }
}

.stats-flip-toggle.pulse {
    animation: stats-pulse-glow 2s ease-in-out infinite;
}

/* Mobile adjustments */
@media (max-width: 768px) {
    .stats-flip-toggle {
        font-size: 10px;
        padding: 4px 8px;
        top: 4px;
        right: 4px;
    }
    
    .stats-flip-toggle svg {
        width: 12px;
        height: 12px;
    }
    
    .stats-view-label {
        font-size: 9px;
        padding: 3px 6px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statsFlipContainers = document.querySelectorAll('.stats-flip-container');
    
    statsFlipContainers.forEach(function(container) {
        const flipToggles = container.querySelectorAll('.stats-flip-toggle');
        
        flipToggles.forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                container.classList.toggle('flipped');
                
                // Update toggle label based on state
                const isFlipped = container.classList.contains('flipped');
                flipToggles.forEach(function(t) {
                    const label = t.querySelector('.toggle-label');
                    if (label) {
                        // Toggle between Averages and Totals display
                        const currentText = label.textContent;
                        if (currentText.includes('Totals')) {
                            label.textContent = 'Averages';
                        } else {
                            label.textContent = 'Totals';
                        }
                    }
                    t.classList.remove('pulse');
                });
            });
        });
        
        // Add pulse for first 5 seconds to draw attention
        if (flipToggles.length > 0) {
            flipToggles[0].classList.add('pulse');
            setTimeout(function() {
                flipToggles.forEach(function(t) {
                    t.classList.remove('pulse');
                });
            }, 5000);
        }
    });
});
</script>
HTML;
    }

    /**
     * Get the flip icon SVG
     * 
     * @return string SVG icon HTML
     */
    private static function getFlipIcon(): string
    {
        return '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/></svg>';
    }

    /**
     * Render a flippable stats card with Averages/Totals toggle
     * 
     * @param string $averagesHtml HTML content for the averages view
     * @param string $totalsHtml HTML content for the totals view
     * @param string $statsCategory Category name (e.g., "Regular Season", "Playoffs")
     * @param bool $showAveragesFirst Whether to show averages first (default: true)
     * @param array|null $colorScheme Optional color scheme from TeamColorHelper
     * @return string Complete HTML for flippable stats card
     */
    public static function render(
        string $averagesHtml,
        string $totalsHtml,
        string $statsCategory = '',
        bool $showAveragesFirst = true,
        ?array $colorScheme = null
    ): string {
        // Style tables using PlayerStatsCardView (don't wrap yet)
        $styledAverages = PlayerStatsCardView::styleTable($averagesHtml);
        $styledTotals = PlayerStatsCardView::styleTable($totalsHtml);
        
        // Determine which content goes on front and back
        $frontContent = $showAveragesFirst ? $styledAverages : $styledTotals;
        $backContent = $showAveragesFirst ? $styledTotals : $styledAverages;
        $frontLabel = $showAveragesFirst ? 'Averages' : 'Totals';
        $backLabel = $showAveragesFirst ? 'Totals' : 'Averages';
        $toggleTarget = $showAveragesFirst ? 'Totals' : 'Averages';
        
        $flipIcon = self::getFlipIcon();
        $escapedCategory = htmlspecialchars($statsCategory, ENT_QUOTES, 'UTF-8');
        
        ob_start();
        ?>
<div class="stats-flip-container" data-category="<?= $escapedCategory ?>">
    <div class="stats-flip-inner">
        <!-- Front (Averages by default) -->
        <div class="stats-front">
            <div class="player-stats-card">
                <span class="stats-view-label"><?= $frontLabel ?></span>
                <button class="stats-flip-toggle pulse" title="Switch to <?= $toggleTarget ?>">
                    <?= $flipIcon ?>
                    <span class="toggle-label"><?= $toggleTarget ?></span>
                </button>
                <?= $frontContent ?>
            </div>
        </div>
        
        <!-- Back (Totals by default) -->
        <div class="stats-back">
            <div class="player-stats-card">
                <span class="stats-view-label"><?= $backLabel ?></span>
                <button class="stats-flip-toggle" title="Switch to <?= $frontLabel ?>">
                    <?= $flipIcon ?>
                    <span class="toggle-label"><?= $frontLabel ?></span>
                </button>
                <?= $backContent ?>
            </div>
        </div>
    </div>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Regular Season stats with flip between Averages and Totals
     * 
     * @param PlayerRegularSeasonAveragesView $averagesView The averages view instance
     * @param PlayerRegularSeasonTotalsView $totalsView The totals view instance
     * @param int $playerID The player's ID
     * @param \mysqli|null $db Optional database connection for team colors
     * @param int $teamID Optional team ID for color lookup
     * @return string HTML for flippable regular season stats card
     */
    public static function renderRegularSeason(
        PlayerRegularSeasonAveragesView $averagesView,
        PlayerRegularSeasonTotalsView $totalsView,
        int $playerID,
        ?\mysqli $db = null,
        int $teamID = 0
    ): string {
        $averagesHtml = $averagesView->renderAverages($playerID);
        $totalsHtml = $totalsView->renderTotals($playerID);
        
        // Fetch team colors if database connection and team ID provided
        $colorScheme = null;
        if ($db !== null && $teamID > 0) {
            $teamColors = TeamColorHelper::getTeamColors($db, $teamID);
            $colorScheme = TeamColorHelper::generateColorScheme($teamColors['color1'], $teamColors['color2']);
        }
        
        return self::render($averagesHtml, $totalsHtml, 'Regular Season', true, $colorScheme);
    }

    /**
     * Render Playoff stats with flip between Averages and Totals
     * 
     * @param PlayerPlayoffAveragesView $averagesView The averages view instance
     * @param PlayerPlayoffTotalsView $totalsView The totals view instance
     * @param string $playerName The player's name
     * @param \mysqli|null $db Optional database connection for team colors
     * @param int $teamID Optional team ID for color lookup
     * @return string HTML for flippable playoff stats card
     */
    public static function renderPlayoffs(
        PlayerPlayoffAveragesView $averagesView,
        PlayerPlayoffTotalsView $totalsView,
        string $playerName,
        ?\mysqli $db = null,
        int $teamID = 0
    ): string {
        $averagesHtml = $averagesView->renderAverages($playerName);
        $totalsHtml = $totalsView->renderTotals($playerName);
        
        // Fetch team colors if database connection and team ID provided
        $colorScheme = null;
        if ($db !== null && $teamID > 0) {
            $teamColors = TeamColorHelper::getTeamColors($db, $teamID);
            $colorScheme = TeamColorHelper::generateColorScheme($teamColors['color1'], $teamColors['color2']);
        }
        
        return self::render($averagesHtml, $totalsHtml, 'Playoffs', true, $colorScheme);
    }

    /**
     * Render Olympics stats with flip between Averages and Totals
     * 
     * @param PlayerOlympicAveragesView $averagesView The averages view instance
     * @param PlayerOlympicTotalsView $totalsView The totals view instance
     * @param string $playerName The player's name
     * @param \mysqli|null $db Optional database connection for team colors
     * @param int $teamID Optional team ID for color lookup
     * @return string HTML for flippable Olympics stats card
     */
    public static function renderOlympics(
        PlayerOlympicAveragesView $averagesView,
        PlayerOlympicTotalsView $totalsView,
        string $playerName,
        ?\mysqli $db = null,
        int $teamID = 0
    ): string {
        $averagesHtml = $averagesView->renderAverages($playerName);
        $totalsHtml = $totalsView->renderTotals($playerName);
        
        // Fetch team colors if database connection and team ID provided
        $colorScheme = null;
        if ($db !== null && $teamID > 0) {
            $teamColors = TeamColorHelper::getTeamColors($db, $teamID);
            $colorScheme = TeamColorHelper::generateColorScheme($teamColors['color1'], $teamColors['color2']);
        }
        
        return self::render($averagesHtml, $totalsHtml, 'Olympics', true, $colorScheme);
    }

    /**
     * Render H.E.A.T. stats with flip between Averages and Totals
     * 
     * @param PlayerHeatAveragesView $averagesView The averages view instance
     * @param PlayerHeatTotalsView $totalsView The totals view instance
     * @param string $playerName The player's name
     * @param \mysqli|null $db Optional database connection for team colors
     * @param int $teamID Optional team ID for color lookup
     * @return string HTML for flippable H.E.A.T. stats card
     */
    public static function renderHeat(
        PlayerHeatAveragesView $averagesView,
        PlayerHeatTotalsView $totalsView,
        string $playerName,
        ?\mysqli $db = null,
        int $teamID = 0
    ): string {
        $averagesHtml = $averagesView->renderAverages($playerName);
        $totalsHtml = $totalsView->renderTotals($playerName);
        
        // Fetch team colors if database connection and team ID provided
        $colorScheme = null;
        if ($db !== null && $teamID > 0) {
            $teamColors = TeamColorHelper::getTeamColors($db, $teamID);
            $colorScheme = TeamColorHelper::generateColorScheme($teamColors['color1'], $teamColors['color2']);
        }
        
        return self::render($averagesHtml, $totalsHtml, 'H.E.A.T.', true, $colorScheme);
    }
}
