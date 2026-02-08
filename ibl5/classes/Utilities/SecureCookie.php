<?php

declare(strict_types=1);

namespace Utilities;

/**
 * Secure Cookie Utility
 *
 * Provides a wrapper for setcookie() that automatically applies
 * security best practices:
 * - HttpOnly: Prevents JavaScript access (XSS protection)
 * - Secure: Only sent over HTTPS
 * - SameSite: Prevents CSRF via cookie
 *
 * Usage:
 * SecureCookie::set('name', 'value', time() + 3600);
 */
class SecureCookie
{
    /**
     * Set a cookie with secure defaults
     *
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $expires Expiration timestamp (0 for session cookie)
     * @param 'Strict'|'Lax'|'None' $samesite SameSite attribute
     * @return bool True on success
     */
    public static function set(
        string $name,
        string $value,
        int $expires = 0,
        string $samesite = 'Strict'
    ): bool {
        // Detect HTTPS
        $isSecure = self::isHttps();

        /** @var array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Strict'|'Lax'|'None'} $cookieOptions */
        $cookieOptions = [
            'expires' => $expires,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => $samesite,
        ];

        return setcookie($name, $value, $cookieOptions);
    }

    /**
     * Delete a cookie
     *
     * @param string $name Cookie name
     * @return bool True on success
     */
    public static function delete(string $name): bool
    {
        // Detect HTTPS
        $isSecure = self::isHttps();

        /** @var array{expires: int, path: string, domain: string, secure: bool, httponly: bool, samesite: 'Strict'} $cookieOptions */
        $cookieOptions = [
            'expires' => 1, // Expire in the past
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        return setcookie($name, '', $cookieOptions);
    }

    /**
     * Set a cookie with Lax SameSite for cross-site top-level navigation
     *
     * Use this when the cookie should be sent on top-level GET requests from external sites.
     *
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $expires Expiration timestamp
     * @return bool True on success
     */
    public static function setLax(string $name, string $value, int $expires = 0): bool
    {
        return self::set($name, $value, $expires, 'Lax');
    }

    /**
     * Detect if the current request is over HTTPS
     */
    private static function isHttps(): bool
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }

        return false;
    }
}
