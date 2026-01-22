<?php

declare(strict_types=1);

namespace Auth;

/**
 * PHP-Nuke Auth Compatibility Layer
 *
 * Provides drop-in replacements for legacy PHP-Nuke authentication functions.
 * Include this file to gradually migrate modules to the new Laravel Auth system.
 *
 * Usage:
 * Replace in mainfile.php or module files:
 *   is_admin($admin)  ->  NukeAuthCompat::is_admin($admin)
 *   is_user($user)    ->  NukeAuthCompat::is_user($user)
 *   cookiedecode($user) -> NukeAuthCompat::cookiedecode($user)
 *   getusrinfo($user) -> NukeAuthCompat::getusrinfo($user)
 */
class NukeAuthCompat
{
    private static ?LaravelAuthBridge $authBridge = null;
    private static bool $nukeAuthDisabled = false;

    /**
     * Initialize the auth bridge with database connection
     */
    public static function init(\mysqli $db): void
    {
        self::$authBridge = new LaravelAuthBridge($db);
        self::$nukeAuthDisabled = defined('NUKE_AUTH_DISABLED') && NUKE_AUTH_DISABLED === true;
    }

    /**
     * Get the auth bridge instance
     */
    public static function getAuthBridge(): ?LaravelAuthBridge
    {
        return self::$authBridge;
    }

    /**
     * Check if Nuke auth is disabled (post-migration)
     */
    public static function isNukeAuthDisabled(): bool
    {
        return self::$nukeAuthDisabled;
    }

    /**
     * Replacement for is_admin() function
     *
     * @param mixed $admin The legacy admin cookie (ignored when using Laravel Auth)
     * @return int 1 if admin, 0 otherwise (for legacy compatibility)
     */
    public static function is_admin(mixed $admin = null): int
    {
        if (self::$authBridge === null) {
            // Fallback to legacy behavior if not initialized
            return self::legacyIsAdmin($admin);
        }

        return self::$authBridge->isAdmin($admin) ? 1 : 0;
    }

    /**
     * Replacement for is_user() function
     *
     * @param mixed $user The legacy user cookie (ignored when using Laravel Auth)
     * @return int 1 if user, 0 otherwise (for legacy compatibility)
     */
    public static function is_user(mixed $user = null): int
    {
        if (self::$authBridge === null) {
            // Fallback to legacy behavior if not initialized
            return self::legacyIsUser($user);
        }

        return self::$authBridge->isUser($user) ? 1 : 0;
    }

    /**
     * Replacement for cookiedecode() function
     *
     * @param mixed $user The legacy user cookie
     * @return array<int|string, mixed>|null User info array or null
     */
    public static function cookiedecode(mixed $user = null): ?array
    {
        if (self::$authBridge === null) {
            // Fallback to legacy behavior if not initialized
            return self::legacyCookiedecode($user);
        }

        $userInfo = self::$authBridge->getUserInfo();

        if (empty($userInfo)) {
            return null;
        }

        // Return in legacy format: [0 => id, 1 => username, 2 => password_hash, ...]
        return $userInfo;
    }

    /**
     * Replacement for getusrinfo() function
     *
     * @param mixed $user The legacy user cookie
     * @return array<string, mixed>|null User info or null
     */
    public static function getusrinfo(mixed $user = null): ?array
    {
        if (self::$authBridge === null) {
            // Fallback to legacy behavior if not initialized
            return self::legacyGetusrinfo($user);
        }

        $laravelUser = self::$authBridge->getUser();

        if ($laravelUser === null) {
            return null;
        }

        // Return in legacy nuke_users format for backwards compatibility
        return [
            'user_id' => $laravelUser->getId(),
            'username' => $laravelUser->getName(),
            'name' => $laravelUser->getName(),
            'user_email' => $laravelUser->getEmail(),
            'user_password' => '', // Never expose password
            'user_ibl_team' => $laravelUser->getTeamsOwned()[0] ?? '',
            'points' => 0, // Legacy points system
            'user_active' => 1,
        ];
    }

    /**
     * Check if current user owns a specific team
     *
     * New method not in legacy Nuke auth - for module upgrades
     *
     * @param int|string $teamId Team ID or abbreviation
     * @return bool True if user owns the team
     */
    public static function ownsTeam(int|string $teamId): bool
    {
        if (self::$authBridge === null) {
            return false;
        }

        return self::$authBridge->ownsTeam($teamId);
    }

    /**
     * Check if current user has a specific role
     *
     * New method not in legacy Nuke auth - for module upgrades
     *
     * @param string $role Role name (spectator, owner, commissioner)
     * @return bool True if user has the role
     */
    public static function hasRole(string $role): bool
    {
        if (self::$authBridge === null) {
            return false;
        }

        return self::$authBridge->hasRole($role);
    }

    /**
     * Get the current authenticated user
     *
     * New method not in legacy Nuke auth - for module upgrades
     *
     * @return User|null The authenticated user or null
     */
    public static function getUser(): ?User
    {
        return self::$authBridge?->getUser();
    }

    // ========================================
    // Legacy fallback methods
    // These are used when the auth bridge isn't initialized
    // ========================================

    /**
     * Legacy is_admin implementation
     */
    private static function legacyIsAdmin(mixed $admin): int
    {
        if (self::$nukeAuthDisabled) {
            throw new \RuntimeException('Nuke auth is disabled. Please use Laravel Auth.');
        }

        if (!$admin) {
            return 0;
        }

        // Original PHP-Nuke logic
        if (!is_array($admin)) {
            $admin = base64_decode((string) $admin);
            $admin = addslashes($admin);
            $admin = explode(':', $admin);
        }

        $aid = $admin[0] ?? '';
        $pwd = $admin[1] ?? '';
        $aid = substr(addslashes($aid), 0, 25);

        if (!empty($aid) && !empty($pwd)) {
            global $prefix, $db;
            $sql = "SELECT pwd FROM " . $prefix . "_authors WHERE aid='" . addslashes($aid) . "'";
            $result = $db->sql_query($sql);
            $pass = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);
            if (($pass[0] ?? '') === $pwd && !empty($pass[0])) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Legacy is_user implementation
     */
    private static function legacyIsUser(mixed $user): int
    {
        if (self::$nukeAuthDisabled) {
            throw new \RuntimeException('Nuke auth is disabled. Please use Laravel Auth.');
        }

        if (!$user) {
            return 0;
        }

        if (!is_array($user)) {
            $user = base64_decode((string) $user);
            $user = addslashes($user);
            $user = explode(":", $user);
        }

        $uid = intval($user[0] ?? 0);
        $pwd = $user[2] ?? '';

        if (!empty($uid) && !empty($pwd)) {
            global $db, $user_prefix;
            $sql = "SELECT user_password FROM " . $user_prefix . "_users WHERE user_id='$uid'";
            $result = $db->sql_query($sql);
            $row = $db->sql_fetchrow($result);
            $db->sql_freeresult($result);
            if (($row[0] ?? '') === $pwd && !empty($row[0])) {
                return 1;
            }
        }

        return 0;
    }

    /**
     * Legacy cookiedecode implementation
     *
     * @return array<int, string>|null
     */
    private static function legacyCookiedecode(mixed $user): ?array
    {
        if (self::$nukeAuthDisabled) {
            throw new \RuntimeException('Nuke auth is disabled. Please use Laravel Auth.');
        }

        global $cookie, $db, $user_prefix;
        static $pass;

        if (!is_array($user)) {
            $user = base64_decode((string) $user);
            $user = addslashes($user);
            $cookie = explode(":", $user);
        } else {
            $cookie = $user;
        }

        if (!isset($pass) && isset($cookie[1])) {
            $sql = "SELECT user_password FROM " . $user_prefix . "_users WHERE username='" . addslashes($cookie[1]) . "'";
            $result = $db->sql_query($sql);
            $row = $db->sql_fetchrow($result);
            $pass = $row[0] ?? null;
            $db->sql_freeresult($result);
        }

        if (isset($cookie[2]) && $cookie[2] === $pass && !empty($pass)) {
            return $cookie;
        }

        return null;
    }

    /**
     * Legacy getusrinfo implementation
     *
     * @return array<string, mixed>|null
     */
    private static function legacyGetusrinfo(mixed $user): ?array
    {
        if (self::$nukeAuthDisabled) {
            throw new \RuntimeException('Nuke auth is disabled. Please use Laravel Auth.');
        }

        global $user_prefix, $db, $userinfo, $cookie;

        if (!$user || empty($user)) {
            return null;
        }

        $cookie = self::legacyCookiedecode($user);
        $user = $cookie;

        if ($user === null) {
            return null;
        }

        $sql = "SELECT * FROM " . $user_prefix . "_users WHERE username='" . addslashes($user[1]) . "' AND user_password='" . addslashes($user[2]) . "'";
        $result = $db->sql_query($sql);

        if ($db->sql_numrows($result) === 1) {
            $userrow = $db->sql_fetchrow($result);
            $userinfo = $userrow;
            return $userrow;
        }

        $db->sql_freeresult($result);
        unset($userinfo);
        return null;
    }
}
