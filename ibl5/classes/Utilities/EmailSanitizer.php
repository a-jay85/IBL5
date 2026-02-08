<?php

declare(strict_types=1);

namespace Utilities;

/**
 * Email Sanitization Utility
 *
 * Provides static methods for safely preparing strings for email headers
 * to prevent email header injection attacks.
 *
 * Email header injection can occur when attackers inject newlines (\r\n)
 * followed by additional headers like CC, BCC, or Content-Type.
 */
class EmailSanitizer
{
    /**
     * Sanitize a string for use in email subject or other header fields
     *
     * Removes all characters that could enable header injection:
     * - Carriage return (\r, ASCII 13)
     * - Line feed (\n, ASCII 10)
     * - Null byte (\0, ASCII 0)
     *
     * Also removes other control characters (ASCII 0-31) except tab.
     *
     * @param string $value The value to sanitize
     * @return string The sanitized value safe for email headers
     */
    public static function sanitizeHeader(string $value): string
    {
        // Remove all control characters except tab (ASCII 9)
        // This includes \r (13), \n (10), and \0 (0)
        $sanitized = preg_replace('/[\x00-\x08\x0A-\x1F]/', '', $value);

        // Ensure we have a string result (preg_replace can return null on error)
        if ($sanitized === null) {
            // Fallback: just strip obvious header injection characters
            $sanitized = str_replace(["\r", "\n", "\0"], '', $value);
        }

        // Also strip any encoded newlines
        $sanitized = str_replace(['%0A', '%0D', '%0a', '%0d'], '', $sanitized);

        return $sanitized;
    }

    /**
     * Sanitize email subject specifically
     *
     * In addition to header sanitization, this also:
     * - Strips HTML tags
     * - Limits length to prevent abuse
     *
     * @param string $subject The email subject
     * @param int $maxLength Maximum subject length (default 255)
     * @return string The sanitized subject
     */
    public static function sanitizeSubject(string $subject, int $maxLength = 255): string
    {
        // First strip HTML tags
        $sanitized = strip_tags($subject);

        // Apply header sanitization
        $sanitized = self::sanitizeHeader($sanitized);

        // Limit length
        if (mb_strlen($sanitized) > $maxLength) {
            $sanitized = mb_substr($sanitized, 0, $maxLength);
        }

        return $sanitized;
    }

    /**
     * Validate an email address
     *
     * @param string $email The email address to validate
     * @return bool True if valid, false otherwise
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
