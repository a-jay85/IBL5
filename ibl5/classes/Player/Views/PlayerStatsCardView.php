<?php

declare(strict_types=1);

namespace Player\Views;

use Security\HtmlSanitizer;

/**
 * PlayerStatsCardView - Reusable horizontal stats card wrapper
 * 
 * Provides consistent styling for all player statistics tables using
 * a horizontal card layout inspired by PlayerTradingCardBackView.
 * Supports all stat view types with a unified visual design.
 * 
 * @since 2026-01-08
 */
class PlayerStatsCardView
{
    /**
     * Get scoped custom styles for stats cards with team colors
     *
     * @deprecated CSS is now centralized in design/components/player-cards.css.
     *             Custom properties are set inline on the container element in wrap().
     *
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string}|null $colorScheme Optional color scheme from TeamColorHelper
     * @return string Empty string — styles are in centralized CSS
     */
    public static function getStyles(?array $colorScheme = null): string
    {
        return '';
    }

    /**
     * Wrap stats table content in a styled card
     *
     * @param string $tableContent The inner table HTML content
     * @param string $title Optional card title (overrides table header)
     * @param string $statsType Optional stats type badge (e.g., "Averages", "Totals")
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string}|null $colorScheme Optional color scheme from TeamColorHelper
     * @return string Complete HTML for the stats card
     */
    public static function wrap(string $tableContent, string $title = '', string $statsType = '', ?array $colorScheme = null): string
    {
        if ($colorScheme === null) {
            $colorScheme = TeamColorHelper::getDefaultColorScheme();
        }
        $html = '<div class="player-stats-card" style="' . CardBaseStyles::getCardCssProperties($colorScheme) . '">';
        if ($statsType !== '') {
            $html .= '<div class="stats-type-indicator">' . HtmlSanitizer::e($statsType) . '</div>';
        }
        $html .= $tableContent;
        $html .= '</div>';
        return $html;
    }

    /**
     * Apply stats card styling to an existing table by adding appropriate classes
     * 
     * Maps .player-view-table markup onto the gold card hooks (.stats-table, .stats-table-header).
     * 
     * @param string $tableHtml The original table HTML
     * @return string Modified table HTML with stats-card classes
     */
    public static function styleTable(string $tableHtml): string
    {
        // Inside the gold card the legacy title-bar modifier gives way to the
        // card's own hook; .ibl-data-table stays so the card themes the shared table.
        $styled = str_replace(
            'class="sortable ibl-data-table player-view-table"',
            'class="sortable ibl-data-table stats-table"',
            $tableHtml
        );

        // Title cell: legacy blue bar becomes the gold card header
        return str_replace(
            'class="player-view-table__title"',
            'class="stats-table-header"',
            $styled
        );
    }

    /**
     * Render a complete stats card with automatic table styling
     *
     * @param string $tableHtml Raw table HTML from a stats view
     * @param string $statsType Optional stats type indicator
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string}|null $colorScheme Optional color scheme from TeamColorHelper
     * @return string Complete styled stats card HTML
     */
    public static function render(string $tableHtml, string $statsType = '', ?array $colorScheme = null): string
    {
        return self::wrap(self::styleTable($tableHtml), '', $statsType, $colorScheme);
    }
}
