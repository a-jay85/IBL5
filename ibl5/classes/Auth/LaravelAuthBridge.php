<?php

declare(strict_types=1);

namespace Auth;

use Auth\Contracts\LaravelAuthBridgeInterface;

/**
 * Laravel Auth Bridge
 *
 * Provides a compatibility layer between PHP-Nuke authentication
 * and Laravel's authentication system. Handles legacy MD5 password
 * rehashing to bcrypt on login.
 */
class LaravelAuthBridge implements LaravelAuthBridgeInterface
{
    private \mysqli $db;
    private ?User $currentUser = null;
    private bool $initialized = false;

    /**
     * Session key for storing user ID
     */
    private const SESSION_USER_KEY = 'ibl_user_id';
    private const SESSION_REMEMBER_KEY = 'ibl_remember_token';
    private const COOKIE_REMEMBER_NAME = 'ibl_remember';
    private const REMEMBER_DURATION = 60 * 60 * 24 * 30; // 30 days

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * Initialize session and load current user if authenticated
     */
    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check for existing session
        if (isset($_SESSION[self::SESSION_USER_KEY])) {
            $this->loadUserById((int) $_SESSION[self::SESSION_USER_KEY]);
        } elseif (isset($_COOKIE[self::COOKIE_REMEMBER_NAME])) {
            // Check remember token cookie
            $this->loadUserByRememberToken($_COOKIE[self::COOKIE_REMEMBER_NAME]);
        }
    }

    /**
     * Load user by ID from database
     */
    private function loadUserById(int $userId): void
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE id = ?'
        );

        if ($stmt === false) {
            return;
        }

        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row !== null) {
            $this->currentUser = new User($row);
        }
    }

    /**
     * Load user by remember token
     */
    private function loadUserByRememberToken(string $token): void
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE remember_token = ?'
        );

        if ($stmt === false) {
            return;
        }

        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row !== null) {
            $this->currentUser = new User($row);
            // Refresh session
            $_SESSION[self::SESSION_USER_KEY] = $this->currentUser->getId();
        }
    }

    /**
     * @inheritDoc
     */
    public function isAdmin(mixed $admin = null): bool
    {
        $this->initialize();

        if ($this->currentUser === null) {
            return false;
        }

        return $this->currentUser->isAdmin();
    }

    /**
     * @inheritDoc
     */
    public function isUser(mixed $user = null): bool
    {
        $this->initialize();

        return $this->currentUser !== null;
    }

    /**
     * @inheritDoc
     */
    public function getUser(): ?User
    {
        $this->initialize();

        return $this->currentUser;
    }

    /**
     * @inheritDoc
     */
    public function getUserInfo(): array
    {
        $this->initialize();

        if ($this->currentUser === null) {
            return [];
        }

        // Return array in legacy PHP-Nuke format
        return [
            0 => $this->currentUser->getId(),
            1 => $this->currentUser->getName(),
            2 => '', // Password hash - never expose
            3 => $this->currentUser->getTeamsOwned()[0] ?? '',
            'user_id' => $this->currentUser->getId(),
            'username' => $this->currentUser->getName(),
            'email' => $this->currentUser->getEmail(),
            'role' => $this->currentUser->getRole(),
            'teams_owned' => $this->currentUser->getTeamsOwned(),
        ];
    }

    /**
     * @inheritDoc
     */
    public function authenticate(string $username, string $password, bool $remember = false): bool
    {
        $this->initialize();

        // Find user by username or email
        $stmt = $this->db->prepare(
            'SELECT * FROM users WHERE name = ? OR email = ?'
        );

        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if ($row === null) {
            return false;
        }

        $user = new User($row);

        // Check password - handle legacy MD5 passwords
        if ($user->hasLegacyPassword()) {
            // Legacy MD5 password check
            if (md5($password) !== $user->getLegacyPassword()) {
                return false;
            }

            // Rehash password to bcrypt and clear legacy password
            $this->rehashPassword($user->getId(), $password);
        } else {
            // Modern bcrypt password check
            if (!password_verify($password, $user->getPassword() ?? '')) {
                return false;
            }

            // Check if password needs rehash (e.g., cost factor changed)
            if (password_needs_rehash($user->getPassword() ?? '', PASSWORD_BCRYPT)) {
                $this->rehashPassword($user->getId(), $password);
            }
        }

        // Reload user after potential password update
        $this->loadUserById($user->getId());

        if ($this->currentUser === null) {
            return false;
        }

        // Set session
        $_SESSION[self::SESSION_USER_KEY] = $this->currentUser->getId();

        // Handle remember me
        if ($remember) {
            $this->setRememberToken();
        }

        return true;
    }

    /**
     * Rehash password from MD5 to bcrypt
     */
    private function rehashPassword(int $userId, string $password): void
    {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare(
            'UPDATE users SET password = ?, legacy_password = NULL, migrated_at = ?, updated_at = ? WHERE id = ?'
        );

        if ($stmt === false) {
            return;
        }

        $stmt->bind_param('sssi', $hashedPassword, $now, $now, $userId);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Set remember token for persistent login
     */
    private function setRememberToken(): void
    {
        if ($this->currentUser === null) {
            return;
        }

        $token = bin2hex(random_bytes(32));

        $stmt = $this->db->prepare(
            'UPDATE users SET remember_token = ?, updated_at = ? WHERE id = ?'
        );

        if ($stmt === false) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $userId = $this->currentUser->getId();
        $stmt->bind_param('ssi', $token, $now, $userId);
        $stmt->execute();
        $stmt->close();

        // Set cookie
        setcookie(
            self::COOKIE_REMEMBER_NAME,
            $token,
            [
                'expires' => time() + self::REMEMBER_DURATION,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    /**
     * @inheritDoc
     */
    public function logout(): void
    {
        $this->initialize();

        if ($this->currentUser !== null) {
            // Clear remember token in database
            $stmt = $this->db->prepare(
                'UPDATE users SET remember_token = NULL WHERE id = ?'
            );

            if ($stmt !== false) {
                $userId = $this->currentUser->getId();
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();
            }
        }

        // Clear session
        unset($_SESSION[self::SESSION_USER_KEY]);
        unset($_SESSION[self::SESSION_REMEMBER_KEY]);

        // Clear cookie
        setcookie(
            self::COOKIE_REMEMBER_NAME,
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );

        $this->currentUser = null;
    }

    /**
     * @inheritDoc
     */
    public function hasRole(string $role): bool
    {
        $this->initialize();

        if ($this->currentUser === null) {
            return false;
        }

        return $this->currentUser->hasRole($role);
    }

    /**
     * @inheritDoc
     */
    public function ownsTeam(int|string $teamId): bool
    {
        $this->initialize();

        if ($this->currentUser === null) {
            return false;
        }

        return $this->currentUser->ownsTeam($teamId);
    }

    /**
     * @inheritDoc
     */
    public function getOwnedTeams(): array
    {
        $this->initialize();

        if ($this->currentUser === null) {
            return [];
        }

        return $this->currentUser->getTeamsOwned();
    }

    /**
     * Register a new user
     *
     * @param string $name Username
     * @param string $email Email address
     * @param string $password Plain text password
     * @param string $role User role
     * @param array<string> $teamsOwned Teams owned by user
     * @return User|null The created user or null on failure
     */
    public function register(
        string $name,
        string $email,
        string $password,
        string $role = User::ROLE_SPECTATOR,
        array $teamsOwned = []
    ): ?User {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $teamsOwnedJson = json_encode($teamsOwned);
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password, role, teams_owned, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param(
            'sssssss',
            $name,
            $email,
            $hashedPassword,
            $role,
            $teamsOwnedJson,
            $now,
            $now
        );

        if (!$stmt->execute()) {
            $stmt->close();
            return null;
        }

        $userId = (int) $stmt->insert_id;
        $stmt->close();

        $this->loadUserById($userId);
        return $this->currentUser;
    }
}
