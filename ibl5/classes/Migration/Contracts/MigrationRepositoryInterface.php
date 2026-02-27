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
