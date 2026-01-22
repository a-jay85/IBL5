<?php

declare(strict_types=1);

namespace Auth;

use Auth\Contracts\UserMigrationServiceInterface;

/**
 * User Migration Service
 *
 * Handles migration of users from PHP-Nuke nuke_users table
 * to Laravel-compatible users table with role mapping.
 */
class UserMigrationService implements UserMigrationServiceInterface
{
    private \mysqli $db;

    /** @var array<string, string> Cache of admin usernames to roles */
    private array $adminCache = [];

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
        $this->loadAdminCache();
    }

    /**
     * Load admin usernames from nuke_authors table
     */
    private function loadAdminCache(): void
    {
        $result = $this->db->query(
            'SELECT aid, radminsuper FROM nuke_authors'
        );

        if ($result === false) {
            return;
        }

        while ($row = $result->fetch_assoc()) {
            $username = strtolower((string) $row['aid']);
            $isSuper = (int) $row['radminsuper'] === 1;
            $this->adminCache[$username] = $isSuper ? User::ROLE_COMMISSIONER : User::ROLE_OWNER;
        }

        $result->free();
    }

    /**
     * @inheritDoc
     */
    public function syncFromNuke(bool $dryRun = false): array
    {
        $result = [
            'migrated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        // Get all nuke users
        $query = $this->db->query(
            'SELECT * FROM nuke_users WHERE user_active = 1 ORDER BY user_id'
        );

        if ($query === false) {
            $result['errors'][] = 'Failed to query nuke_users: ' . $this->db->error;
            return $result;
        }

        while ($nukeUser = $query->fetch_assoc()) {
            $nukeUserId = (int) $nukeUser['user_id'];

            // Check if already migrated
            if ($this->isUserMigrated($nukeUserId)) {
                $result['skipped']++;
                continue;
            }

            if ($dryRun) {
                $result['migrated']++;
                continue;
            }

            try {
                if ($this->syncUser($nukeUserId)) {
                    $result['migrated']++;
                } else {
                    $result['errors'][] = "Failed to sync user ID: {$nukeUserId}";
                }
            } catch (\Exception $e) {
                $result['errors'][] = "Error syncing user ID {$nukeUserId}: " . $e->getMessage();
            }
        }

        $query->free();
        return $result;
    }

    /**
     * Check if a nuke user has already been migrated
     */
    private function isUserMigrated(int $nukeUserId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as cnt FROM users WHERE nuke_user_id = ?'
        );

        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param('i', $nukeUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return ((int) $row['cnt']) > 0;
    }

    /**
     * @inheritDoc
     */
    public function syncUser(int $nukeUserId): bool
    {
        // Get nuke user data
        $stmt = $this->db->prepare(
            'SELECT * FROM nuke_users WHERE user_id = ?'
        );

        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param('i', $nukeUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        $nukeUser = $result->fetch_assoc();
        $stmt->close();

        if ($nukeUser === null) {
            return false;
        }

        // Map nuke user fields to Laravel users fields
        $username = (string) $nukeUser['username'];
        $email = (string) $nukeUser['user_email'];
        $legacyPassword = (string) $nukeUser['user_password'];
        $role = $this->determineRole($username);
        $teamsOwned = $this->parseTeamsOwned((string) $nukeUser['user_ibl_team']);
        $now = date('Y-m-d H:i:s');

        // Check if email already exists (different nuke user)
        if ($this->emailExists($email)) {
            // Append nuke user id to make unique
            $email = "nuke_{$nukeUserId}@iblhoops.net";
        }

        // Insert into users table
        $stmt = $this->db->prepare(
            'INSERT INTO users (name, email, password, role, teams_owned, nuke_user_id, legacy_password, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if ($stmt === false) {
            return false;
        }

        // Password is empty - will be set on first login via legacy_password
        $emptyPassword = '';
        $teamsOwnedJson = json_encode($teamsOwned);

        $stmt->bind_param(
            'sssssssss',
            $username,
            $email,
            $emptyPassword,
            $role,
            $teamsOwnedJson,
            $nukeUserId,
            $legacyPassword,
            $now,
            $now
        );

        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Check if an email already exists in users table
     */
    private function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as cnt FROM users WHERE email = ?'
        );

        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return ((int) $row['cnt']) > 0;
    }

    /**
     * @inheritDoc
     */
    public function determineRole(string $username): string
    {
        $lowerUsername = strtolower($username);

        if (isset($this->adminCache[$lowerUsername])) {
            return $this->adminCache[$lowerUsername];
        }

        // Default role based on whether they own a team
        // Will be set to 'owner' if they have user_ibl_team set
        return User::ROLE_SPECTATOR;
    }

    /**
     * Parse teams owned from user_ibl_team field
     *
     * The field can contain a single team abbreviation or comma-separated list
     *
     * @param string $teamsField The user_ibl_team field value
     * @return array<string>
     */
    private function parseTeamsOwned(string $teamsField): array
    {
        if ($teamsField === '') {
            return [];
        }

        // Handle comma-separated teams
        $teams = array_map('trim', explode(',', $teamsField));
        $teams = array_filter($teams, fn($t) => $t !== '');

        return $teams;
    }

    /**
     * @inheritDoc
     */
    public function getStats(): array
    {
        $stats = [
            'total_nuke' => 0,
            'total_users' => 0,
            'migrated' => 0,
            'pending' => 0,
        ];

        // Count total nuke users
        $result = $this->db->query(
            'SELECT COUNT(*) as cnt FROM nuke_users WHERE user_active = 1'
        );
        if ($result !== false) {
            $row = $result->fetch_assoc();
            $stats['total_nuke'] = (int) $row['cnt'];
            $result->free();
        }

        // Count total users table entries
        $result = $this->db->query('SELECT COUNT(*) as cnt FROM users');
        if ($result !== false) {
            $row = $result->fetch_assoc();
            $stats['total_users'] = (int) $row['cnt'];
            $result->free();
        }

        // Count migrated users (those with nuke_user_id set)
        $result = $this->db->query(
            'SELECT COUNT(*) as cnt FROM users WHERE nuke_user_id IS NOT NULL'
        );
        if ($result !== false) {
            $row = $result->fetch_assoc();
            $stats['migrated'] = (int) $row['cnt'];
            $result->free();
        }

        // Calculate pending
        $stats['pending'] = $stats['total_nuke'] - $stats['migrated'];

        return $stats;
    }

    /**
     * Update role for an existing user
     *
     * @param int $userId The users.id
     * @param string $role The new role
     * @return bool True if update successful
     */
    public function updateUserRole(int $userId, string $role): bool
    {
        $validRoles = [User::ROLE_SPECTATOR, User::ROLE_OWNER, User::ROLE_COMMISSIONER];

        if (!in_array($role, $validRoles, true)) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE users SET role = ?, updated_at = ? WHERE id = ?'
        );

        if ($stmt === false) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $stmt->bind_param('ssi', $role, $now, $userId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }

    /**
     * Update teams owned for an existing user
     *
     * @param int $userId The users.id
     * @param array<string> $teams Array of team identifiers
     * @return bool True if update successful
     */
    public function updateTeamsOwned(int $userId, array $teams): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET teams_owned = ?, updated_at = ? WHERE id = ?'
        );

        if ($stmt === false) {
            return false;
        }

        $teamsJson = json_encode($teams);
        $now = date('Y-m-d H:i:s');
        $stmt->bind_param('ssi', $teamsJson, $now, $userId);
        $success = $stmt->execute();
        $stmt->close();

        return $success;
    }
}
