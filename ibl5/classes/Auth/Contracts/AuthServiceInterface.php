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
}
