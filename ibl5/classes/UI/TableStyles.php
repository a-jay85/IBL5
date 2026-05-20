<?php

declare(strict_types=1);

namespace UI;

use UI\Contracts\TableStylesInterface;

/** @see TableStylesInterface */
class TableStyles implements TableStylesInterface
{
    /** @see TableStylesInterface::inlineTeamVars() */
    public static function inlineTeamVars(string $teamColor, string $teamColor2): string
    {
        $teamColor = self::sanitizeColor($teamColor);
        $teamColor2 = self::sanitizeColor($teamColor2);

        return '--team-color-primary: #' . $teamColor . '; --team-color-secondary: #' . $teamColor2 . ';';
    }

    /** @see TableStylesInterface::sanitizeColor() */
    public static function sanitizeColor(string $color): string
    {
        $color = ltrim($color, '#');
        if (preg_match('/^[0-9A-Fa-f]{3}$|^[0-9A-Fa-f]{6}$/', $color) === 1) {
            return $color;
        }
        return '000000';
    }
}
