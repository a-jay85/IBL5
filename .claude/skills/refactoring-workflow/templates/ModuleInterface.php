<?php

declare(strict_types=1);

namespace ModuleName\Contracts;

/**
 * ModuleRepositoryInterface - Data access contract
 *
 * Defines the data access layer for ModuleName operations.
 * All database queries are encapsulated here.
 */
interface ModuleRepositoryInterface
{
    /**
     * Find a record by its ID
     *
     * @param int $id The record ID
     * @return array<string, mixed>|null Record data or null if not found
     */
    public function findById(int $id): ?array;

    /**
     * Find all records for a team
     *
     * @param int $teamId The team ID
     * @return array<int, array<string, mixed>> Array of records
     */
    public function findByTeam(int $teamId): array;

    /**
     * Update a record
     *
     * @param int $id The record ID
     * @param array<string, mixed> $data The data to update
     * @return int Number of affected rows
     */
    public function update(int $id, array $data): int;

    /**
     * Delete a record
     *
     * @param int $id The record ID
     * @return bool True if deleted, false otherwise
     */
    public function delete(int $id): bool;
}
