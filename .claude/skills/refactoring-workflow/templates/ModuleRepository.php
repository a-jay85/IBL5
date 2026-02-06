<?php

declare(strict_types=1);

namespace ModuleName;

use ModuleName\Contracts\ModuleRepositoryInterface;

/**
 * ModuleRepository - Data access implementation
 *
 * Implements ModuleRepositoryInterface with dual database support.
 * Uses prepared statements for modern mysqli, escaped strings for legacy.
 */
class ModuleRepository extends \BaseMysqliRepository implements ModuleRepositoryInterface
{
    /**
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
     * @see ModuleRepositoryInterface::update()
     */
    public function update(int $id, array $data): int
    {
        // Build dynamic update query based on data keys
        // For simple cases, use direct query:
        return $this->execute(
            "UPDATE ibl_table SET name = ?, value = ? WHERE id = ?",
            "ssi",
            $data['name'],
            $data['value'],
            $id
        );
    }

    /**
     * @see ModuleRepositoryInterface::delete()
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
}
