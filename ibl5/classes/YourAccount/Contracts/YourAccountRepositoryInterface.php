<?php

declare(strict_types=1);

namespace YourAccount\Contracts;

/**
 * Contract for YourAccount database operations.
 *
 * Handles user login tracking queries specific to the authentication flow.
 */
interface YourAccountRepositoryInterface
{
    /**
     * Record the user's last login IP address.
     *
     * @param string $username The username to update
     * @param string $ipAddress The client IP address
     */
    public function updateLastLoginIp(string $username, string $ipAddress): void;
}
