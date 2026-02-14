<?php

declare(strict_types=1);

namespace Auth;

use Auth\Contracts\AuthServiceInterface;

/**
 * AuthService - Session-based user authentication with bcrypt password hashing
 *
 * Replaces legacy PHP-Nuke MD5/base64-cookie auth with:
 * - bcrypt password hashing via password_hash() (cost 12)
 * - PHP native sessions as the source of truth
 * - Transparent MD5-to-bcrypt migration on first login
 * - Backward-compatible getCookieArray() for legacy $cookie[] references
 *
 * Admin auth (nuke_authors / is_admin()) is NOT handled here.
 *
 * @phpstan-type UserRow array{user_id: int, username: string, user_password: string, storynum: int, umode: string, uorder: int, thold: int, noscore: int, ublockon: int, theme: string, commentmax: int, user_email: string, user_regdate: string, name: string}
 */
class AuthService implements AuthServiceInterface
{
    private const BCRYPT_COST = 12;

    private const SESSION_USER_ID = 'auth_user_id';
    private const SESSION_USERNAME = 'auth_username';

    private \mysqli $db;

    /** @var array<string, float|int|string|null>|null Cached user info row */
    private ?array $cachedUserInfo = null;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    public function attempt(string $username, string $password): bool
    {
        $stmt = $this->db->prepare(
            'SELECT user_id, username, user_password FROM nuke_users WHERE username = ?'
        );
        if ($stmt === false) {
            return false;
        }
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            return false;
        }
        /** @var array{user_id: int, username: string, user_password: string}|null $row */
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row === null) {
            return false;
        }

        $storedHash = $row['user_password'];

        // 1. Try bcrypt verification first
        if (password_verify($password, $storedHash)) {
            // Re-hash if cost parameters have changed
            if (password_needs_rehash($storedHash, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST])) {
                $this->upgradeHash($row['username'], $password);
            }
            $this->startSession($row['user_id'], $row['username']);
            return true;
        }

        // 2. MD5 transitional fallback — upgrade hash on success
        if ($storedHash !== '' && md5($password) === $storedHash) {
            $this->upgradeHash($row['username'], $password);
            $this->startSession($row['user_id'], $row['username']);
            return true;
        }

        return false;
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION[self::SESSION_USER_ID])
            && is_int($_SESSION[self::SESSION_USER_ID])
            && $_SESSION[self::SESSION_USER_ID] > 0;
    }

    public function getUserId(): ?int
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        $userId = $_SESSION[self::SESSION_USER_ID];
        \assert(is_int($userId));
        return $userId;
    }

    public function getUsername(): ?string
    {
        if (!$this->isAuthenticated()) {
            return null;
        }
        $username = $_SESSION[self::SESSION_USERNAME];
        \assert(is_string($username));
        return $username;
    }

    public function getUserInfo(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        // Return cached info if available and username matches
        if ($this->cachedUserInfo !== null) {
            $cachedUsername = $this->cachedUserInfo['username'] ?? '';
            \assert(is_string($cachedUsername));
            if ($cachedUsername === $this->getUsername()) {
                return $this->cachedUserInfo;
            }
        }

        $username = $this->getUsername();
        if ($username === null) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM nuke_users WHERE username = ?');
        if ($stmt === false) {
            return null;
        }
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            $stmt->close();
            return null;
        }
        /** @var array<string, float|int|string|null>|null $row */
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row === null) {
            return null;
        }

        $this->cachedUserInfo = $row;
        return $this->cachedUserInfo;
    }

    public function getCookieArray(): ?array
    {
        $userInfo = $this->getUserInfo();
        if ($userInfo === null) {
            return null;
        }

        /** @var array{user_id: int, username: string, user_password: string, storynum: int|string, umode: string, uorder: int|string, thold: int|string, noscore: int|string, ublockon: int|string, theme: string, commentmax: int|string} $userInfo */

        // Match the legacy cookie format: uid:username:password:storynum:umode:uorder:thold:noscore:ublockon:theme:commentmax
        return [
            (int) $userInfo['user_id'],
            $userInfo['username'],
            $userInfo['user_password'],
            (string) $userInfo['storynum'],
            (string) $userInfo['umode'],
            (string) $userInfo['uorder'],
            (string) $userInfo['thold'],
            (string) $userInfo['noscore'],
            (string) $userInfo['ublockon'],
            (string) $userInfo['theme'],
            (string) $userInfo['commentmax'],
        ];
    }

    public function logout(): void
    {
        unset(
            $_SESSION[self::SESSION_USER_ID],
            $_SESSION[self::SESSION_USERNAME],
        );
        $this->cachedUserInfo = null;
    }

    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    /**
     * Start an authenticated session for the given user
     */
    private function startSession(int $userId, string $username): void
    {
        // Regenerate session ID to prevent session fixation
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION[self::SESSION_USER_ID] = $userId;
        $_SESSION[self::SESSION_USERNAME] = $username;

        // Clear cached user info so it gets re-fetched
        $this->cachedUserInfo = null;
    }

    /**
     * Upgrade a user's password hash from MD5 to bcrypt
     */
    private function upgradeHash(string $username, string $plaintext): void
    {
        $newHash = $this->hashPassword($plaintext);
        $stmt = $this->db->prepare('UPDATE nuke_users SET user_password = ? WHERE username = ?');
        if ($stmt === false) {
            return;
        }
        $stmt->bind_param('ss', $newHash, $username);
        $stmt->execute();
        $stmt->close();
    }
}
