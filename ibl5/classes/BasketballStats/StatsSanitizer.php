<?php

declare(strict_types=1);

namespace BasketballStats;

/**
 * StatsSanitizer - Unified input sanitization for basketball statistics
 * 
 * This class provides consistent sanitization methods for statistical data
 * from database queries, user input, and external sources.
 * 
 * Key features:
 * - Safe type conversion with null handling
 * - Protection against invalid numeric values
 * - Consistent handling of edge cases
 */
class StatsSanitizer
{
    /**
     * Safely convert a value to an integer
     * Returns 0 for null, empty string, or non-numeric values
     *
     * @param string|int|float|bool|null $value Value to convert
     * @return int Integer value or 0
     */
    public static function sanitizeInt(string|int|float|bool|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return intval($value);
    }

    /**
     * Safely convert a value to a float
     * Returns 0.0 for null, empty string, or non-numeric values
     *
     * @param string|int|float|bool|null $value Value to convert
     * @return float Float value or 0.0
     */
    public static function sanitizeFloat(string|int|float|bool|null $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return floatval($value);
    }

    /**
     * Safely convert a value to a string
     * Returns empty string for null values
     *
     * @param string|int|float|bool|null $value Value to convert
     * @return string String value or empty string
     */
    public static function sanitizeString(string|int|float|bool|null $value): string
    {
        if ($value === null) {
            return '';
        }

        return strval($value);
    }

    /**
     * Sanitize an array of database row values
     * Converts all numeric fields to appropriate types
     *
     * @param array<string, string|int|float|bool|null> $row Database row with mixed types
     * @param array<int, string> $intFields Fields that should be integers
     * @param array<int, string> $floatFields Fields that should be floats
     * @return array<string, string|int|float|bool|null> Sanitized row
     */
    public static function sanitizeRow(array $row, array $intFields = [], array $floatFields = []): array
    {
        $sanitized = $row;

        foreach ($intFields as $field) {
            if (isset($row[$field])) {
                $sanitized[$field] = self::sanitizeInt($row[$field]);
            }
        }

        foreach ($floatFields as $field) {
            if (isset($row[$field])) {
                $sanitized[$field] = self::sanitizeFloat($row[$field]);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize a percentage value to ensure it's between 0 and 1
     *
     * @param string|float|int|null $percentage Percentage value
     * @return float Sanitized percentage between 0 and 1
     */
    public static function sanitizePercentage(string|float|int|null $percentage): float
    {
        $value = self::sanitizeFloat($percentage);

        // Clamp between 0 and 1
        if ($value < 0) {
            return 0.0;
        }
        if ($value > 1) {
            return 1.0;
        }

        return $value;
    }

    /**
     * Sanitize a games played value (must be non-negative)
     *
     * @param string|int|null $games Games played
     * @return int Non-negative integer
     */
    public static function sanitizeGames(string|int|null $games): int
    {
        $value = self::sanitizeInt($games);
        return max(0, $value);
    }

    /**
     * Sanitize minutes played (must be non-negative)
     *
     * @param string|float|int|null $minutes Minutes played
     * @return float Non-negative float
     */
    public static function sanitizeMinutes(string|float|int|null $minutes): float
    {
        $value = self::sanitizeFloat($minutes);
        return max(0.0, $value);
    }
}
