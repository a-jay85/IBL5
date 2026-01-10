<?php

declare(strict_types=1);

namespace ModuleName;

use ModuleName\Contracts\ModuleRepositoryInterface;
use ModuleName\Contracts\ModuleServiceInterface;

/**
 * ModuleService - Business logic orchestration
 *
 * Coordinates between repository and view, enforces business rules.
 */
class ModuleService implements ModuleServiceInterface
{
    private ModuleRepositoryInterface $repository;

    public function __construct(ModuleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Get a record by ID with business logic applied
     *
     * @param int $id The record ID
     * @return array<string, mixed>|null Processed record or null
     */
    public function getById(int $id): ?array
    {
        $record = $this->repository->findById($id);
        
        if ($record === null) {
            return null;
        }

        // Apply any business transformations
        return $this->enrichRecord($record);
    }

    /**
     * Get all records for a team
     *
     * @param int $teamId The team ID
     * @return array<int, array<string, mixed>> Processed records
     */
    public function getByTeam(int $teamId): array
    {
        $records = $this->repository->findByTeam($teamId);
        
        return array_map(
            fn($record) => $this->enrichRecord($record),
            $records
        );
    }

    /**
     * Update a record with validation
     *
     * @param int $id The record ID
     * @param array<string, mixed> $data The data to update
     * @return bool True if successful
     * @throws \InvalidArgumentException If validation fails
     */
    public function update(int $id, array $data): bool
    {
        // Validate data before update
        $this->validateData($data);
        
        $affected = $this->repository->update($id, $data);
        return $affected > 0;
    }

    /**
     * Enrich a record with computed values
     */
    private function enrichRecord(array $record): array
    {
        // Add computed fields, format values, etc.
        return $record;
    }

    /**
     * Validate data before persistence
     *
     * @throws \InvalidArgumentException
     */
    private function validateData(array $data): void
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Name is required');
        }
    }
}
