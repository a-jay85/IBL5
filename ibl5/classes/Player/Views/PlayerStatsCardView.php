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
/* Player Stats Card - Horizontal Layout
   Uses !important to override legacy .player-table styles */
.player-stats-card {
    background: linear-gradient(145deg, #1e3a5f 0%, #0f1419 50%, #1e3a5f 100%) !important;
    border: 3px solid #D4AF37 !important;
    border-radius: 12px !important;
    box-shadow: 
        0 0 0 1px #1e3a5f,
        0 0 0 3px #D4AF37,
        0 8px 32px rgba(0,0,0,0.3) !important;
    margin: 16px auto !important;
    padding: 16px !important;
    color: #fff !important;
    overflow-x: auto;
    position: relative;
}

.player-stats-card * {
    box-sizing: border-box;
}

/* Stats Table Styling - Override legacy .player-table and .sortable styles */
.player-stats-card table,
.player-stats-card .stats-table,
.player-stats-card table.sortable,
.player-stats-card table.player-table {
    width: 100% !important;
    border-collapse: collapse !important;
    table-layout: auto !important;
    font-size: 13px !important;
    border: none !important;
    background: transparent !important;
    margin: 0 !important;
}

/* Table header - gold gradient */
.player-stats-card .stats-table-header,
.player-stats-card .player-table-header,
.player-stats-card td.player-table-header,
.player-stats-card td.stats-table-header {
    background: linear-gradient(135deg, #D4AF37 0%, #b8972e 100%) !important;
    color: #0f1419 !important;
    font-weight: 700 !important;
    font-size: 14px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    padding: 10px 16px !important;
    text-align: center !important;
    border: none !important;
    border-radius: 8px 8px 0 0;
}

/* Column headers */
.player-stats-card table th,
.player-stats-card .stats-table th {
    background: rgba(212, 175, 55, 0.15) !important;
    color: #D4AF37 !important;
    font-weight: 600 !important;
    font-size: 11px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.3px !important;
    padding: 8px 6px !important;
    text-align: center !important;
    border: none !important;
    border-bottom: 1px solid rgba(212, 175, 55, 0.3) !important;
    white-space: nowrap;
}

/* Table cells */
.player-stats-card table td,
.player-stats-card .stats-table td {
    padding: 6px 6px !important;
    text-align: center !important;
    border: none !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
    color: #e5e7eb !important;
    font-family: 'Monaco', 'Menlo', 'Consolas', monospace !important;
    font-size: 12px !important;
    white-space: nowrap;
    background: transparent !important;
}

/* Row hover effect */
.player-stats-card table tbody tr:hover td,
.player-stats-card .stats-table tbody tr:hover td {
    background: rgba(212, 175, 55, 0.08) !important;
}

/* Alternating row colors */
.player-stats-card table tbody tr:nth-child(even) td,
.player-stats-card .stats-table tbody tr:nth-child(even) td {
    background: rgba(0, 0, 0, 0.15) !important;
}

.player-stats-card table tbody tr:nth-child(even):hover td,
.player-stats-card .stats-table tbody tr:nth-child(even):hover td {
    background: rgba(212, 175, 55, 0.12) !important;
}

/* Career/Total Row Styling */
.player-stats-card .career-row td,
.player-stats-card tr.player-table-row-bold td,
.player-stats-card .stats-table .career-row td,
.player-stats-card .stats-table tr.player-table-row-bold td {
    background: linear-gradient(90deg, rgba(212, 175, 55, 0.2) 0%, rgba(212, 175, 55, 0.1) 100%) !important;
    font-weight: 700 !important;
    color: #fff !important;
    border-top: 2px solid #D4AF37 !important;
}

/* Footer Row (e.g., Total Salary) */
.player-stats-card .footer-row td,
.player-stats-card .stats-table .footer-row td {
    background: rgba(0, 0, 0, 0.3) !important;
    color: #D4AF37 !important;
    font-weight: 600 !important;
    font-style: italic;
    padding: 12px !important;
    text-align: center !important;
    border-top: 2px solid rgba(212, 175, 55, 0.3) !important;
}

/* Team Links */
.player-stats-card table a,
.player-stats-card .stats-table a {
    color: #60a5fa !important;
    text-decoration: none !important;
    transition: color 0.2s ease;
}

.player-stats-card table a:hover,
.player-stats-card .stats-table a:hover {
    color: #D4AF37 !important;
    text-decoration: underline !important;
}

/* Responsive: Horizontal scroll on small screens */
@media (max-width: 768px) {
    .player-stats-card {
        margin: 12px 8px !important;
        padding: 12px !important;
        border-radius: 8px !important;
    }
    
    .player-stats-card table th,
    .player-stats-card table td,
    .player-stats-card .stats-table th,
    .player-stats-card .stats-table td {
        padding: 4px 4px !important;
        font-size: 10px !important;
    }
    
    .player-stats-card .stats-table-header,
    .player-stats-card .player-table-header {
        font-size: 12px !important;
        padding: 8px 12px !important;
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
    z-index: 5;
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
