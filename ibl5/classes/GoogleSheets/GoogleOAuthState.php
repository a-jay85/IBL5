<?php

declare(strict_types=1);

namespace GoogleSheets;

use Clock\ClockInterface;
use Clock\SystemClock;

/**
 * GoogleOAuthState - the anti-CSRF `state` value for one Google sign-in round trip.
 *
 * One pending flow per session. The value is bound to the user who started it,
 * expires after ten minutes, and is removed on the first consume() whether or
 * not it validates.
 */
final class GoogleOAuthState
{
    public const SESSION_KEY = '_google_oauth_state';
    public const TTL_SECONDS = 600;

    private static ?ClockInterface $clock = null;

    /**
     * Override the clock for testing. Pass null to restore the system clock.
     */
    public static function setTestClock(?ClockInterface $clock): void
    {
        self::$clock = $clock;
    }

    public static function issue(int $userId): string
    {
        self::ensureSessionStarted();

        $state = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = [
            'value' => $state,
            'user_id' => $userId,
            'expires_at' => self::clock()->now() + self::TTL_SECONDS,
        ];

        return $state;
    }

    public static function consume(mixed $submitted, int $userId): bool
    {
        self::ensureSessionStarted();

        $stored = $_SESSION[self::SESSION_KEY] ?? null;
        unset($_SESSION[self::SESSION_KEY]);

        if (!is_array($stored) || !is_string($submitted)) {
            return false;
        }
        $value = $stored['value'] ?? null;
        $boundUser = $stored['user_id'] ?? null;
        $expiresAt = $stored['expires_at'] ?? null;
        if (!is_string($value) || !is_int($boundUser) || !is_int($expiresAt)) {
            return false;
        }

        return hash_equals($value, $submitted)
            && $boundUser === $userId
            && self::clock()->now() < $expiresAt;
    }

    private static function clock(): ClockInterface
    {
        return self::$clock ?? new SystemClock();
    }

    private static function ensureSessionStarted(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
