<?php

declare(strict_types=1);

namespace UI;

use UI\Contracts\TableStylesInterface;

/**
 * TableStyles - Generates inline CSS custom property values for team-colored tables
 *
 * Used with the .team-table modifier class in tables.css.
 * Outputs only --team-color-primary and --team-color-secondary as inline style values.
 */
class TableStyles implements TableStylesInterface
{
    /**
     * Generate inline CSS custom property declarations for team colors
     *
     * @param string $teamColor Primary team color (hex without #)
     * @param string $teamColor2 Secondary team color (hex without #)
     * @param bool $withOnPrimary Append --team-color-on-primary, a text color that clears 4.5:1 on the primary color
     * @return string Inline style value (e.g. "--team-color-primary: #1e3a5f; --team-color-secondary: #D4AF37;")
     */
    public static function inlineTeamVars(string $teamColor, string $teamColor2, bool $withOnPrimary = false): string
    {
        $teamColor = self::sanitizeColor($teamColor);
        $teamColor2 = self::sanitizeColor($teamColor2);

        $vars = '--team-color-primary: #' . $teamColor . '; --team-color-secondary: #' . $teamColor2 . ';';
        if ($withOnPrimary) {
            $vars .= ' --team-color-on-primary: #'
                . \Player\Views\TeamColorHelper::getReadableTextColor($teamColor, $teamColor2) . ';';
        }

        return $vars;
    }

    /**
     * Sanitize color value to prevent injection
     *
     * @param string $color Hex color value
     * @return string Sanitized hex color
     */
    public static function sanitizeColor(string $color): string
    {
        $color = ltrim($color, '#');
        if (preg_match('/^[0-9A-Fa-f]{3}$|^[0-9A-Fa-f]{6}$/', $color) === 1) {
            return $color;
        }
        return '000000';
    }
}
