<?php

declare(strict_types=1);

namespace Auth;

use Auth\Contracts\AuthServiceInterface;
use Database\PdoConnection;
use Delight\Auth\Auth;
use Delight\Auth\AuthError;
use Delight\Auth\DuplicateUsernameException;
use Delight\Auth\EmailNotVerifiedException;
use Delight\Auth\InvalidEmailException;
use Delight\Auth\InvalidPasswordException;
use Delight\Auth\InvalidSelectorTokenPairException;
use Delight\Auth\ResetDisabledException;
use Delight\Auth\TokenExpiredException;
use Delight\Auth\TooManyRequestsException;
use Delight\Auth\UnknownUsernameException;
use Delight\Auth\UserAlreadyExistsException;

/**
 * AuthService - Session-based user authentication via delight-im/auth
 *
 * All authentication is handled by delight-auth (auth_users table).
 * Backward-compatible getCookieArray() for legacy $cookie[] references.
 */
class AuthService implements AuthServiceInterface
{
    private const BCRYPT_COST = 12;
    private const REMEMBER_DURATION_SECONDS = 7776000; // 90 days

    private const SESSION_USER_ID = 'auth_user_id';
    private const SESSION_USERNAME = 'auth_username';

    private \mysqli $db;

    private ?Auth $auth;

    private ?string $lastError = null;

    /** @var array<string, float|int|string|null>|null Cached user info row */
    private ?array $cachedUserInfo = null;

    public function __construct(\mysqli $db, ?Auth $auth = null)
    {
        $this->db = $db;
        $this->auth = $auth;
    }

    /**
     * Get the delight-im/auth instance, creating it lazily if needed.
     */
    private function getAuth(): Auth
    {
        if ($this->auth === null) {
            $this->auth = new Auth(PdoConnection::getInstance(), null, 'auth_', getenv('E2E_TESTING') !== '1');
        }
        return $this->auth;
    }

    public function attempt(string $username, string $password, bool $rememberMe = false): bool
    {
        $this->lastError = null;
        $rememberDuration = $rememberMe ? self::REMEMBER_DURATION_SECONDS : null;

        try {
            $this->getAuth()->loginWithUsername($username, $password, $rememberDuration);
            $this->startSession($this->getAuth()->getUserId(), $username);
            return true;
        } catch (UnknownUsernameException | InvalidPasswordException) {
            return false;
        } catch (EmailNotVerifiedException) {
            $this->lastError = "Please verify your email address.\nCheck your inbox or spam folder for a confirmation link.";
            return false;
        } catch (TooManyRequestsException) {
            $this->lastError = "Too many login attempts.\nPlease try again later.";
            return false;
        } catch (AuthError) {
            return false;
        }
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

    public function isAdmin(): bool
    {
        return $this->hasRole(\Delight\Auth\Role::ADMIN);
    }

    public function hasRole(int $role): bool
    {
        // First check session cache (set on previous call or during login)
        if (isset($_SESSION['auth_roles']) && is_int($_SESSION['auth_roles'])) {
            return ($_SESSION['auth_roles'] & $role) === $role;
        }

        // Fallback: query auth_users for legacy login path
        $username = $this->getUsername();
        if ($username === null) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT roles_mask FROM auth_users WHERE username = ?');
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
        /** @var array{roles_mask: int}|null $row */
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row === null) {
            // Cache the miss so we don't re-query on every page load
            $_SESSION['auth_roles'] = 0;
            return false;
        }

        $rolesMask = $row['roles_mask'];

        // Cache in session for subsequent checks
        $_SESSION['auth_roles'] = $rolesMask;

        return ($rolesMask & $role) === $role;
    }

    public function getUserInfo(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

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

        $stmt = $this->db->prepare('SELECT id AS user_id, username, email AS user_email FROM auth_users WHERE username = ?');
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

        // Legacy defaults for News module backward compat (comment system removed)
        $row['storynum'] = 10;
        $row['umode'] = '';
        $row['uorder'] = 0;
        $row['thold'] = 0;

        $this->cachedUserInfo = $row;
        return $this->cachedUserInfo;
    }

    public function getCookieArray(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $userId = $this->getUserId();
        $username = $this->getUsername();
        if ($userId === null || $username === null) {
            return null;
        }

        return [
            $userId,    // [0] user_id
            $username,  // [1] username
            '',         // [2] password (no longer exposed)
            '10',       // [3] storynum (legacy default)
            '',         // [4] umode
            '0',        // [5] uorder
            '0',        // [6] thold
            '0',        // [7] noscore
            '0',        // [8] ublockon
            '',         // [9] theme
            '4096',     // [10] commentmax
        ];
    }

    public function tryRememberMe(): bool
    {
        // Already authenticated — nothing to do
        if ($this->isAuthenticated()) {
            return false;
        }

        try {
            $auth = $this->getAuth();
        } catch (\Throwable) {
            return false;
        }

        // Delight-auth's constructor calls processRememberDirective(), which
        // checks for a remember cookie and auto-logs the user in if valid.
        if (!$auth->isLoggedIn()) {
            return false;
        }

        // Delight-auth restored the session — mirror it into our own session keys
        $username = $auth->getUsername();
        if ($username === null) {
            return false;
        }

        $this->startSession($auth->getUserId(), $username);
        return true;
    }

    public function logout(): void
    {
        // Clear delight-auth remember cookie and session
        try {
            $auth = $this->getAuth();
            if ($auth->isLoggedIn()) {
                $auth->logOut();
            }
        } catch (\Throwable) {
            // Delight-auth not available — continue with local cleanup
        }

        unset(
            $_SESSION[self::SESSION_USER_ID],
            $_SESSION[self::SESSION_USERNAME],
            $_SESSION['auth_roles'],
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

    public function register(string $email, string $password, string $username, ?callable $emailCallback = null): int
    {
        $this->lastError = null;

        try {
            /** @var int $userId */
            $userId = $this->getAuth()->registerWithUniqueUsername($email, $password, $username, $emailCallback);
            return $userId;
        } catch (InvalidEmailException) {
            $this->fail('The email address is invalid.');
        } catch (InvalidPasswordException) {
            $this->fail('The password is invalid. Please choose a stronger password.');
        } catch (UserAlreadyExistsException) {
            $this->fail('A user with this email address already exists.');
        } catch (DuplicateUsernameException) {
            $this->fail('This username is already taken. Please choose another.');
        } catch (TooManyRequestsException) {
            $this->fail('Too many requests. Please try again later.');
        } catch (AuthError $e) {
            $this->fail('An unexpected error occurred during registration.', $e);
        }
    }

    /**
     * Confirm a user's email and sign them in via delight-auth.
     *
     * @return array{username: string} Confirmed username
     */
    public function confirmEmail(string $selector, string $token): array
    {
        $this->lastError = null;

        try {
            $this->getAuth()->confirmEmailAndSignIn($selector, $token);
            $authUsername = $this->getAuth()->getUsername();
            return ['username' => $authUsername ?? 'User'];
        } catch (InvalidSelectorTokenPairException) {
            $this->fail('mismatch');
        } catch (TokenExpiredException) {
            $this->fail('expired');
        } catch (UserAlreadyExistsException) {
            $this->fail('mismatch');
        } catch (TooManyRequestsException) {
            $this->fail('expired');
        } catch (AuthError $e) {
            $this->fail('expired', $e);
        }
    }

    public function forgotPassword(string $email, callable $callback): void
    {
        $this->lastError = null;

        try {
            $this->getAuth()->forgotPassword($email, $callback);
        } catch (InvalidEmailException) {
            // Don't reveal whether email exists — silently succeed
        } catch (EmailNotVerifiedException) {
            // Don't reveal account status — silently succeed
        } catch (ResetDisabledException) {
            // Don't reveal account status — silently succeed
        } catch (TooManyRequestsException) {
            $this->lastError = 'Too many requests. Please try again later.';
        } catch (AuthError) {
            $this->lastError = 'An error occurred. Please try again later.';
        }
    }

    public function resetPassword(string $selector, string $token, string $newPassword): void
    {
        $this->lastError = null;

        try {
            $this->getAuth()->resetPassword($selector, $token, $newPassword);
        } catch (InvalidSelectorTokenPairException) {
            $this->fail('This password reset link is invalid. Please request a new one.');
        } catch (TokenExpiredException) {
            $this->fail('This password reset link has expired. Please request a new one.');
        } catch (ResetDisabledException) {
            $this->fail('Password reset is disabled for this account.');
        } catch (InvalidPasswordException) {
            $this->fail('The new password is invalid. Please choose a stronger password.');
        } catch (TooManyRequestsException) {
            $this->fail('Too many requests. Please try again later.');
        } catch (AuthError $e) {
            $this->fail('An error occurred while resetting your password.', $e);
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Set the last error message and throw a RuntimeException.
     *
     * Consolidates the repeated catch pattern: set lastError, then re-throw.
     *
     * @throws \RuntimeException Always
     */
    private function fail(string $message, ?\Throwable $previous = null): never
    {
        $this->lastError = $message;
        throw new \RuntimeException($message, 0, $previous);
    }


}
