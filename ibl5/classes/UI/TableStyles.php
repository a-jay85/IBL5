<?php

declare(strict_types=1);

namespace UI;

/**
 * TableStyles - Generates inline CSS custom property values for team-colored tables
 *
 * Used with the .team-table modifier class in tables.css.
 * Outputs only --team-color-primary and --team-color-secondary as inline style values.
 */
class TableStyles
{
    /**
     * Generate inline CSS custom property declarations for team colors
     *
     * @param string $teamColor Primary team color (hex without #)
     * @param string $teamColor2 Secondary team color (hex without #)
     * @return string Inline style value (e.g. "--team-color-primary: #1e3a5f; --team-color-secondary: #D4AF37;")
     */
    public static function inlineVars(string $teamColor, string $teamColor2): string
    {
        $teamColor = self::sanitizeColor($teamColor);
        $teamColor2 = self::sanitizeColor($teamColor2);

        return '--team-color-primary: #' . $teamColor . '; --team-color-secondary: #' . $teamColor2 . ';';
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
}
