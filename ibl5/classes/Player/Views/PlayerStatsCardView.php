<?php

declare(strict_types=1);

namespace Player\Views;

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
     * Get scoped custom styles for stats cards (no external dependencies)
     * 
     * @return string HTML style tag with scoped CSS
     */
    public static function getStyles(): string
    {
        return <<<'HTML'
<style>
/* Player Stats Card - Horizontal Layout */
.player-stats-card {
    background: linear-gradient(145deg, #1e3a5f 0%, #0f1419 50%, #1e3a5f 100%);
    border: 3px solid #D4AF37;
    border-radius: 12px;
    box-shadow: 
        0 0 0 1px #1e3a5f,
        0 0 0 3px #D4AF37,
        0 8px 32px rgba(0,0,0,0.3);
    margin: 16px auto;
    padding: 16px;
    color: #fff;
    overflow-x: auto;
}

.player-stats-card * {
    box-sizing: border-box;
}

/* Stats Table Styling */
.player-stats-card .stats-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: auto;
    font-size: 13px;
}

.player-stats-card .stats-table-header {
    background: linear-gradient(135deg, #D4AF37 0%, #b8972e 100%);
    color: #0f1419;
    font-weight: 700;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 16px;
    text-align: center;
    border-radius: 8px 8px 0 0;
}

.player-stats-card .stats-table th {
    background: rgba(212, 175, 55, 0.15);
    color: #D4AF37;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    padding: 8px 6px;
    text-align: center;
    border-bottom: 1px solid rgba(212, 175, 55, 0.3);
    white-space: nowrap;
}

.player-stats-card .stats-table td {
    padding: 6px 6px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    color: #e5e7eb;
    font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
    font-size: 12px;
    white-space: nowrap;
}

.player-stats-card .stats-table tbody tr:hover {
    background: rgba(212, 175, 55, 0.08);
}

.player-stats-card .stats-table tbody tr:nth-child(even) {
    background: rgba(0, 0, 0, 0.15);
}

.player-stats-card .stats-table tbody tr:nth-child(even):hover {
    background: rgba(212, 175, 55, 0.12);
}

/* Career/Total Row Styling */
.player-stats-card .stats-table .career-row,
.player-stats-card .stats-table tr.player-table-row-bold {
    background: linear-gradient(90deg, rgba(212, 175, 55, 0.2) 0%, rgba(212, 175, 55, 0.1) 100%) !important;
    font-weight: 700;
    color: #fff;
    border-top: 2px solid #D4AF37;
}

.player-stats-card .stats-table .career-row td,
.player-stats-card .stats-table tr.player-table-row-bold td {
    color: #fff;
    font-weight: 700;
}

/* Footer Row (e.g., Total Salary) */
.player-stats-card .stats-table .footer-row td {
    background: rgba(0, 0, 0, 0.3);
    color: #D4AF37;
    font-weight: 600;
    font-style: italic;
    padding: 12px;
    text-align: center;
    border-top: 2px solid rgba(212, 175, 55, 0.3);
}

/* Team Links */
.player-stats-card .stats-table a {
    color: #60a5fa;
    text-decoration: none;
    transition: color 0.2s ease;
}

.player-stats-card .stats-table a:hover {
    color: #D4AF37;
    text-decoration: underline;
}

/* Responsive: Horizontal scroll on small screens */
@media (max-width: 768px) {
    .player-stats-card {
        margin: 12px 8px;
        padding: 12px;
        border-radius: 8px;
    }
    
    .player-stats-card .stats-table th,
    .player-stats-card .stats-table td {
        padding: 4px 4px;
        font-size: 10px;
    }
    
    .player-stats-card .stats-table-header {
        font-size: 12px;
        padding: 8px 12px;
    }
}

/* Card Title Badge */
.player-stats-card .card-title-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.player-stats-card .card-title-badge .badge {
    background: rgba(0, 0, 0, 0.3);
    color: #fff;
    font-size: 10px;
    padding: 2px 6px;
    border-radius: 4px;
    font-weight: 400;
    text-transform: none;
    letter-spacing: normal;
}

/* Stats Type Indicator */
.player-stats-card .stats-type-indicator {
    position: absolute;
    top: 8px;
    right: 8px;
    background: rgba(212, 175, 55, 0.9);
    color: #0f1419;
    font-size: 9px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>
HTML;
    }

    /**
     * Wrap stats table content in a styled card
     * 
     * @param string $tableContent The inner table HTML content
     * @param string $title Optional card title (overrides table header)
     * @param string $statsType Optional stats type badge (e.g., "Averages", "Totals")
     * @return string Complete HTML for the stats card
     */
    public static function wrap(string $tableContent, string $title = '', string $statsType = ''): string
    {
        ob_start();
        ?>
<div class="player-stats-card">
    <?php if ($statsType): ?>
    <div class="stats-type-indicator"><?= htmlspecialchars($statsType, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?= $tableContent ?>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Apply stats card styling to an existing table by adding appropriate classes
     * 
     * This method transforms standard player-table markup to use stats-card styling.
     * 
     * @param string $tableHtml The original table HTML
     * @return string Modified table HTML with stats-card classes
     */
    public static function styleTable(string $tableHtml): string
    {
        // Replace sortable player-table with stats-table
        $styled = str_replace(
            'class="sortable player-table"',
            'class="stats-table sortable"',
            $tableHtml
        );
        
        // Also handle sim-stats-table variant
        $styled = str_replace(
            'class="sortable player-table sim-stats-table"',
            'class="stats-table sortable sim-stats-table"',
            $styled
        );
        
        // Replace player-table-header with stats-table-header
        $styled = str_replace(
            'class="player-table-header"',
            'class="stats-table-header"',
            $styled
        );
        
        // Add career-row class to bold rows for additional styling
        $styled = str_replace(
            'class="player-table-row-bold"',
            'class="player-table-row-bold career-row"',
            $styled
        );
        
        return $styled;
    }

    /**
     * Render a complete stats card with automatic table styling
     * 
     * @param string $tableHtml Raw table HTML from a stats view
     * @param string $statsType Optional stats type indicator
     * @return string Complete styled stats card HTML
     */
    public static function render(string $tableHtml, string $statsType = ''): string
    {
        return self::wrap(self::styleTable($tableHtml), '', $statsType);
    }
}
