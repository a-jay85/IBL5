<?php

declare(strict_types=1);

namespace Middleware\Contracts;

/**
 * Interface for Module Authorization Middleware
 *
 * Provides a centralized authorization check for all modules
 * based on user roles and module access settings.
 */
interface ModuleMiddlewareInterface
{
    /**
     * Authorize access to a module
     *
     * Checks if the current user has permission to access the specified module
     * based on the module's view setting and user role.
     *
     * @param string $moduleName The name of the module to authorize
     * @return bool True if authorized, false otherwise
     */
    public function authorize(string $moduleName): bool;

    /**
     * Get the required access level for a module
     *
     * Returns the view setting for a module:
     * - 0: Public access (everyone)
     * - 1: Registered users only
     * - 2: Admins only
     * - 3: Paid subscribers
     *
     * @param string $moduleName The module name
     * @return int The access level (0-3)
     */
    public function getModuleAccessLevel(string $moduleName): int;

    /**
     * Check if a module is active
     *
     * @param string $moduleName The module name
     * @return bool True if module is active
     */
    public function isModuleActive(string $moduleName): bool;

    /**
     * Get the denial reason for a failed authorization
     *
     * @return string|null The denial reason or null if authorized
     */
    public function getDenialReason(): ?string;
}
