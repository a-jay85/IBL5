<?php

declare(strict_types=1);

namespace Player\Views;

/**
 * TeamColorHelper - Utility for fetching and applying team colors to card designs
 * 
 * Provides methods to:
 * - Fetch team colors from database
 * - Calculate proper contrast ratios for readability
 * - Generate dynamic CSS color schemes
 * 
 * @since 2026-01-08
 */
class TeamColorHelper
{
    /**
     * Fetch team colors from the database
     * 
     * @param \mysqli $db Database connection
     * @param int $teamID The team's ID
     * @return array{color1: string, color2: string} Team colors (hex without #)
     */
    public static function getTeamColors(\mysqli $db, int $teamID): array
    {
        $stmt = $db->prepare('SELECT color1, color2 FROM ibl_team_info WHERE teamid = ?');
        $stmt->bind_param('i', $teamID);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        if (!$row) {
            // Default to gold if team not found
            return ['color1' => 'D4AF37', 'color2' => '1e3a5f'];
        }
        
        return [
            'color1' => $row['color1'] ?: 'D4AF37',
            'color2' => $row['color2'] ?: '1e3a5f'
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
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
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
        
        // Generate gradient colors
        $gradientStart = $secondary;
        $gradientMid = self::darken($primary, 10);
        $gradientEnd = $secondary;
        
        // Border should be the brighter of the two colors
        $border = self::isDark($color1) ? $color2 : $color1;
        
        // Ensure border is visible - lighten if too dark
        if (self::getLuminance(self::hexToRgb($border)) < 0.3) {
            $border = self::lighten($border, 30);
        }
        
        // Get RGB values for border (for rgba() usage)
        $borderRgb = self::hexToRgb($border);
        $borderRgbString = "{$borderRgb['r']}, {$borderRgb['g']}, {$borderRgb['b']}";
        
        // Accent color for highlights (slightly lightened border)
        $accent = self::lighten($border, 10);
        
        // Text colors based on contrast
        $textOnPrimary = self::getTextColor($primary);
        $text = $textOnPrimary;
        
        // Muted text (70% opacity)
        $textMuted = self::isDark($primary) ? 'cccccc' : '333333';
        
        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'gradient_start' => $gradientStart,
            'gradient_mid' => $gradientMid,
            'gradient_end' => $gradientEnd,
            'border' => $border,
            'border_rgb' => $borderRgbString,
            'accent' => $accent,
            'text' => $text,
            'text_muted' => $textMuted
        ];
    }

    /**
     * Get default color scheme (fallback)
     * 
     * @return array Color scheme array
     */
    public static function getDefaultColorScheme(): array
    {
        return self::generateColorScheme('D4AF37', '1e3a5f');
    }
}
