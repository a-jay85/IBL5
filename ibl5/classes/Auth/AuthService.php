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
            $this->auth = new Auth(PdoConnection::getInstance(), null, 'auth_', true);
        }
        return $this->auth;
    }

    public function attempt(string $username, string $password): bool
    {
        $this->lastError = null;

        // 1. Try delight-auth first (handles users registered via the new flow)
        try {
            $this->getAuth()->loginWithUsername($username, $password);

            // Delight-auth login succeeded — look up nuke_users row for session
            $nukeUserId = $this->getNukeUserId($username);
            if ($nukeUserId === null) {
                return false;
            }

            $this->startSession($nukeUserId, $username);

            // Sync password hash to nuke_users so future logins can use the faster legacy path
            $this->syncPasswordToNukeUsers($username, $password);

            return true;
        } catch (UnknownUsernameException) {
            // Not in delight-auth — fall through to legacy nuke_users path
        } catch (InvalidPasswordException) {
            // User IS in delight-auth but password is wrong — do NOT fall through to legacy
            // path, otherwise an old synced hash in nuke_users could accept a stale password
            return false;
        } catch (EmailNotVerifiedException) {
            $this->lastError = 'Please verify your email address before logging in. Check your inbox for a confirmation link.';
            return false;
        } catch (TooManyRequestsException) {
            $this->lastError = 'Too many login attempts. Please try again later.';
            return false;
        } catch (AuthError) {
            // Unexpected auth error — fall through to legacy path
        } catch (\Error $e) {
            // Delight-auth classes not available (e.g. Composer autoloader not loaded) —
            // fall through to legacy path so login still works
        }

        // 2. Legacy nuke_users fallback (bcrypt / MD5 transitional)
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

        // Skip rows with delight-auth placeholder — these must authenticate via delight-auth
        if (str_starts_with($storedHash, 'delight-auth:')) {
            return false;
        }

        // 2a. Try bcrypt verification
        if (password_verify($password, $storedHash)) {
            // Re-hash if cost parameters have changed
            if (password_needs_rehash($storedHash, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST])) {
                $this->upgradeHash($row['username'], $password);
            }
            $this->startSession($row['user_id'], $row['username']);
            return true;
        }

        // 2b. MD5 transitional fallback — upgrade hash on success
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

    public function register(string $email, string $password, string $username, ?callable $emailCallback = null): int
    {
        $this->lastError = null;

        try {
            /** @var int $userId */
            $userId = $this->getAuth()->registerWithUniqueUsername($email, $password, $username, $emailCallback);
            return $userId;
        } catch (InvalidEmailException) {
            $this->lastError = 'The email address is invalid.';
            throw new \RuntimeException($this->lastError);
        } catch (InvalidPasswordException) {
            $this->lastError = 'The password is invalid. Please choose a stronger password.';
            throw new \RuntimeException($this->lastError);
        } catch (UserAlreadyExistsException) {
            $this->lastError = 'A user with this email address already exists.';
            throw new \RuntimeException($this->lastError);
        } catch (DuplicateUsernameException) {
            $this->lastError = 'This username is already taken. Please choose another.';
            throw new \RuntimeException($this->lastError);
        } catch (TooManyRequestsException) {
            $this->lastError = 'Too many requests. Please try again later.';
            throw new \RuntimeException($this->lastError);
        } catch (AuthError $e) {
            $this->lastError = 'An unexpected error occurred during registration.';
            throw new \RuntimeException($this->lastError, 0, $e);
        }
    }

    /**
     * @see AuthServiceInterface::confirmEmail()
     *
     * @return array<int|string, string>
     */
    public function confirmEmail(string $selector, string $token): array
    {
        $this->lastError = null;

        try {
            /** @var array<int|string, string> $emailBeforeAndAfter */
            $emailBeforeAndAfter = $this->getAuth()->confirmEmailAndSignIn($selector, $token);

            // Create nuke_users profile so the rest of the site sees this user
            $authUserId = $this->getAuth()->getUserId();
            $authUsername = $this->getAuth()->getUsername();
            $authEmail = $this->getAuth()->getEmail();

            if ($authUsername !== null && $authEmail !== null) {
                $this->createNukeUserProfile($authUserId, $authUsername, $authEmail);
            }

            return $emailBeforeAndAfter;
        } catch (InvalidSelectorTokenPairException) {
            $this->lastError = 'mismatch';
            throw new \RuntimeException($this->lastError);
        } catch (TokenExpiredException) {
            $this->lastError = 'expired';
            throw new \RuntimeException($this->lastError);
        } catch (UserAlreadyExistsException) {
            $this->lastError = 'mismatch';
            throw new \RuntimeException($this->lastError);
        } catch (TooManyRequestsException) {
            $this->lastError = 'expired';
            throw new \RuntimeException($this->lastError);
        } catch (AuthError $e) {
            $this->lastError = 'expired';
            throw new \RuntimeException($this->lastError, 0, $e);
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
        } catch (AuthError $e) {
            $this->lastError = 'An error occurred. Please try again later.';
        }
    }

    public function resetPassword(string $selector, string $token, string $newPassword): void
    {
        $this->lastError = null;

        try {
            /** @var array<string, string> $resetData */
            $resetData = $this->getAuth()->resetPassword($selector, $token, $newPassword);

            // Also update password in nuke_users for backward compat
            $newHash = $this->hashPassword($newPassword);
            $stmt = $this->db->prepare('UPDATE nuke_users SET user_password = ? WHERE user_email = ?');
            if ($stmt !== false) {
                $email = $resetData['email'];
                $stmt->bind_param('ss', $newHash, $email);
                $stmt->execute();
                $stmt->close();
            }
        } catch (InvalidSelectorTokenPairException) {
            $this->lastError = 'This password reset link is invalid. Please request a new one.';
            throw new \RuntimeException($this->lastError);
        } catch (TokenExpiredException) {
            $this->lastError = 'This password reset link has expired. Please request a new one.';
            throw new \RuntimeException($this->lastError);
        } catch (ResetDisabledException) {
            $this->lastError = 'Password reset is disabled for this account.';
            throw new \RuntimeException($this->lastError);
        } catch (InvalidPasswordException) {
            $this->lastError = 'The new password is invalid. Please choose a stronger password.';
            throw new \RuntimeException($this->lastError);
        } catch (TooManyRequestsException) {
            $this->lastError = 'Too many requests. Please try again later.';
            throw new \RuntimeException($this->lastError);
        } catch (AuthError $e) {
            $this->lastError = 'An error occurred while resetting your password.';
            throw new \RuntimeException($this->lastError, 0, $e);
        }
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Create a nuke_users profile for a newly confirmed user.
     *
     * This allows the rest of the site (which queries nuke_users) to see the user.
     */
    private function createNukeUserProfile(int $authUserId, string $username, string $email): void
    {
        // Check if profile already exists
        $checkStmt = $this->db->prepare('SELECT user_id FROM nuke_users WHERE username = ?');
        if ($checkStmt === false) {
            return;
        }
        $checkStmt->bind_param('s', $username);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult !== false && $checkResult->num_rows > 0) {
            $checkStmt->close();
            return; // Profile already exists
        }
        $checkStmt->close();

        $regdate = date('M d, Y');
        $defaultTheme = '';
        $stmt = $this->db->prepare(
            'INSERT INTO nuke_users (username, user_email, user_regdate, user_password, theme, user_lang) VALUES (?, ?, ?, ?, ?, ?)'
        );
        if ($stmt === false) {
            return;
        }
        // Store a placeholder bcrypt hash — the real password is managed by delight-auth
        $placeholderHash = 'delight-auth:' . $authUserId;
        $language = 'english';
        $stmt->bind_param('ssssss', $username, $email, $regdate, $placeholderHash, $defaultTheme, $language);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Sync the password hash to nuke_users after a successful delight-auth login.
     *
     * This allows future logins to succeed via the faster nuke_users bcrypt path
     * without needing a delight-auth round-trip.
     */
    private function syncPasswordToNukeUsers(string $username, string $plaintext): void
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

    /**
     * Look up the nuke_users user_id for a given username.
     */
    private function getNukeUserId(string $username): ?int
    {
        $stmt = $this->db->prepare('SELECT user_id FROM nuke_users WHERE username = ?');
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
        /** @var array{user_id: int}|null $row */
        $row = $result->fetch_assoc();
        $stmt->close();

        return $row !== null ? $row['user_id'] : null;
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
