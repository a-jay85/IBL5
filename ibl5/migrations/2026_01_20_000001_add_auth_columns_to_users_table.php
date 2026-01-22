<?php

declare(strict_types=1);

/**
 * Migration: Add auth columns to users table
 *
 * Adds columns required for Laravel Auth Bridge:
 * - role: User role (spectator, owner, commissioner)
 * - teams_owned: JSON array of team IDs/abbreviations
 * - nuke_user_id: Reference to legacy nuke_users table
 * - legacy_password: MD5 password for migration (cleared after bcrypt rehash)
 * - migrated_at: Timestamp when password was migrated to bcrypt
 *
 * Run with: php migrations/run.php
 */

return function (\mysqli $db) {
    return new class($db) {
        private \mysqli $db;

        public function __construct(\mysqli $db)
        {
            $this->db = $db;
        }

    /**
     * Run the migration
     */
    public function up(): void
    {
        // Add role column if not exists
        $this->addColumnIfNotExists(
            'users',
            'role',
            "ENUM('spectator', 'owner', 'commissioner') NOT NULL DEFAULT 'spectator' AFTER `remember_token`"
        );

        // Add teams_owned JSON column
        $this->addColumnIfNotExists(
            'users',
            'teams_owned',
            "JSON DEFAULT NULL AFTER `role`"
        );

        // Add nuke_user_id reference
        $this->addColumnIfNotExists(
            'users',
            'nuke_user_id',
            "INT(11) DEFAULT NULL AFTER `teams_owned`"
        );

        // Add legacy_password for MD5 migration
        $this->addColumnIfNotExists(
            'users',
            'legacy_password',
            "VARCHAR(40) DEFAULT NULL AFTER `nuke_user_id`"
        );

        // Add migrated_at timestamp
        $this->addColumnIfNotExists(
            'users',
            'migrated_at',
            "TIMESTAMP NULL DEFAULT NULL AFTER `legacy_password`"
        );

        // Add index on nuke_user_id for migration lookups
        $this->addIndexIfNotExists('users', 'idx_nuke_user_id', 'nuke_user_id');

        // Add index on role for permission queries
        $this->addIndexIfNotExists('users', 'idx_role', 'role');

        echo "Migration complete: Added auth columns to users table\n";
    }

    /**
     * Reverse the migration
     */
    public function down(): void
    {
        $columns = ['role', 'teams_owned', 'nuke_user_id', 'legacy_password', 'migrated_at'];

        foreach ($columns as $column) {
            $this->dropColumnIfExists('users', $column);
        }

        $this->dropIndexIfExists('users', 'idx_nuke_user_id');
        $this->dropIndexIfExists('users', 'idx_role');

        echo "Rollback complete: Removed auth columns from users table\n";
    }

    /**
     * Check if a column exists in a table
     */
    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return ((int) $row['cnt']) > 0;
    }

    /**
     * Add a column if it doesn't exist
     */
    private function addColumnIfNotExists(string $table, string $column, string $definition): void
    {
        if ($this->columnExists($table, $column)) {
            echo "Column {$table}.{$column} already exists, skipping\n";
            return;
        }

        $sql = "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}";
        if ($this->db->query($sql)) {
            echo "Added column {$table}.{$column}\n";
        } else {
            echo "ERROR adding column {$table}.{$column}: " . $this->db->error . "\n";
        }
    }

    /**
     * Drop a column if it exists
     */
    private function dropColumnIfExists(string $table, string $column): void
    {
        if (!$this->columnExists($table, $column)) {
            return;
        }

        $sql = "ALTER TABLE `{$table}` DROP COLUMN `{$column}`";
        if ($this->db->query($sql)) {
            echo "Dropped column {$table}.{$column}\n";
        } else {
            echo "ERROR dropping column {$table}.{$column}: " . $this->db->error . "\n";
        }
    }

    /**
     * Check if an index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?"
        );
        $stmt->bind_param('ss', $table, $indexName);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return ((int) $row['cnt']) > 0;
    }

    /**
     * Add an index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, string $indexName, string $columns): void
    {
        if ($this->indexExists($table, $indexName)) {
            echo "Index {$table}.{$indexName} already exists, skipping\n";
            return;
        }

        $sql = "ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$columns}`)";
        if ($this->db->query($sql)) {
            echo "Added index {$table}.{$indexName}\n";
        } else {
            echo "ERROR adding index {$table}.{$indexName}: " . $this->db->error . "\n";
        }
    }

    /**
     * Drop an index if it exists
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (!$this->indexExists($table, $indexName)) {
            return;
        }

        $sql = "ALTER TABLE `{$table}` DROP INDEX `{$indexName}`";
        if ($this->db->query($sql)) {
            echo "Dropped index {$table}.{$indexName}\n";
        } else {
            echo "ERROR dropping index {$table}.{$indexName}: " . $this->db->error . "\n";
        }
    }
    };
};
