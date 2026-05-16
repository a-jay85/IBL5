<?php

declare(strict_types=1);

namespace UI\Contracts;

/**
 * TableStylesInterface - Contract for generating inline CSS custom property values for team-colored tables
 */
interface TableStylesInterface
{
    /**
     * Generate inline CSS custom property declarations for team colors
     *
     * @param string $teamColor Primary team color (hex without #)
     * @param string $teamColor2 Secondary team color (hex without #)
     * @return string Inline style value (e.g. "--team-color-primary: #1e3a5f; --team-color-secondary: #D4AF37;")
     */
    public static function inlineVars(string $teamColor, string $teamColor2): string;

    /**
     * Sanitize color value to prevent injection
     *
     * @param string $color Hex color value
     * @return string Sanitized hex color
     */
    public static function sanitizeColor(string $color): string;
}
