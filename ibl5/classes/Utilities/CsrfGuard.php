<?php

declare(strict_types=1);

namespace Utilities;

/**
 * CSRF (Cross-Site Request Forgery) Protection Utility
 *
 * Provides methods for generating and validating CSRF tokens
 * to protect forms from cross-site request forgery attacks.
 *
 * Usage:
 * - Call generateToken() in forms to create a hidden input field
 * - Call validateToken() in POST handlers to verify the token
 *
 * Tokens are stored in the PHP session with a 4-hour expiration.
 *
 * @phpstan-type TokenData array{token: string, expires: int}
 * @phpstan-type FormTokens array<string, list<TokenData>>
 */
class CsrfGuard
{
    /**
     * Session key for storing CSRF tokens
     */
    private const SESSION_KEY = '_csrf_tokens';

    /**
     * Token expiration time in seconds (4 hours)
     */
    private const TOKEN_EXPIRATION = 14400;

    /**
     * Maximum number of tokens to store per session
     */
    private const MAX_TOKENS = 10;

    /**
     * Generate a CSRF token and return as HTML hidden input
     *
     * Creates a cryptographically secure random token, stores it in the session,
     * and returns an HTML hidden input field ready for form inclusion.
     *
     * @param string $formName Optional form identifier for multiple forms on one page
     * @return string HTML hidden input element with the CSRF token
     */
    public static function generateToken(string $formName = 'default'): string
    {
        self::ensureSessionStarted();

        $token = bin2hex(random_bytes(32));
        self::storeToken($formName, $token);

        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }

    /**
     * Generate a raw CSRF token without HTML wrapper
     *
     * Useful when token needs to be embedded in JavaScript or non-form contexts.
     *
     * @param string $formName Optional form identifier
     * @return string Raw token string
     */
    public static function generateRawToken(string $formName = 'default'): string
    {
        self::ensureSessionStarted();

        $token = bin2hex(random_bytes(32));
        self::storeToken($formName, $token);

        return $token;
    }

    /**
     * Validate a submitted CSRF token
     *
     * Checks if the submitted token matches a stored token for the given form.
     * Tokens are single-use and are removed after successful validation.
     *
     * @param string $submittedToken The token from the POST request
     * @param string $formName Optional form identifier (must match generateToken call)
     * @return bool True if token is valid, false otherwise
     */
    public static function validateToken(string $submittedToken, string $formName = 'default'): bool
    {
        self::ensureSessionStarted();

        if ($submittedToken === '') {
            return false;
        }

        $tokens = self::getStoredTokens($formName);

        foreach ($tokens as $index => $tokenData) {
            // Check expiration
            if (time() > $tokenData['expires']) {
                continue;
            }

            // Use hash_equals for timing-safe comparison
            if (hash_equals($tokenData['token'], $submittedToken)) {
                // Remove used token (single-use)
                self::removeToken($formName, $index);
                return true;
            }
        }

        return false;
    }

    /**
     * Get the token from POST data
     *
     * Convenience method to extract token from $_POST array.
     *
     * @return string The submitted token or empty string if not present
     */
    public static function getSubmittedToken(): string
    {
        $token = $_POST['_csrf_token'] ?? '';
        return is_string($token) ? $token : '';
    }

    /**
     * Validate token from POST data directly
     *
     * Convenience method combining getSubmittedToken and validateToken.
     *
     * @param string $formName Optional form identifier
     * @return bool True if valid, false otherwise
     */
    public static function validateSubmittedToken(string $formName = 'default'): bool
    {
        return self::validateToken(self::getSubmittedToken(), $formName);
    }

    /**
     * Clear all tokens for a form (e.g., on logout)
     *
     * @param string $formName Form identifier or 'all' to clear everything
     */
    public static function clearTokens(string $formName = 'all'): void
    {
        self::ensureSessionStarted();

        $allTokens = self::getAllTokens();
        if ($allTokens === null) {
            return;
        }

        if ($formName === 'all') {
            unset($_SESSION[self::SESSION_KEY]);
        } else {
            unset($allTokens[$formName]);
            $_SESSION[self::SESSION_KEY] = $allTokens;
        }
    }

    /**
     * Ensure PHP session is started
     */
    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Get all tokens from session with type safety
     *
     * @return FormTokens|null
     */
    private static function getAllTokens(): ?array
    {
        if (!isset($_SESSION[self::SESSION_KEY])) {
            return null;
        }

        $data = $_SESSION[self::SESSION_KEY];
        if (!is_array($data)) {
            return null;
        }

        /** @var FormTokens */
        return $data;
    }

    /**
     * Store a token in the session
     */
    private static function storeToken(string $formName, string $token): void
    {
        $allTokens = self::getAllTokens();

        if ($allTokens === null) {
            $allTokens = [];
        }

        if (!isset($allTokens[$formName])) {
            $allTokens[$formName] = [];
        }

        // Add new token
        $allTokens[$formName][] = [
            'token' => $token,
            'expires' => time() + self::TOKEN_EXPIRATION,
        ];

        // Store back
        $_SESSION[self::SESSION_KEY] = $allTokens;

        // Cleanup: remove expired tokens and limit total count
        self::cleanupTokens($formName);
    }

    /**
     * Get stored tokens for a form
     *
     * @return list<TokenData>
     */
    private static function getStoredTokens(string $formName): array
    {
        $allTokens = self::getAllTokens();

        if ($allTokens === null || !isset($allTokens[$formName])) {
            return [];
        }

        return $allTokens[$formName];
    }

    /**
     * Remove a specific token by index
     */
    private static function removeToken(string $formName, int $index): void
    {
        $allTokens = self::getAllTokens();

        if ($allTokens === null || !isset($allTokens[$formName][$index])) {
            return;
        }

        unset($allTokens[$formName][$index]);
        // Re-index array
        $allTokens[$formName] = array_values($allTokens[$formName]);
        $_SESSION[self::SESSION_KEY] = $allTokens;
    }

    /**
     * Cleanup expired tokens and enforce maximum count
     */
    private static function cleanupTokens(string $formName): void
    {
        $allTokens = self::getAllTokens();

        if ($allTokens === null || !isset($allTokens[$formName])) {
            return;
        }

        $currentTime = time();
        $tokens = $allTokens[$formName];

        // Remove expired tokens
        $tokens = array_filter($tokens, function (array $tokenData) use ($currentTime): bool {
            return $currentTime <= $tokenData['expires'];
        });

        // Keep only the most recent tokens if over limit
        if (count($tokens) > self::MAX_TOKENS) {
            $tokens = array_slice($tokens, -self::MAX_TOKENS);
        }

        $allTokens[$formName] = array_values($tokens);
        $_SESSION[self::SESSION_KEY] = $allTokens;
    }
}
