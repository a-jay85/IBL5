<?php

declare(strict_types=1);

namespace YourAccount\Contracts;

/**
 * Contract for YourAccount business logic.
 *
 * Orchestrates authentication flows: login, registration, email confirmation,
 * password reset, and logout. Delegates to AuthService for credential management
 * and to the Repository for session/IP tracking.
 */
interface YourAccountServiceInterface
{
    /**
     * Attempt to authenticate a user.
     *
     * On success, cleans up guest sessions and records the login IP.
     *
     * @param string $username The username
     * @param string $password The plaintext password
     * @param bool $rememberMe Whether to set a persistent cookie
     * @param string $clientIp The client's IP address
     * @return array{success: bool, error: ?string}
     */
    public function attemptLogin(string $username, string $password, bool $rememberMe, string $clientIp): array;

    /**
     * Register a new user account.
     *
     * Validates passwords (match, length, auto-generates if both blank),
     * validates username format, then delegates to AuthService and sends
     * a verification email.
     *
     * @param string $username Desired username
     * @param string $email User's email address
     * @param string $password1 Password (or blank for auto-generation)
     * @param string $password2 Password confirmation (or blank for auto-generation)
     * @return array{success: bool, error: ?string}
     */
    public function registerUser(string $username, string $email, string $password1, string $password2): array;

    /**
     * Confirm a user's email address via selector/token from the verification link.
     *
     * @param string $selector The selector from the confirmation URL
     * @param string $token The token from the confirmation URL
     * @return array{success: bool, username: ?string, error: ?string}
     */
    public function confirmEmail(string $selector, string $token): array;

    /**
     * Request a password reset email.
     *
     * Always returns success to avoid revealing whether the email exists,
     * unless AuthService reports a rate-limiting error.
     *
     * @param string $email The email address
     * @return array{success: bool, error: ?string}
     */
    public function requestPasswordReset(string $email): array;

    /**
     * Reset a user's password using the selector/token from the reset email.
     *
     * @param string $selector The selector from the reset URL
     * @param string $token The token from the reset URL
     * @param string $newPassword The new password
     * @param string $confirmPassword The new password confirmation
     * @return array{success: bool, error: ?string}
     */
    public function resetPassword(string $selector, string $token, string $newPassword, string $confirmPassword): array;

    /**
     * Log out the current user by clearing the auth session.
     */
    public function logout(): void;

    /**
     * Get the redirect URL for a user's team page.
     *
     * Returns the Team module URL if the user has a real team assignment,
     * or null if they are a free agent or have no team.
     *
     * @param string $username The username to look up
     * @return string|null The team page URL, or null
     */
    public function getTeamRedirectUrl(string $username): ?string;
}
