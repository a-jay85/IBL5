<?php

declare(strict_types=1);

namespace Auth\Contracts;

use Auth\User;

/**
 * Interface for Laravel Auth Bridge
 *
 * Provides a compatibility layer between PHP-Nuke auth functions
 * and Laravel's authentication system.
 */
interface LaravelAuthBridgeInterface
{
    /**
     * Check if the current session has an authenticated admin
     *
     * @param mixed $admin The admin cookie/session data (legacy parameter for compatibility)
     * @return bool True if user is an admin (commissioner or super admin)
     */
    public function isAdmin(mixed $admin = null): bool;

    /**
     * Check if the current session has an authenticated user
     *
     * @param mixed $user The user cookie/session data (legacy parameter for compatibility)
     * @return bool True if user is authenticated
     */
    public function isUser(mixed $user = null): bool;

    /**
     * Get the currently authenticated user
     *
     * @return User|null The authenticated user or null
     */
    public function getUser(): ?User;

    /**
     * Get user information array (legacy format compatibility)
     *
     * Returns array in format compatible with PHP-Nuke's cookiedecode():
     * [0] => user_id, [1] => username, [2] => password_hash, etc.
     *
     * @return array<int, mixed> User info array or empty array if not authenticated
     */
    public function getUserInfo(): array;

    /**
     * Authenticate a user with username/email and password
     *
     * Handles legacy MD5 password rehashing to bcrypt on successful login.
     *
     * @param string $username Username or email
     * @param string $password Plain text password
     * @param bool $remember Whether to remember the session
     * @return bool True if authentication successful
     */
    public function authenticate(string $username, string $password, bool $remember = false): bool;

    /**
     * Log out the current user
     *
     * @return void
     */
    public function logout(): void;

    /**
     * Check if the current user has a specific role
     *
     * @param string $role Role name (spectator, owner, commissioner)
     * @return bool True if user has the role
     */
    public function hasRole(string $role): bool;

    /**
     * Check if the user owns a specific team
     *
     * @param int|string $teamId Team ID or abbreviation
     * @return bool True if user owns the team
     */
    public function ownsTeam(int|string $teamId): bool;

    /**
     * Get all teams owned by the current user
     *
     * @return array<string> Array of team identifiers
     */
    public function getOwnedTeams(): array;
}
