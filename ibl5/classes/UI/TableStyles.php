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

        ob_start();
        ?>
<style>
.<?= $tableClass ?> { --team-sep-color: #<?= $teamColor ?>; color: #<?= $teamColor2 ?>; border-collapse: collapse; }
.<?= $tableClass ?> .salary { padding-left: 3px; }
.<?= $tableClass ?> th { color: #<?= $teamColor2 ?>; }
.<?= $tableClass ?> td { color: #000; }
.<?= $tableClass ?> th.sep-team, .<?= $tableClass ?> td.sep-team { border-right: 3px solid var(--team-sep-color); }
.<?= $tableClass ?> th.sep-weak, .<?= $tableClass ?> td.sep-weak { border-right: 1px solid #CCCCCC; }
.<?= $tableClass ?> th.salary { text-align: left; }
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
}
