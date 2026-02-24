<?php

declare(strict_types=1);

namespace Utilities;

/**
 * HTML Output Sanitization Utility
 *
 * Provides static methods for safely preparing strings for HTML output.
 */
class HtmlSanitizer
{
    /**
     * Safely prepares a value for HTML output.
     * Handles multiple variable types with appropriate sanitization.
     *
     * @param mixed $value The value to prepare for HTML output (string, int, float, bool, array, object, null)
     * @param int $flags Optional htmlspecialchars flags (default: ENT_QUOTES | ENT_HTML5)
     */
    public static function safeHtmlOutput(mixed $value, int $flags = ENT_QUOTES | ENT_HTML5): string
    {
        // Null values become empty string (identical behavior in echo/template output context)
        if ($value === null) {
            return '';
        }

        // Booleans convert to string representation (no HTML escaping needed)
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // Numbers (int, float) are safe for HTML output - no escaping needed
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        // Strings: remove SQL escaping and apply HTML entity encoding
        if (is_string($value)) {
            $unescaped = stripslashes($value);
            return htmlspecialchars($unescaped, $flags, 'UTF-8');
        }

        // Arrays and objects: JSON-encode and sanitize the resulting string
        if (is_array($value) || is_object($value)) {
            $json = json_encode($value);
            if ($json === false) {
                return '';
            }
            return htmlspecialchars($json, $flags, 'UTF-8');
        }

        // Remaining types (resource, callable, etc.) cannot be meaningfully rendered
        return '';
    }
}
