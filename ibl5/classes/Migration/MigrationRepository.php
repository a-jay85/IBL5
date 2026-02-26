<?php

declare(strict_types=1);

namespace Migration;

use Migration\Contracts\MigrationRepositoryInterface;

/**
 * MigrationRepository - Database operations for migration tracking
 *
 * Extends BaseMysqliRepository to interact with the `migrations` table.
 * Also provides raw SQL execution for applying migration files.
 */
class MigrationRepository extends \BaseMysqliRepository implements MigrationRepositoryInterface
{
    /**
     * @see MigrationRepositoryInterface::getRanMigrations()
     */
    public function getRanMigrations(): array
    {
        $rows = $this->fetchAll(
            'SELECT migration FROM migrations ORDER BY id',
        );

        return array_map(
            static fn(array $row): string => (string) $row['migration'],
            $rows,
        );
    }

    /**
     * @see MigrationRepositoryInterface::recordMigration()
     */
    public function recordMigration(string $filename, int $batch): void
    {
        $this->execute(
            'INSERT INTO migrations (migration, batch) VALUES (?, ?)',
            'si',
            $filename,
            $batch,
        );
    }

    /**
     * @see MigrationRepositoryInterface::getNextBatchNumber()
     */
    public function getNextBatchNumber(): int
    {
        $row = $this->fetchOne(
            'SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM migrations',
        );

        return $row !== null ? (int) $row['next_batch'] : 1;
    }

    /**
     * @see MigrationRepositoryInterface::executeRawSql()
     *
     * Uses mysqli::multi_query() to handle migration files containing
     * multiple SQL statements separated by semicolons.
     *
     * Note: DDL statements (CREATE TABLE, ALTER TABLE, etc.) auto-commit
     * in MySQL, so wrapping in transactions is not possible.
     */
    public function executeRawSql(string $sql): void
    {
        $sql = trim($sql);

        if ($sql === '') {
            return;
        }

        $result = $this->db->multi_query($sql);

        if ($result === false) {
            throw new \RuntimeException(
                'Migration SQL execution failed: ' . $this->db->error,
            );
        }

        // Consume all result sets from multi_query to detect errors
        do {
            $storeResult = $this->db->store_result();
            if ($storeResult instanceof \mysqli_result) {
                $storeResult->free();
            }

            if ($this->db->errno !== 0) {
                throw new \RuntimeException(
                    'Migration SQL error: ' . $this->db->error,
                );
            }
        } while ($this->db->more_results() && $this->db->next_result());

        // Check for errors after the final result set
        if ($this->db->errno !== 0) {
            throw new \RuntimeException(
                'Migration SQL error after final statement: ' . $this->db->error,
            );
        }
    }

    /**
     * @see MigrationRepositoryInterface::truncate()
     */
    public function truncate(): void
    {
        $this->execute('TRUNCATE TABLE migrations');
    }
}
