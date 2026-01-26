<?php

namespace UI;

/**
 * TableStyles - Generates reusable CSS styles for tables with team-colored separators
 */
class TableStyles
{
    /**
     * Generate CSS styles for tables with team-colored separators
     *
     * @param string $tableClass CSS class name for the table
     * @param string $teamColor Primary team color (hex without #)
     * @param string $teamColor2 Secondary team color (hex without #)
     * @return string CSS style block
     */
    public static function render(string $tableClass, string $teamColor, string $teamColor2): string
    {
        // Sanitize color values to prevent injection
        $teamColor = self::sanitizeColor($teamColor);
        $teamColor2 = self::sanitizeColor($teamColor2);
        $tableClass = self::sanitizeClassName($tableClass);

        // Pre-compute color variants for browser compatibility
        $teamColorDark = self::adjustBrightness($teamColor, -15);
        $teamColorLight8 = self::mixWithWhite($teamColor, 8);
        $teamColorLight15 = self::mixWithWhite($teamColor, 15);
        $teamColorLight22 = self::mixWithWhite($teamColor, 22);

        ob_start();
        ?>
<style>
/* Modern table styling with team colors */
.<?= $tableClass ?> {
    --team-color-primary: #<?= $teamColor ?>;
    --team-color-secondary: #<?= $teamColor2 ?>;
    --team-sep-color: #<?= $teamColor ?>;
    font-family: var(--font-sans, 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif);
    border-collapse: separate;
    border-spacing: 0;
    border: none;
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
}
.<?= $tableClass ?> thead {
    background: linear-gradient(135deg, #<?= $teamColor ?>, #<?= $teamColorDark ?>);
}
@supports (background: color-mix(in srgb, red, blue)) {
    .<?= $tableClass ?> thead {
        background: linear-gradient(135deg, #<?= $teamColor ?>, color-mix(in srgb, #<?= $teamColor ?> 85%, black));
    }
}
.<?= $tableClass ?> th {
    color: #<?= $teamColor2 ?>;
    font-family: var(--font-display, 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 600;
    font-size: 1.125rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
}
.<?= $tableClass ?> td {
    color: var(--gray-800, #1f2937);
    font-size: 1rem;
}
.<?= $tableClass ?> tbody tr {
    transition: background-color 150ms ease;
}
.<?= $tableClass ?> tbody tr:hover {
    background-color: #<?= $teamColorLight8 ?> !important;
}
@supports (background: color-mix(in srgb, red, blue)) {
    .<?= $tableClass ?> tbody tr:hover {
        background-color: color-mix(in srgb, #<?= $teamColor ?> 8%, white) !important;
    }
}
.<?= $tableClass ?> th:first-child, .<?= $tableClass ?> td:first-child { padding-left: 3px; }
.<?= $tableClass ?> th:last-child, .<?= $tableClass ?> td:last-child { padding-right: 3px; }
.<?= $tableClass ?> th:first-row, .<?= $tableClass ?> td:first-row { padding-top: 3px; }
.<?= $tableClass ?> th.sep-team, .<?= $tableClass ?> td.sep-team { border-right: 2px solid var(--team-sep-color); padding-right: 3px; }
.<?= $tableClass ?> th.sep-team + th, .<?= $tableClass ?> th.sep-team + td, .<?= $tableClass ?> td.sep-team + th, .<?= $tableClass ?> td.sep-team + td { padding-left: 3px; }
.<?= $tableClass ?> th.sep-weak, .<?= $tableClass ?> td.sep-weak { border-right: 1px solid var(--gray-200, #e5e7eb); padding-right: 3px; }
.<?= $tableClass ?> th.sep-weak + th, .<?= $tableClass ?> th.sep-weak + td, .<?= $tableClass ?> td.sep-weak + th, .<?= $tableClass ?> td.sep-weak + td { padding-left: 3px; }
.<?= $tableClass ?> th.salary { text-align: left; }
.<?= $tableClass ?> tbody tr:nth-child(odd) { background-color: white; }
.<?= $tableClass ?> tbody tr:nth-child(even) { background-color: var(--gray-50, #f9fafb); }
.<?= $tableClass ?> a {
    color: var(--gray-800, #1f2937);
    text-decoration: none;
    font-weight: 500;
    transition: color 150ms ease;
}
.<?= $tableClass ?> a:hover {
    color: #<?= $teamColor ?>;
}
/* Highlight row for special cases (League_Starters, Next_Sim) */
.<?= $tableClass ?> tr.ratings-highlight {
    background-color: #<?= $teamColorLight15 ?> !important;
}
.<?= $tableClass ?> tr.ratings-highlight:hover {
    background-color: #<?= $teamColorLight22 ?> !important;
}
@supports (background: color-mix(in srgb, red, blue)) {
    .<?= $tableClass ?> tr.ratings-highlight {
        background-color: color-mix(in srgb, #<?= $teamColor ?> 15%, white) !important;
    }
    .<?= $tableClass ?> tr.ratings-highlight:hover {
        background-color: color-mix(in srgb, #<?= $teamColor ?> 22%, white) !important;
    }
}
/* Separator row styling */
.<?= $tableClass ?> tr.ratings-separator td {
    padding: 0 !important;
}
</style>
        <?php
        return ob_get_clean();
    }

    /**
     * Sanitize color value to prevent injection
     *
     * @param string $color Hex color value
     * @return string Sanitized hex color
     */
    private static function sanitizeColor(string $color): string
    {
        $color = ltrim($color, '#');
        if (preg_match('/^[0-9A-Fa-f]{3}$|^[0-9A-Fa-f]{6}$/', $color)) {
            return $color;
        }
        return '000000';
    }

    /**
     * Sanitize CSS class name
     *
     * @param string $className CSS class name
     * @return string Sanitized class name
     */
    private static function sanitizeClassName(string $className): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $className);
    }

    /**
     * Adjust brightness of a hex color
     *
     * @param string $hex Hex color (without #)
     * @param int $percent Percentage to adjust (-100 to 100)
     * @return string Adjusted hex color (without #)
     */
    private static function adjustBrightness(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, min(255, $r + ($r * $percent / 100)));
        $g = max(0, min(255, $g + ($g * $percent / 100)));
        $b = max(0, min(255, $b + ($b * $percent / 100)));

        return sprintf('%02x%02x%02x', (int)$r, (int)$g, (int)$b);
    }

    /**
     * Mix a hex color with white at a given percentage
     *
     * @param string $hex Hex color (without #)
     * @param int $percent Percentage of original color (0-100)
     * @return string Mixed hex color (without #)
     */
    private static function mixWithWhite(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Mix with white (255, 255, 255)
        $factor = $percent / 100;
        $r = (int)($r * $factor + 255 * (1 - $factor));
        $g = (int)($g * $factor + 255 * (1 - $factor));
        $b = (int)($b * $factor + 255 * (1 - $factor));

        return sprintf('%02x%02x%02x', $r, $g, $b);
    }
}
