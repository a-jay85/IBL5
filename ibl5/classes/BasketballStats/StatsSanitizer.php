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
     * Strictly convert a mixed value to int.
     *
     * Unlike sanitizeInt(), this never uses intval(): an int is returned as is,
     * a numeric string or a float is cast with (int), and everything else
     * (null, '', non-numeric or partially numeric strings, bool, array) is 0.
     * Semantics copied verbatim from the former private DepthChartSnapshot copies.
     */
    public static function toInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        if (is_float($value)) {
            return (int) $value;
        }
        return 0;
    }

    /**
     * Read an int from an array by key, ignoring floats.
     *
     * An int is returned as is and a numeric string is cast with (int).
     * Everything else (missing key, null, float, bool, array, non-numeric
     * string) is 0. Unlike toInt(), a float yields 0. Semantics copied
     * verbatim from the former private DepthChart and DepthChartSnapshot copies.
     *
     * @param array<string, mixed> $data
     */
    public static function intFromArray(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }
        return 0;
    }

    /**
     * Narrow a mixed value to string.
     *
     * Returns strings unchanged and stringifies ints and floats. Anything else
     * (null, bool, array) yields ''. Unlike sanitizeString(), bool is not '1'.
     */
    public static function scalarToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return '';
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
}
