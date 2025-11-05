<?php

namespace Statistics;

/**
 * StatsFormatter - Unified formatting for basketball statistics
 * 
 * This class provides consistent formatting methods for all statistical displays
 * across the site, reducing redundant code and ensuring uniform presentation.
 * 
 * Key features:
 * - Safe division with zero-division handling
 * - Consistent decimal places for different stat types
 * - Comma-separated thousands for large numbers
 * - Null-safe operations
 */
class StatsFormatter
{
    /**
     * Format a shooting percentage (FG%, FT%, 3P%)
     * Returns formatted percentage with 3 decimal places (e.g., "0.523")
     * 
     * @param float|int|null $made Number of shots made
     * @param float|int|null $attempted Number of shots attempted
     * @return string Formatted percentage or "0.000" if attempted is 0
     */
    public static function formatPercentage($made, $attempted): string
    {
        if ($attempted === null || $attempted == 0) {
            return "0.000";
        }
        
        $made = $made ?? 0;
        $percentage = $made / $attempted;
        return number_format($percentage, 3);
    }

    /**
     * Format a per-game average statistic
     * Returns formatted average with 1 decimal place (e.g., "12.5")
     * 
     * @param float|int|null $total Total value
     * @param int|null $games Number of games
     * @return string Formatted per-game average or "0.0" if games is 0
     */
    public static function formatPerGameAverage($total, $games): string
    {
        if ($games === null || $games == 0) {
            return "0.0";
        }
        
        $total = $total ?? 0;
        $average = $total / $games;
        return number_format($average, 1);
    }

    /**
     * Format a per-36-minute statistic
     * Returns formatted stat with 1 decimal place (e.g., "8.3")
     * 
     * @param float|int|null $total Total value
     * @param float|int|null $minutes Total minutes played
     * @return string Formatted per-36-minute stat or "0.0" if minutes is 0
     */
    public static function formatPer36Stat($total, $minutes): string
    {
        if ($minutes === null || $minutes == 0) {
            return "0.0";
        }
        
        $total = $total ?? 0;
        $per36 = (36 / $minutes) * $total;
        return number_format($per36, 1);
    }

    /**
     * Format a total statistic (counting stat like total points, rebounds, etc.)
     * Returns formatted integer with comma separators for thousands (e.g., "1,234")
     * 
     * @param float|int|null $value The value to format
     * @return string Formatted total as integer with comma separators
     */
    public static function formatTotal($value): string
    {
        $value = $value ?? 0;
        return number_format($value, 0);
    }

    /**
     * Format an average statistic with 2 decimal places
     * Used for career averages and other detailed statistics (e.g., "15.23")
     * 
     * @param float|int|null $value The value to format
     * @return string Formatted value with 2 decimal places
     */
    public static function formatAverage($value): string
    {
        $value = $value ?? 0;
        return number_format($value, 2);
    }

    /**
     * Calculate and format points from field goals, free throws, and three-pointers
     * 
     * @param int $fgm Field goals made
     * @param int $ftm Free throws made
     * @param int $tgm Three-pointers made
     * @return int Total points (FGM*2 + FTM + TGM)
     */
    public static function calculatePoints($fgm, $ftm, $tgm): int
    {
        $fgm = $fgm ?? 0;
        $ftm = $ftm ?? 0;
        $tgm = $tgm ?? 0;
        
        return (2 * $fgm) + $ftm + $tgm;
    }

    /**
     * Safe division that returns 0 instead of throwing an error
     * 
     * @param float|int $numerator
     * @param float|int $denominator
     * @return float Result of division or 0 if denominator is 0
     */
    public static function safeDivide($numerator, $denominator): float
    {
        if ($denominator === null || $denominator == 0) {
            return 0;
        }
        
        $numerator = $numerator ?? 0;
        return $numerator / $denominator;
    }

    /**
     * Format a percentage with custom decimal places
     * 
     * @param float|int|null $made Number made
     * @param float|int|null $attempted Number attempted
     * @param int $decimals Number of decimal places (default: 3)
     * @return string Formatted percentage
     */
    public static function formatPercentageWithDecimals($made, $attempted, int $decimals = 3): string
    {
        if ($attempted === null || $attempted == 0) {
            return number_format(0, $decimals);
        }
        
        $made = $made ?? 0;
        $percentage = $made / $attempted;
        return number_format($percentage, $decimals);
    }

    /**
     * Format a value with custom decimal places
     * 
     * @param float|int|null $value Value to format
     * @param int $decimals Number of decimal places
     * @return string Formatted value
     */
    public static function formatWithDecimals($value, int $decimals): string
    {
        $value = $value ?? 0;
        return number_format($value, $decimals);
    }
}
