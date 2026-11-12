<?php

declare(strict_types=1);

namespace Migration\Contracts;

/**
 * MigrationRepositoryInterface - Contract for migration tracking data access
 *
 * Manages the `migrations` table to track which migration files have been applied.
 */
interface MigrationRepositoryInterface
{
    /**
     * Get all migration filenames that have already been applied.
     *
     * @return list<string> Filenames of previously-run migrations
     */
    public function getRanMigrations(): array;

    /**
     * Record a migration as having been applied.
     *
     * @param string $filename The migration filename
     * @param int $batch The batch number for this run
     */
    public function recordMigration(string $filename, int $batch): void;

    /**
     * Whether the migrations table has both the down_sql and up_sha256 columns.
     *
     * Queried on every call and never cached: the answer changes inside the
     * run that applies the migration that adds the columns.
     */
    public function supportsDownSql(): bool;

    /**
     * Record a migration together with its stored down SQL and up-file checksum.
     *
     * @param string $filename The migration filename
     * @param int $batch The batch number for this run
     * @param string $downSql Contents of the paired .down.sql file
     * @param string $upSha256 Hex SHA-256 of the up migration file
     */
    public function recordMigrationWithDown(string $filename, int $batch, string $downSql, string $upSha256): void;

    /**
     * Get the down-SQL state of every applied migration, oldest first.
     *
     * The down SQL text itself is not read, only whether it is stored.
     *
     * @return list<array{id: int, migration: string, hasDown: bool, upSha256: ?string}>
     */
    public function getAppliedDownStates(): array;

    /**
     * Get the down SQL stored for one applied migration row.
     *
     * @param int $id The migrations row id
     * @return string|null The stored text, or null when the row or the value is missing
     */
    public function getStoredDownSql(int $id): ?string;

    /**
     * Run a migration's stored down SQL and delete its migrations row, in one transaction.
     *
     * The down SQL must be DML-only because DDL commits implicitly and could not roll back.
     *
     * @param int $id The migrations row id
     * @param string $downSql The stored down SQL to run
     * @throws \RuntimeException When the down SQL fails or the row cannot be removed
     */
    public function undoMigration(int $id, string $downSql): void;

    /**
     * Get the next batch number (max existing batch + 1).
     *
     * @return int The next batch number to use
     */
    public function getNextBatchNumber(): int;

    /**
     * Execute raw SQL (for running migration file contents).
     *
     * Uses multi_query() to support files with multiple statements.
     *
     * @param string $sql Raw SQL to execute
     * @throws \RuntimeException On SQL execution failure
     */
    public function executeRawSql(string $sql): void;

    /**
     * Check if the migrations table has been seeded (contains batch 0 rows).
     *
     * Used to detect whether migrate-seed has been run. Without seeding,
     * the runner would attempt to re-execute all existing migrations.
     */
    public function hasSeededMigrations(): bool;

    /**
     * Truncate the migrations table (for seeding).
     */
    public function truncate(): void;
}
