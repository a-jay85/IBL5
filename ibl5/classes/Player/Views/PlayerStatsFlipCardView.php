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
 * Uses CardFlipStyles for shared flip animation CSS/JS.
 * 
 * @see CardFlipStyles for shared flip animation
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
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string}|null $colorScheme Optional color scheme from TeamColorHelper
     * @return string HTML with CSS and JavaScript
     */
    public static function getFlipStyles(?array $colorScheme = null): string
    {
        if ($colorScheme === null) {
            $colorScheme = TeamColorHelper::getDefaultColorScheme();
        }
        
        return CardFlipStyles::getStatsCardFlipStyles($colorScheme);
    }

    /**
     * Render a flippable stats card with Averages/Totals toggle
     * 
     * @param string $averagesHtml HTML content for the averages view
     * @param string $totalsHtml HTML content for the totals view
     * @param string $statsCategory Category name (e.g., "Regular Season", "Playoffs")
     * @param bool $showAveragesFirst Whether to show averages first (default: true)
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string}|null $colorScheme Optional color scheme from TeamColorHelper
     * @return string Complete HTML for flippable stats card
     */
    public static function render(
        string $averagesHtml,
        string $totalsHtml,
        string $statsCategory = '',
        bool $showAveragesFirst = true,
        ?array $colorScheme = null
    ): string {
        // Style tables using PlayerStatsCardView
        $styledAverages = PlayerStatsCardView::styleTable($averagesHtml);
        $styledTotals = PlayerStatsCardView::styleTable($totalsHtml);
        
        // Determine which content goes on front and back
        $frontContent = $showAveragesFirst ? $styledAverages : $styledTotals;
        $backContent = $showAveragesFirst ? $styledTotals : $styledAverages;
        $frontLabel = $showAveragesFirst ? 'Averages' : 'Totals';
        $backLabel = $showAveragesFirst ? 'Totals' : 'Averages';
        $toggleTarget = $showAveragesFirst ? 'Totals' : 'Averages';
        
        $flipIcon = CardFlipStyles::getFlipIcon();
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
        return (string) ob_get_clean();
    }

    /**
     * Render Regular Season stats with flip between Averages and Totals
     */
    public static function renderRegularSeason(
        PlayerRegularSeasonAveragesView $averagesView,
        PlayerRegularSeasonTotalsView $totalsView,
        int $playerID,
        ?\mysqli $db = null,
        int $teamID = 0
    ): string {
        $colorScheme = CardBaseStyles::getColorSchemeForTeam($db, $teamID);
        return self::render(
            $averagesView->renderAverages($playerID),
            $totalsView->renderTotals($playerID),
            'Regular Season',
            true,
            $colorScheme
        );
    }

    /**
     * Render Playoff stats with flip between Averages and Totals
     */
    public static function renderPlayoffs(
        PlayerPlayoffAveragesView $averagesView,
        PlayerPlayoffTotalsView $totalsView,
        string $playerName,
        ?\mysqli $db = null,
        int $teamID = 0
    ): string {
        $colorScheme = CardBaseStyles::getColorSchemeForTeam($db, $teamID);
        return self::render(
            $averagesView->renderAverages($playerName),
            $totalsView->renderTotals($playerName),
            'Playoffs',
            true,
            $colorScheme
        );
    }

    /**
     * Render Olympics stats with flip between Averages and Totals
     */
    public static function renderOlympics(
        PlayerOlympicAveragesView $averagesView,
        PlayerOlympicTotalsView $totalsView,
        string $playerName,
        ?\mysqli $db = null,
        int $teamID = 0
    ): string {
        $colorScheme = CardBaseStyles::getColorSchemeForTeam($db, $teamID);
        return self::render(
            $averagesView->renderAverages($playerName),
            $totalsView->renderTotals($playerName),
            'Olympics',
            true,
            $colorScheme
        );
    }

    /**
     * Render H.E.A.T. stats with flip between Averages and Totals
     */
    public static function renderHeat(
        PlayerHeatAveragesView $averagesView,
        PlayerHeatTotalsView $totalsView,
        string $playerName,
        ?\mysqli $db = null,
        int $teamID = 0
    ): string {
        $colorScheme = CardBaseStyles::getColorSchemeForTeam($db, $teamID);
        return self::render(
            $averagesView->renderAverages($playerName),
            $totalsView->renderTotals($playerName),
            'H.E.A.T.',
            true,
            $colorScheme
        );
    }
}
