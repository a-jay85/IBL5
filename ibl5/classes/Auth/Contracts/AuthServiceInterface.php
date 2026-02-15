<?php

declare(strict_types=1);

namespace Auth\Contracts;

/**
 * AuthServiceInterface - Contract for user authentication
 *
 * Wraps delight-im/auth with backward-compatible methods for legacy PHP-Nuke code.
 * Handles login, registration, email verification, password reset, remember-me,
 * login throttling, and admin role checking.
 *
 * @phpstan-type UserRow array{user_id: int, username: string, user_password: string, storynum: int, umode: string, uorder: int, thold: int, noscore: int, ublockon: int, theme: string, commentmax: int, user_email: string, user_regdate: string, name: string}
 */
interface AuthServiceInterface
{
    /**
     * Attempt to authenticate a user with username and password
     *
     * @param string $username The username to authenticate
     * @param string $password The plaintext password
     * @param int|null $rememberDuration Seconds to keep the user logged in via remember-me cookie (null = session only)
     * @return bool True if authentication succeeded
     */
    public function attempt(string $username, string $password, ?int $rememberDuration = null): bool;

    /**
     * Check if the current session is authenticated
     *
     * @return bool True if a user is logged in
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
     * Log out the current user (clears session and remember-me tokens)
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
     * Check if the authenticated user has the admin role
     *
     * @return bool True if the current user is an admin
     */
    public function isAdmin(): bool;

    /**
     * Register a new user account
     *
     * @param string $email The email address
     * @param string $password The plaintext password
     * @param string $username The username
     * @param callable|null $emailCallback Optional callback that receives ($selector, $token) for email verification
     * @return int The new user's ID
     */
    public function register(string $email, string $password, string $username, ?callable $emailCallback = null): int;

    /**
     * Confirm a user's email address using selector and token from the verification link
     *
     * @param string $selector The selector from the verification link
     * @param string $token The token from the verification link
     * @return array<string, mixed> Confirmation result data
     */
    public function confirmEmail(string $selector, string $token): array;

    /**
     * Initiate a password reset for the given email address
     *
     * @param string $email The user's email address
     * @param callable $callback Callback that receives ($selector, $token) for the reset email
     */
    public function forgotPassword(string $email, callable $callback): void;

    /**
     * Reset a user's password using selector and token from the reset link
     *
     * @param string $selector The selector from the reset link
     * @param string $token The token from the reset link
     * @param string $newPassword The new plaintext password
     */
    public function resetPassword(string $selector, string $token, string $newPassword): void;

    /**
     * Get the last error message from a failed auth operation
     *
     * @return string|null The error message or null if no error
     */
    public function getLastError(): ?string;
}
