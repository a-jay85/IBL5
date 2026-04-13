<?php

declare(strict_types=1);

namespace Auth;

use Logging\LoggerFactory;

/**
 * Transparent dev-only auto-login for local development.
 *
 * When DEV_AUTO_LOGIN=<username> is set in .env.test and the request
 * originates from localhost, automatically authenticates as that user
 * without requiring login form interaction. This is especially useful
 * for browser-based verification via Chrome DevTools MCP.
 *
 * Safety: three independent guards must ALL pass:
 * 1. Session is not already authenticated
 * 2. SERVER_NAME is localhost/127.0.0.1/main.localhost
 * 3. DEV_AUTO_LOGIN env var or .env.test entry is set to a non-empty username
 *
 * The caller (mainfile.php) also checks for the _no_auto_login cookie
 * which E2E tests set to opt out of auto-authentication.
 */
final class DevAutoLogin
{
    private const ENV_VAR_NAME = 'DEV_AUTO_LOGIN';

    public static function tryAutoLogin(\mysqli $db): void
    {
        // Guard 1: already authenticated — nothing to do
        if (isset($_SESSION['auth_user_id']) && is_int($_SESSION['auth_user_id']) && $_SESSION['auth_user_id'] > 0) {
            return;
        }

        // Guard 2: only on localhost (exact match or *.localhost subdomains for worktrees)
        $serverName = $_SERVER['SERVER_NAME'] ?? null;
        if (!is_string($serverName) || !self::isLocalhost($serverName)) {
            return;
        }

        // Guard 3: DEV_AUTO_LOGIN must be set to a non-empty username
        $username = self::getAutoLoginUsername();
        if ($username === null) {
            return;
        }

        // Look up user ID from auth_users
        $stmt = $db->prepare('SELECT id AS user_id FROM auth_users WHERE username = ? LIMIT 1');
        if ($stmt === false) {
            return;
        }

        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            return;
        }
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!is_array($row) || !isset($row['user_id'])) {
            LoggerFactory::getChannel('auth')->debug('Dev auto-login: user not found', ['username' => $username]);
            return;
        }

        // Mirrors AuthService::startSession() — keep in sync
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['auth_user_id'] = (int) $row['user_id'];
        $_SESSION['auth_username'] = $username;

        LoggerFactory::getChannel('auth')->debug('Dev auto-login activated', ['username' => $username]);
    }

    private static function getAutoLoginUsername(): ?string
    {
        // Check environment variable first (e.g., set in Docker Compose)
        $envValue = getenv(self::ENV_VAR_NAME);
        if (is_string($envValue) && $envValue !== '') {
            return $envValue;
        }

        // Fall back to parsing .env.test (same pattern as TestCookieOverrides::isE2eTesting())
        $envFile = __DIR__ . '/../../.env.test';
        if (!file_exists($envFile)) {
            return null;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return null;
        }

        $prefix = self::ENV_VAR_NAME . '=';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (str_starts_with($line, $prefix)) {
                $value = substr($line, strlen($prefix));
                return $value !== '' ? $value : null;
            }
        }

        return null;
    }

    private static function isLocalhost(string $serverName): bool
    {
        if ($serverName === 'localhost' || $serverName === '127.0.0.1') {
            return true;
        }

        // Accept *.localhost subdomains (e.g., main.localhost, dev-auto-login.localhost)
        return str_ends_with($serverName, '.localhost');
    }
}
