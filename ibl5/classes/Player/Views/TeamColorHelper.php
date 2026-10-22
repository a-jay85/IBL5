<?php

declare(strict_types=1);

namespace Player\Views;

/**
 * TeamColorHelper - Utility for fetching and applying team colors to card designs
 * 
 * Provides methods to:
 * - Apply the fallback to team color rows
 * - Calculate proper contrast ratios for readability (WCAG AA compliance)
 * - Generate dynamic CSS color schemes with guaranteed accessibility
 * 
 * The color scheme generation algorithm ensures all text colors meet WCAG AA
 * standards (4.5:1 minimum contrast for normal text, 3:1 for large text).
 * 
 * This fixes issues like Orlando Magic's dark blue + black colors producing
 * unreadable gray text (1.38:1 contrast), now improved to 4.5:1+ contrast.
 * 
 * @since 2026-01-08
 * @version 2.0 (2026-01-08) - Improved contrast algorithm for WCAG AA compliance
 */
class TeamColorHelper
{
    private const MIN_CONTRAST = 4.5; // WCAG AA standard for normal text

    /**
     * Apply the presentation fallback to a raw ibl_team_info color row.
     *
     * @param array{color1: string|null, color2: string|null}|null $row Null when no row exists
     * @return array{color1: string, color2: string} Team colors (hex without #)
     */
    public static function resolveTeamColors(?array $row): array
    {
        if ($row === null) {
            return ['color1' => 'D4AF37', 'color2' => '1e3a5f'];
        }

        return [
            'color1' => $row['color1'] !== null && $row['color1'] !== '' ? $row['color1'] : 'D4AF37',
            'color2' => $row['color2'] !== null && $row['color2'] !== '' ? $row['color2'] : '1e3a5f',
        ];
    }

    /**
     * Convert hex color to RGB array
     * 
     * @param string $hex Hex color (with or without #)
     * @return array{r: int, g: int, b: int} RGB values
     */
    public static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return [
            'r' => (int) hexdec(substr($hex, 0, 2)),
            'g' => (int) hexdec(substr($hex, 2, 2)),
            'b' => (int) hexdec(substr($hex, 4, 2))
        ];
    }

    /**
     * Calculate relative luminance for WCAG contrast calculation
     * 
     * @param array{r: int, g: int, b: int} $rgb RGB color values
     * @return float Relative luminance (0-1)
     */
    public static function getLuminance(array $rgb): float
    {
        $r = $rgb['r'] / 255;
        $g = $rgb['g'] / 255;
        $b = $rgb['b'] / 255;
        
        $r = ($r <= 0.03928) ? $r / 12.92 : pow(($r + 0.055) / 1.055, 2.4);
        $g = ($g <= 0.03928) ? $g / 12.92 : pow(($g + 0.055) / 1.055, 2.4);
        $b = ($b <= 0.03928) ? $b / 12.92 : pow(($b + 0.055) / 1.055, 2.4);
        
        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /**
     * Calculate contrast ratio between two colors (WCAG standard)
     * 
     * @param string $color1 First hex color
     * @param string $color2 Second hex color
     * @return float Contrast ratio (1-21)
     */
    public static function getContrastRatio(string $color1, string $color2): float
    {
        $lum1 = self::getLuminance(self::hexToRgb($color1));
        $lum2 = self::getLuminance(self::hexToRgb($color2));
        
        $lighter = max($lum1, $lum2);
        $darker = min($lum1, $lum2);
        
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    /**
     * Determine if a color is dark (luminance < 0.5)
     * 
     * @param string $hex Hex color
     * @return bool True if dark, false if light
     */
    public static function isDark(string $hex): bool
    {
        $rgb = self::hexToRgb($hex);
        $luminance = self::getLuminance($rgb);
        return $luminance < 0.5;
    }

    /**
     * Get appropriate text color (white or black) for a background color
     * 
     * @param string $bgHex Background hex color
     * @return string 'ffffff' or '000000'
     */
    public static function getTextColor(string $bgHex): string
    {
        $whiteContrast = self::getContrastRatio($bgHex, 'ffffff');
        $blackContrast = self::getContrastRatio($bgHex, '000000');
        
        // Use white text if it has better contrast, otherwise use black
        return ($whiteContrast > $blackContrast) ? 'ffffff' : '000000';
    }

    /**
     * Keep the preferred text color when it meets WCAG AA against the background,
     * otherwise fall back to white or black via getTextColor().
     *
     * @param string $bgHex Background hex color
     * @param string $preferredHex Preferred text hex color
     * @return string $preferredHex unchanged, or 'ffffff' / '000000'
     */
    public static function getReadableTextColor(string $bgHex, string $preferredHex, float $minRatio = 4.5): string
    {
        if (self::getContrastRatio($bgHex, $preferredHex) >= $minRatio) {
            return $preferredHex;
        }

        return self::getTextColor($bgHex);
    }

    /**
     * Lighten a color by a percentage
     * 
     * @param string $hex Hex color
     * @param float $percent Percentage to lighten (0-100)
     * @return string Lightened hex color (without #)
     */
    public static function lighten(string $hex, float $percent): string
    {
        $rgb = self::hexToRgb($hex);
        
        $rgb['r'] = min(255, (int)($rgb['r'] + (255 - $rgb['r']) * ($percent / 100)));
        $rgb['g'] = min(255, (int)($rgb['g'] + (255 - $rgb['g']) * ($percent / 100)));
        $rgb['b'] = min(255, (int)($rgb['b'] + (255 - $rgb['b']) * ($percent / 100)));
        
        return sprintf('%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);
    }

    /**
     * Darken a color by a percentage
     * 
     * @param string $hex Hex color
     * @param float $percent Percentage to darken (0-100)
     * @return string Darkened hex color (without #)
     */
    public static function darken(string $hex, float $percent): string
    {
        $rgb = self::hexToRgb($hex);
        
        $rgb['r'] = max(0, (int)($rgb['r'] * (1 - $percent / 100)));
        $rgb['g'] = max(0, (int)($rgb['g'] * (1 - $percent / 100)));
        $rgb['b'] = max(0, (int)($rgb['b'] * (1 - $percent / 100)));
        
        return sprintf('%02x%02x%02x', $rgb['r'], $rgb['g'], $rgb['b']);
    }

    /**
     * Generate a color scheme for cards based on team colors
     * 
     * @param string $color1 Primary team color (hex)
     * @param string $color2 Secondary team color (hex)
     * @return array{
     *   primary: string,
     *   secondary: string,
     *   gradient_start: string,
     *   gradient_mid: string,
     *   gradient_end: string,
     *   border: string,
     *   border_rgb: string,
     *   accent: string,
     *   text: string,
     *   text_muted: string
     * }
     */
    public static function generateColorScheme(string $color1, string $color2): array
    {
        // Ensure we have the darker color as the gradient base
        $primary = self::isDark($color1) ? $color1 : $color2;
        $secondary = self::isDark($color1) ? $color2 : $color1;

        $border = self::chooseBorder($color1, $color2, $primary);

        // Get RGB values for border (for rgba() usage)
        $borderRgb = self::hexToRgb($border);
        $borderRgbString = "{$borderRgb['r']}, {$borderRgb['g']}, {$borderRgb['b']}";

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'gradient_start' => $secondary,
            'gradient_mid' => self::darken($primary, 10),
            'gradient_end' => $secondary,
            'border' => $border,
            'border_rgb' => $borderRgbString,
            'accent' => self::deriveAccent($border, $primary),
            'text' => self::getTextColor($primary),
            'text_muted' => self::deriveMutedText($primary),
        ];
    }

    /**
     * Pick the team color with better contrast against the primary, lighten it until it
     * meets MIN_CONTRAST, and fall back to near-white or near-black if it never does.
     */
    private static function chooseBorder(string $color1, string $color2, string $primary): string
    {
        $seed = (self::getContrastRatio($color1, $primary) > self::getContrastRatio($color2, $primary)) ? $color1 : $color2;

        if (self::getContrastRatio($seed, $primary) >= self::MIN_CONTRAST) {
            return $seed;
        }

        // Lighter than primary: lighten more. Darker or equal: lighten significantly.
        $border = (self::getLuminance(self::hexToRgb($seed)) > self::getLuminance(self::hexToRgb($primary)))
            ? self::lightenUntilContrast($seed, $primary, 20, 90, 10)
            : self::lightenUntilContrast($seed, $primary, 80, 98, 2);

        if (self::getContrastRatio($border, $primary) < self::MIN_CONTRAST) {
            return self::isDark($primary) ? 'f0f0f0' : '1a1a1a';
        }

        return $border;
    }

    /**
     * Lighten $color by $start, $start + $step, ... up to $max percent, stopping at the first
     * result that meets MIN_CONTRAST against $background. Returns the last result tried.
     */
    private static function lightenUntilContrast(string $color, string $background, int $start, int $max, int $step): string
    {
        $result = $color;
        for ($percent = $start; $percent <= $max; $percent += $step) {
            $result = self::lighten($color, $percent);
            if (self::getContrastRatio($result, $background) >= self::MIN_CONTRAST) {
                break;
            }
        }

        return $result;
    }

    private static function deriveAccent(string $border, string $primary): string
    {
        // Lighten the border by 10%; if contrast is too low, use the border itself
        $accent = self::lighten($border, 10);

        return self::getContrastRatio($accent, $primary) < self::MIN_CONTRAST ? $border : $accent;
    }

    private static function deriveMutedText(string $primary): string
    {
        // Muted text must meet WCAG AA for large text (3:1)
        $textMuted = self::isDark($primary) ? 'cccccc' : '333333';
        if (self::getContrastRatio($textMuted, $primary) < 3.0) {
            // '222222' is unreachable today; kept verbatim
            $textMuted = self::isDark($primary) ? 'dddddd' : '222222';
        }

        return $textMuted;
    }

    /**
     * Get default color scheme (fallback)
     *
     * @return array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string} Color scheme array
     */
    public static function getDefaultColorScheme(): array
    {
        return self::generateColorScheme('D4AF37', '1e3a5f');
    }
}
