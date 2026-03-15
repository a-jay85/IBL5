<?php

declare(strict_types=1);

namespace Utilities;

/**
 * Reads E2E test state overrides from a cookie.
 *
 * When E2E_TESTING=1, Playwright sets a `_test_overrides` cookie containing
 * URL-encoded JSON of setting overrides. This class decodes and validates
 * the cookie against an allowlist, returning overrides that callers merge
 * into their settings arrays. This eliminates DB-level race conditions
 * between parallel E2E tests.
 */
final class TestCookieOverrides
{
    /**
     * Settings that may be overridden via cookie.
     *
     * @var list<string>
     */
    private const ALLOWED_KEYS = [
        'Current Season Phase',
        'Allow Trades',
        'Allow Waiver Moves',
        'Show Draft Link',
        'Free Agency Notifications',
        'Trivia Mode',
    ];

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /**
     * Get validated overrides from the _test_overrides cookie.
     *
     * Returns an empty array when:
     * - E2E_TESTING is not enabled
     * - No cookie is set
     * - Cookie contains invalid JSON
     *
     * @return array<string, string>
     */
    public static function getOverrides(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        self::$cache = [];

        if (!self::isE2eTesting()) {
            return self::$cache;
        }

        $raw = $_COOKIE['_test_overrides'] ?? null;
        if (!is_string($raw) || $raw === '') {
            return self::$cache;
        }

        // PHP auto-decodes URL-encoded cookie values, so $raw is already decoded JSON
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return self::$cache;
        }

        $allowed = array_flip(self::ALLOWED_KEYS);

        /** @var mixed $value */
        foreach ($decoded as $key => $value) {
            if (is_string($key) && isset($allowed[$key]) && is_string($value)) {
                self::$cache[$key] = $value;
            }
        }

        return self::$cache;
    }

    /**
     * Reset cached overrides (for testing).
     */
    public static function resetCache(): void
    {
        self::$cache = null;
    }

    /**
     * Check if E2E testing is enabled via environment or .env.test file.
     */
    private static function isE2eTesting(): bool
    {
        if (getenv('E2E_TESTING') === '1') {
            return true;
        }

        $envFile = __DIR__ . '/../../.env.test';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#') {
                        continue;
                    }
                    if ($line === 'E2E_TESTING=1') {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
