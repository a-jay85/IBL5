<?php

declare(strict_types=1);

namespace Player\Views;

/**
 * PlayerViewStyles - Centralized CSS styles for all Player Views
 * 
 * Provides a unified style block that replaces deprecated HTML tags
 * (<center>, <font>, <b>, align=, bgcolor=) with modern CSS equivalents.
 * 
 * Include this at the top of your page or in a view factory to ensure
 * consistent styling across all player view components.
 * 
 * Usage:
 *   echo PlayerViewStyles::getStyles();
 * 
 * @since 2026-01-08
 */
class PlayerViewStyles
{
    /**
     * Returns the centralized CSS styles for Player Views
     * 
     * CSS Classes:
     * - .player-table: Base table styling with auto-centering
     * - .player-table-header: Blue header row with white text
     * - .player-table th: Bold, centered table headers
     * - .player-table td: Centered table cells
     * - .player-table-row-bold: Bold row (for career totals)
     * - .text-center: Centered text
     * - .text-bold: Bold text
     * - .text-white: White text color
     * - .bg-blue: Blue background (#0000cc)
     * - .content-header: Styled content header (replaces <font class="content">)
     * - .section-title: Page section title (replaces <H1><center>)
     * - .allstar-table: All-Star activity table styling
     * - .awards-table: Awards list table styling
     * - .gamelog: Game log cell styling
     * - .oneonone-table: One-on-one results table
     * 
     * @return string CSS style block
     */
    public static function getStyles(): string
    {
        return <<<'CSS'
<style>
/* ============================================
   Player Views - Centralized Styles
   Replaces deprecated HTML tags with CSS
   ============================================ */

/* Base table styling - replaces align="center" on tables */
.player-table {
    margin: 0 auto;
    border: 1px solid #000;
    border-collapse: collapse;
}

.player-table td,
.player-table th {
    text-align: center;
    padding: 4px 8px;
    border: 1px solid #000;
}

/* Table header row - replaces bgcolor=#0000cc and <font color=#ffffff> */
.player-table-header {
    background-color: #0000cc;
    color: #ffffff;
    font-weight: bold;
    text-align: center;
}

/* Bold row for career/total summaries */
.player-table-row-bold {
    font-weight: bold;
}

/* Column headers - replaces <b> in <th> */
.player-table th {
    font-weight: bold;
}

/* Content headers - replaces <font class="content"><b> */
.content-header {
    font-weight: bold;
    text-align: center;
}

/* Section title - replaces <H1><center> */
.section-title {
    font-size: 2em;
    font-weight: bold;
    text-align: center;
    margin: 1em 0;
}

/* ============================================
   Utility Classes
   ============================================ */

/* Text alignment - replaces align="center", align="left" */
.text-center {
    text-align: center;
}

.text-left {
    text-align: left;
}

/* Text formatting - replaces <b>, <strong> */
.text-bold {
    font-weight: bold;
}

/* Colors - replaces <font color="..."> */
.text-white {
    color: #ffffff;
}

/* Background colors - replaces bgcolor="..." */
.bg-blue {
    background-color: #0000cc;
}

/* ============================================
   Component-Specific Styles
   ============================================ */

/* Ratings table - player overview */
.misc-ratings-table {
    margin: 0 auto;
    border-collapse: separate;
    border-spacing: 0px 0px;
}

.misc-ratings-table td {
    text-align: center;
    padding: 0px 8px;
}

.misc-ratings-table .header-row td {
    font-weight: bold;
}

/* All-Star activity table */
.allstar-table {
    border: 1px solid #000;
    border-collapse: collapse;
    text-align: left;
}

.allstar-table td {
    padding: 4px 8px;
    border: 1px solid #000;
}

.allstar-table th {
    text-align: center;
    font-weight: bold;
    padding: 4px 8px;
}

/* Awards table */
.awards-table {
    border: 1px solid #000;
    border-collapse: collapse;
    margin: 0 auto;
}

.awards-table td {
    padding: 4px 8px;
    border: 1px solid #000;
}

.awards-table .year-cell {
    text-align: center;
}

/* Game log cells */
.gamelog {
    text-align: center;
}

/* One-on-One results table */
.oneonone-table {
    margin: 0 auto;
}

.oneonone-table td {
    padding: 8px;
}

/* Sim stats table */
.sim-stats-table {
    margin: 0 auto;
    border: 1px solid #000;
    border-collapse: collapse;
    text-align: center;
}

.sim-stats-table td,
.sim-stats-table th {
    padding: 4px 8px;
    border: 1px solid #000;
    text-align: center;
}

/* Stats tables - regular season, playoffs, heat, olympics */
.stats-table {
    border: 1px solid #000;
    border-collapse: collapse;
    margin: 0 auto;
}

.stats-table td,
.stats-table th {
    padding: 4px 8px;
    border: 1px solid #000;
    text-align: center;
}

.stats-table .header-cell {
    font-weight: bold;
    text-align: center;
}

/* Sortable tables - extends existing .sortable class */
.sortable.player-table {
    margin: 0 auto;
}
</style>
CSS;
    }

    /**
     * Returns inline styles for a single element (for gradual migration)
     * 
     * @param string $type Type of styling needed
     * @return string Inline style attribute value
     */
    public static function getInlineStyle(string $type): string
    {
        return match ($type) {
            'center' => 'text-align: center;',
            'bold' => 'font-weight: bold;',
            'header-blue' => 'background-color: #0000cc; color: #ffffff; font-weight: bold; text-align: center;',
            'table-center' => 'margin: 0 auto;',
            default => '',
        };
    }
}
