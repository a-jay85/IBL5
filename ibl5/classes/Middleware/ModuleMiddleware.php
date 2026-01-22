<?php

declare(strict_types=1);

namespace Middleware;

use Middleware\Contracts\ModuleMiddlewareInterface;
use Auth\LaravelAuthBridge;
use Auth\User;

/**
 * Module Authorization Middleware
 *
 * Provides centralized authorization checks for all PHP-Nuke modules.
 * Replaces the scattered is_user(), is_admin() checks throughout modules.php.
 */
class ModuleMiddleware implements ModuleMiddlewareInterface
{
    public const ACCESS_PUBLIC = 0;
    public const ACCESS_USERS = 1;
    public const ACCESS_ADMINS = 2;
    public const ACCESS_SUBSCRIBERS = 3;

    private \mysqli $db;
    private LaravelAuthBridge $authBridge;
    private string $prefix;
    private ?string $denialReason = null;

    /** @var array<string, array{active: int, view: int}> */
    private array $moduleCache = [];

    public function __construct(\mysqli $db, LaravelAuthBridge $authBridge, string $prefix = 'nuke')
    {
        $this->db = $db;
        $this->authBridge = $authBridge;
        $this->prefix = $prefix;
    }

    /**
     * @inheritDoc
     */
    public function authorize(string $moduleName): bool
    {
        $this->denialReason = null;

        // Check if module exists and is active
        if (!$this->isModuleActive($moduleName)) {
            // Admins can access inactive modules
            if ($this->authBridge->isAdmin()) {
                return true;
            }
            $this->denialReason = 'Module is not active';
            return false;
        }

        $accessLevel = $this->getModuleAccessLevel($moduleName);

        return match ($accessLevel) {
            self::ACCESS_PUBLIC => true,
            self::ACCESS_USERS => $this->authorizeForUsers($moduleName),
            self::ACCESS_ADMINS => $this->authorizeForAdmins(),
            self::ACCESS_SUBSCRIBERS => $this->authorizeForSubscribers(),
            default => false,
        };
    }

    /**
     * Authorize for registered users
     */
    private function authorizeForUsers(string $moduleName): bool
    {
        // Admins always have access
        if ($this->authBridge->isAdmin()) {
            return true;
        }

        // Check if user is logged in
        if (!$this->authBridge->isUser()) {
            $this->denialReason = 'This module requires a registered user account';
            return false;
        }

        // Check group permissions (points-based access)
        if (!$this->checkGroupPermissions($moduleName)) {
            $this->denialReason = 'You do not have sufficient privileges for this module';
            return false;
        }

        return true;
    }

    /**
     * Authorize for admins only
     */
    private function authorizeForAdmins(): bool
    {
        if (!$this->authBridge->isAdmin()) {
            $this->denialReason = 'This module is for administrators only';
            return false;
        }

        return true;
    }

    /**
     * Authorize for paid subscribers
     */
    private function authorizeForSubscribers(): bool
    {
        // For now, commissioners bypass subscription requirements
        if ($this->authBridge->hasRole(User::ROLE_COMMISSIONER)) {
            return true;
        }

        // TODO: Implement subscription check when subscription system is added
        $this->denialReason = 'This module requires a paid subscription';
        return false;
    }

    /**
     * Check group-based permissions (legacy points system)
     */
    private function checkGroupPermissions(string $moduleName): bool
    {
        $user = $this->authBridge->getUser();
        if ($user === null) {
            return false;
        }

        // Commissioners have all permissions
        if ($user->isAdmin()) {
            return true;
        }

        // Get module's required group
        $stmt = $this->db->prepare(
            "SELECT mod_group FROM {$this->prefix}_modules WHERE title = ?"
        );

        if ($stmt === false) {
            return true; // Allow access if query fails
        }

        $stmt->bind_param('s', $moduleName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row === null) {
            return true; // Allow if module not found
        }

        $modGroup = (int) $row['mod_group'];

        // mod_group = 0 means no group restriction
        if ($modGroup === 0) {
            return true;
        }

        // For now, allow access - group/points system can be extended
        return true;
    }

    /**
     * Load module info from database
     *
     * @return array{active: int, view: int}|null
     */
    private function loadModuleInfo(string $moduleName): ?array
    {
        if (isset($this->moduleCache[$moduleName])) {
            return $this->moduleCache[$moduleName];
        }

        $stmt = $this->db->prepare(
            "SELECT active, view FROM {$this->prefix}_modules WHERE title = ?"
        );

        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('s', $moduleName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row === null) {
            return null;
        }

        $info = [
            'active' => (int) $row['active'],
            'view' => (int) $row['view'],
        ];

        $this->moduleCache[$moduleName] = $info;
        return $info;
    }

    /**
     * @inheritDoc
     */
    public function getModuleAccessLevel(string $moduleName): int
    {
        $info = $this->loadModuleInfo($moduleName);
        return $info['view'] ?? self::ACCESS_PUBLIC;
    }

    /**
     * @inheritDoc
     */
    public function isModuleActive(string $moduleName): bool
    {
        $info = $this->loadModuleInfo($moduleName);
        return ($info['active'] ?? 0) === 1;
    }

    /**
     * @inheritDoc
     */
    public function getDenialReason(): ?string
    {
        return $this->denialReason;
    }

    /**
     * Clear the module cache
     */
    public function clearCache(): void
    {
        $this->moduleCache = [];
    }

    /**
     * Static factory method for convenience
     */
    public static function create(\mysqli $db, string $prefix = 'nuke'): self
    {
        $authBridge = new LaravelAuthBridge($db);
        return new self($db, $authBridge, $prefix);
    }
}
