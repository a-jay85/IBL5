<?php

declare(strict_types=1);

namespace Debug;

use Clock\ClockInterface;
use Clock\SystemClock;
use Debug\Contracts\DebugSessionInterface;

class DebugSession implements DebugSessionInterface
{
    private const SESSION_KEY = 'debug_view_all_extensions';
    public const COOKIE_NAME = 'ibl_debug_extensions';
    private const COOKIE_EXPIRY_DAYS = 30;

    private bool $isAdmin;
    private ClockInterface $clock;

    public function __construct(?string $username, ?string $serverName, ?string $cookieValue = null, bool $isE2ETesting = false, ?ClockInterface $clock = null)
    {
        $this->clock = $clock ?? new SystemClock();
        $this->isAdmin = $username === 'A-Jay' && (self::isLocalhost($serverName) || $isE2ETesting);

        if ($this->isAdmin) {
            $this->hydrateSessionFromCookie($cookieValue);
        }
    }

    /**
     * Named constructor for module entry points, which may not use `new`.
     * The caller passes the acting identity, so no factory can build this.
     */
    public static function forRequest(?string $username, ?string $serverName, ?string $cookieValue = null): self
    {
        return new self($username, $serverName, $cookieValue);
    }

    public function isDebugAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function isViewAllExtensionsEnabled(): bool
    {
        if (!$this->isAdmin) {
            return false;
        }

        return ($_SESSION[self::SESSION_KEY] ?? null) === true;
    }

    public function toggleViewAllExtensions(): void
    {
        if (!$this->isAdmin) {
            return;
        }

        $current = ($_SESSION[self::SESSION_KEY] ?? null) === true;
        $newState = !$current;

        $_SESSION[self::SESSION_KEY] = $newState;

        if ($newState) {
            setcookie(self::COOKIE_NAME, '1', [
                'expires' => $this->cookieExpiry(true),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie(self::COOKIE_NAME, '', [
                'expires' => $this->cookieExpiry(false),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    /**
     * Unix expiry for the debug cookie: COOKIE_EXPIRY_DAYS ahead when enabling,
     * one hour in the past when disabling (tells the browser to delete it).
     */
    public function cookieExpiry(bool $enable): int
    {
        $now = $this->clock->now();

        return $enable ? $now + 86400 * self::COOKIE_EXPIRY_DAYS : $now - 3600;
    }

    private static function isLocalhost(?string $serverName): bool
    {
        if ($serverName === null) {
            return false;
        }

        return $serverName === 'localhost'
            || str_ends_with($serverName, '.localhost');
    }

    private function hydrateSessionFromCookie(?string $cookieValue): void
    {
        if (($_SESSION[self::SESSION_KEY] ?? null) === true) {
            return;
        }

        if ($cookieValue === '1') {
            $_SESSION[self::SESSION_KEY] = true;
        }
    }
}
