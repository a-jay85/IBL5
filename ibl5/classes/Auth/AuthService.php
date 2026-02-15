<?php

declare(strict_types=1);

namespace Auth;

use Auth\Contracts\AuthServiceInterface;
use Database\PdoConnection;
use Delight\Auth\Auth;
use Delight\Auth\Role;

/**
 * AuthService - Wraps delight-im/auth for user authentication
 *
 * Provides login, registration, email verification, password reset,
 * remember-me, login throttling, and admin role checking while maintaining
 * backward compatibility with legacy PHP-Nuke $cookie/$userinfo globals.
 *
 * @phpstan-import-type UserRow from AuthServiceInterface
 */
class AuthService implements AuthServiceInterface
{
    private const BCRYPT_COST = 12;

    private const AUTH_TABLE_PREFIX = 'auth_';

    private \mysqli $db;
    private Auth $auth;
    private ?string $lastError = null;

    /** @var array<string, float|int|string|null>|null Cached user info row */
    private ?array $cachedUserInfo = null;

    /**
     * @param \mysqli $db MySQLi connection for profile queries (nuke_users)
     * @param Auth|null $auth Optional Auth instance (for testing; production uses PdoConnection singleton)
     */
    public function __construct(\mysqli $db, ?Auth $auth = null)
    {
        $this->db = $db;
        // Suppress E_DEPRECATED during Auth construction — delight-im/auth v9.0
        // uses implicitly nullable parameters which PHP 8.4 deprecates.
        // No upstream fix available; v9.0.0 is the latest release.
        $previousLevel = error_reporting(error_reporting() & ~E_DEPRECATED);
        try {
            $this->auth = $auth ?? new Auth(
                PdoConnection::getInstance(),
                null,
                self::AUTH_TABLE_PREFIX,
                true,
            );
        } finally {
            error_reporting($previousLevel);
        }
    }

    public function attempt(string $username, string $password, ?int $rememberDuration = null): bool
    {
        $this->lastError = null;

        try {
            $this->auth->loginWithUsername($username, $password, $rememberDuration);
            $this->cachedUserInfo = null;
            return true;
        } catch (\Delight\Auth\UnknownUsernameException) {
            $this->lastError = 'Invalid username or password.';
            return false;
        } catch (\Delight\Auth\AmbiguousUsernameException) {
            $this->lastError = 'Invalid username or password.';
            return false;
        } catch (\Delight\Auth\InvalidPasswordException) {
            $this->lastError = 'Invalid username or password.';
            return false;
        } catch (\Delight\Auth\EmailNotVerifiedException) {
            $this->lastError = 'Please verify your email address before logging in.';
            return false;
        } catch (\Delight\Auth\TooManyRequestsException) {
            $this->lastError = 'Too many login attempts. Please try again later.';
            return false;
        } catch (\Delight\Auth\AuthError) {
            $this->lastError = 'An authentication error occurred. Please try again.';
            return false;
        }
    }

    public function isAuthenticated(): bool
    {
        return $this->auth->isLoggedIn();
    }

    public function getUserId(): ?int
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return $this->auth->getUserId();
    }

    public function getUsername(): ?string
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return $this->auth->getUsername();
    }

    public function getUserInfo(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $username = $this->getUsername();
        if ($username === null) {
            return null;
        }

        // Return cached info if available and username matches
        if ($this->cachedUserInfo !== null) {
            $cachedUsername = $this->cachedUserInfo['username'] ?? '';
            \assert(is_string($cachedUsername));
            if ($cachedUsername === $username) {
                return $this->cachedUserInfo;
            }
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
        try {
            $this->auth->logOut();
        } catch (\Delight\Auth\AuthError) {
            // Best-effort logout — session destruction may fail in edge cases
        }
        $this->cachedUserInfo = null;
    }

    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);
    }

    public function isAdmin(): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        try {
            return $this->auth->hasRole(Role::ADMIN);
        } catch (\Delight\Auth\AuthError) {
            return false;
        }
    }

    public function register(string $email, string $password, string $username, ?callable $emailCallback = null): int
    {
        $this->lastError = null;

        try {
            $userId = $this->auth->registerWithUniqueUsername($email, $password, $username, $emailCallback);

            // Create a matching profile row in nuke_users
            $this->createNukeUserProfile($userId, $username, $email, $password);

            return $userId;
        } catch (\Delight\Auth\InvalidEmailException) {
            $this->lastError = 'The email address is invalid.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\InvalidPasswordException) {
            $this->lastError = 'The password is invalid or too short.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\UserAlreadyExistsException) {
            $this->lastError = 'A user with this email address already exists.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\DuplicateUsernameException) {
            $this->lastError = 'This username is already taken.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\TooManyRequestsException) {
            $this->lastError = 'Too many registration attempts. Please try again later.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\AuthError $e) {
            $this->lastError = 'Registration failed. Please try again.';
            throw new \RuntimeException($this->lastError, 0, $e);
        }
    }

    public function confirmEmail(string $selector, string $token): array
    {
        $this->lastError = null;

        try {
            $emailPair = $this->auth->confirmEmailAndSignIn($selector, $token);
            /** @var array<string, mixed> */
            return ['old_email' => $emailPair[0] ?? null, 'new_email' => $emailPair[1] ?? null];
        } catch (\Delight\Auth\InvalidSelectorTokenPairException) {
            $this->lastError = 'Invalid or expired confirmation link.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\TokenExpiredException) {
            $this->lastError = 'This confirmation link has expired. Please register again.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\UserAlreadyExistsException) {
            $this->lastError = 'This email has already been confirmed.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\TooManyRequestsException) {
            $this->lastError = 'Too many attempts. Please try again later.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\AuthError $e) {
            $this->lastError = 'Email confirmation failed. Please try again.';
            throw new \RuntimeException($this->lastError, 0, $e);
        }
    }

    public function forgotPassword(string $email, callable $callback): void
    {
        $this->lastError = null;

        try {
            $this->auth->forgotPassword($email, $callback);
        } catch (\Delight\Auth\InvalidEmailException) {
            // Silently ignore — don't reveal whether the email exists
        } catch (\Delight\Auth\EmailNotVerifiedException) {
            // Silently ignore — don't reveal account status
        } catch (\Delight\Auth\ResetDisabledException) {
            // Silently ignore — don't reveal account status
        } catch (\Delight\Auth\TooManyRequestsException) {
            $this->lastError = 'Too many password reset attempts. Please try again later.';
        } catch (\Delight\Auth\AuthError) {
            // Silently ignore — don't reveal internal errors for password reset
        }
    }

    public function resetPassword(string $selector, string $token, string $newPassword): void
    {
        $this->lastError = null;

        try {
            $this->auth->resetPassword($selector, $token, $newPassword);
        } catch (\Delight\Auth\InvalidSelectorTokenPairException) {
            $this->lastError = 'Invalid or expired password reset link.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\TokenExpiredException) {
            $this->lastError = 'This password reset link has expired. Please request a new one.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\ResetDisabledException) {
            $this->lastError = 'Password reset is not available for this account.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\InvalidPasswordException) {
            $this->lastError = 'The new password is invalid or too short.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\TooManyRequestsException) {
            $this->lastError = 'Too many attempts. Please try again later.';
            throw new \RuntimeException($this->lastError);
        } catch (\Delight\Auth\AuthError $e) {
            $this->lastError = 'Password reset failed. Please try again.';
            throw new \RuntimeException($this->lastError, 0, $e);
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Create a matching nuke_users profile row for a newly registered user
     *
     * This ensures legacy code that queries nuke_users for profile data continues working.
     */
    private function createNukeUserProfile(int $userId, string $username, string $email, string $password): void
    {
        $hashedPassword = $this->hashPassword($password);
        $regDate = date('F d, Y');

        $stmt = $this->db->prepare(
            'INSERT INTO nuke_users (user_id, username, user_email, user_password, user_regdate, storynum, umode, uorder, thold, noscore, ublockon, theme, commentmax) VALUES (?, ?, ?, ?, ?, 10, \'\', 0, 0, 0, 0, \'\', 4096)',
        );
        if ($stmt === false) {
            return;
        }
        $stmt->bind_param('issss', $userId, $username, $email, $hashedPassword, $regDate);
        $stmt->execute();
        $stmt->close();
    }
}
