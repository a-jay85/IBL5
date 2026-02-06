<?php

declare(strict_types=1);

namespace ModuleName;

use ModuleName\Contracts\ModuleRepositoryInterface;

/**
 * CustomRepository - Database repository template extending BaseMysqliRepository
 *
 * Replace 'ModuleName' with your actual module namespace.
 * Replace 'ibl_table' with your actual table name.
 * Add methods as needed for your specific data access needs.
 */
class CustomRepository extends \BaseMysqliRepository implements ModuleRepositoryInterface
{
    /**
     * Find a single record by ID
     *
     * @see ModuleRepositoryInterface::findById()
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM ibl_table WHERE id = ? LIMIT 1",
            "i",
            $id
        );
    }

    /**
     * Find all records for a team
     *
     * @see ModuleRepositoryInterface::findByTeam()
     */
    public function findByTeam(int $teamId): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_table WHERE tid = ? ORDER BY ordinal",
            "i",
            $teamId
        );
    }

    /**
     * Find records matching multiple criteria
     */
    public function findByFilters(int $teamId, string $status, int $minValue): array
    {
        return $this->fetchAll(
            "SELECT * FROM ibl_table 
             WHERE tid = ? AND status = ? AND value >= ?
             ORDER BY value DESC",
            "isi",
            $teamId,
            $status,
            $minValue
        );
    }

    /**
     * Search by name with LIKE pattern
     */
    public function searchByName(string $searchTerm): array
    {
        $pattern = "%" . $searchTerm . "%";
        
        return $this->fetchAll(
            "SELECT * FROM ibl_table WHERE name LIKE ? ORDER BY name",
            "s",
            $pattern
        );
    }

    /**
     * Insert a new record
     *
     * @return int The new record ID
     */
    public function create(string $name, int $teamId, int $value): int
    {
        $this->execute(
            "INSERT INTO ibl_table (name, tid, value, created_at) VALUES (?, ?, ?, NOW())",
            "sii",
            $name,
            $teamId,
            $value
        );
        
        return $this->getLastInsertId();
    }

    /**
     * Update a record
     *
     * @see ModuleRepositoryInterface::update()
     */
    public function update(int $id, array $data): int
    {
        return $this->execute(
            "UPDATE ibl_table SET name = ?, value = ?, updated_at = NOW() WHERE id = ?",
            "sii",
            $data['name'],
            $data['value'],
            $id
        );
    }

    /**
     * Delete a record
     */
    public function delete(int $id): bool
    {
        $affected = $this->execute(
            "DELETE FROM ibl_table WHERE id = ?",
            "i",
            $id
        );
        
        return $affected > 0;
    }

    /**
     * Count records by status
     */
    public function countByStatus(string $status): int
    {
        $result = $this->fetchOne(
            "SELECT COUNT(*) as count FROM ibl_table WHERE status = ?",
            "s",
            $status
        );
        
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Check if record exists
     */
    public function exists(int $id): bool
    {
        $result = $this->fetchOne(
            "SELECT 1 as found FROM ibl_table WHERE id = ? LIMIT 1",
            "i",
            $id
        );
        
        return $result !== null;
    }

    /**
     * Find records by multiple IDs
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        
        // Validate all IDs are integers
        $safeIds = array_map('intval', $ids);
        $safeIds = array_filter($safeIds, fn($id) => $id > 0);
        
        if (empty($safeIds)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($safeIds), '?'));
        $types = str_repeat('i', count($safeIds));
        
        return $this->fetchAll(
            "SELECT * FROM ibl_table WHERE id IN ($placeholders) ORDER BY id",
            $types,
            ...$safeIds
        );
    }
}
