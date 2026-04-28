<?php

declare(strict_types=1);

namespace Debug;

use Debug\Contracts\DebugSessionInterface;

class DebugSession implements DebugSessionInterface
{
    private const SESSION_KEY = 'debug_view_all_extensions';
    public const COOKIE_NAME = 'ibl_debug_extensions';
    private const COOKIE_EXPIRY_DAYS = 30;

    private bool $isAdmin;

    public function __construct(?string $username, ?string $serverName, ?string $cookieValue = null, bool $isE2ETesting = false)
    {
        $this->isAdmin = $username === 'A-Jay' && (self::isLocalhost($serverName) || $isE2ETesting);

        if ($this->isAdmin) {
            $this->hydrateSessionFromCookie($cookieValue);
        }
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
                'expires' => time() + 86400 * self::COOKIE_EXPIRY_DAYS,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie(self::COOKIE_NAME, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
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
