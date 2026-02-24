<?php

declare(strict_types=1);

namespace Auth\Contracts;

/**
 * AuthServiceInterface - Contract for user authentication
 *
 * Handles password verification, session management, and backward-compatible
 * cookie array generation for legacy PHP-Nuke code.
 */
interface AuthServiceInterface
{
    /**
     * Attempt to authenticate a user with username and password
     *
     * Verifies the password against the stored hash (bcrypt first, MD5 fallback
     * for transitional upgrade). On success, starts a session and returns true.
     *
     * @param string $username The username to authenticate
     * @param string $password The plaintext password
     * @return bool True if authentication succeeded
     */
    public function attempt(string $username, string $password): bool;

    /**
     * Check if the current session is authenticated
     *
     * @return bool True if a user is logged in via session
     */
    public function isAuthenticated(): bool;

    /**
     * Get the authenticated user's ID
     *
     * @return int|null User ID or null if not authenticated
     */
    public function getUserId(): ?int;

    /**
     * Get the authenticated user's username
     *
     * @return string|null Username or null if not authenticated
     */
    public function getUsername(): ?string;

    /**
     * Check if the authenticated user has the ADMIN role
     *
     * @return bool True if the user is an admin
     */
    public function isAdmin(): bool;

    /**
     * Check if the authenticated user has a specific role (bitmask check)
     *
     * @param int $role The role bitmask to check (use \Delight\Auth\Role constants)
     * @return bool True if the user has the role
     */
    public function hasRole(int $role): bool;

    /**
     * Get the full user info row for the authenticated user
     *
     * @return array<string, mixed>|null User row from nuke_users or null if not authenticated
     */
    public function getUserInfo(): ?array;

    /**
     * Get a cookie-format array for backward compatibility with legacy code
     *
     * Returns an indexed array matching the old base64 cookie format:
     *   [0] => user_id, [1] => username, [2] => user_password,
     *   [3] => storynum, [4] => umode, [5] => uorder,
     *   [6] => thold, [7] => noscore, [8] => ublockon,
     *   [9] => theme, [10] => commentmax
     *
     * @return array<int, string|int>|null Cookie array or null if not authenticated
     */
    public function getCookieArray(): ?array;

    /**
     * Log out the current user by clearing session auth keys
     */
    public function logout(): void;

    /**
     * Hash a plaintext password using bcrypt
     *
     * @param string $password The plaintext password to hash
     * @return string The bcrypt hash
     */
    public function hashPassword(string $password): string;

    /**
     * Register a new user via delight-im/auth with unique username enforcement
     *
     * @param string $email User's email address
     * @param string $password Plaintext password
     * @param string $username Desired username
     * @param callable|null $emailCallback Called with (selector, token) to send verification email
     * @return int The new user's ID in the auth system
     * @throws \RuntimeException on validation or duplicate errors (check getLastError() for message)
     */
    public function register(string $email, string $password, string $username, ?callable $emailCallback = null): int;

    /**
     * Confirm a user's email via selector/token and sign them in
     *
     * On success, also creates the nuke_users profile for site-wide compatibility.
     *
     * @param string $selector The selector from the confirmation URL
     * @param string $token The token from the confirmation URL
     * @return array<int|string, string> Email before and after confirmation
     * @throws \RuntimeException on invalid/expired token (check getLastError() for message)
     */
    public function confirmEmail(string $selector, string $token): array;

    /**
     * Initiate a password reset by sending a reset link via callback
     *
     * @param string $email The user's email address
     * @param callable $callback Called with (selector, token) to send the reset email
     * @throws \RuntimeException on error (check getLastError() for message)
     */
    public function forgotPassword(string $email, callable $callback): void;

    /**
     * Reset a user's password using the selector/token from the reset email
     *
     * Also updates the password in nuke_users for backward compatibility.
     *
     * @param string $selector The selector from the reset URL
     * @param string $token The token from the reset URL
     * @param string $newPassword The new plaintext password
     * @throws \RuntimeException on invalid/expired token (check getLastError() for message)
     */
    public function resetPassword(string $selector, string $token, string $newPassword): void;

    /**
     * Get the last error message from a failed auth operation
     *
     * @return string|null The error message, or null if no error
     */
    public function getLastError(): ?string;
}
