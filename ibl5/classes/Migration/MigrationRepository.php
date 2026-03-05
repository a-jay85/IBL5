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

        return array_values(array_map(
            static fn(array $row): string => is_string($row['migration']) ? $row['migration'] : '',
            $rows,
        ));
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

        if ($row === null) {
            return 1;
        }

        $nextBatch = $row['next_batch'];

        return is_int($nextBatch) ? $nextBatch : 1;
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
        $sql = $this->preprocessDelimiters(trim($sql));

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

        // Final error check handled within the do-while loop above
    }

    /**
     * Strip DELIMITER directives from SQL and replace custom delimiters with semicolons.
     *
     * DELIMITER is a mysql client command, not valid SQL. multi_query() cannot process it.
     * This method converts custom delimiter blocks back to standard semicolon-terminated SQL
     * that multi_query() (and the MySQL server parser) can handle natively. The server parser
     * understands BEGIN...END compound statements, so semicolons inside trigger/procedure
     * bodies are handled correctly.
     */
    private function preprocessDelimiters(string $sql): string
    {
        $lines = explode("\n", $sql);
        $result = [];
        $currentDelimiter = ';';

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Match DELIMITER directive (e.g., "DELIMITER //", "DELIMITER $$", "DELIMITER ;")
            if (preg_match('/^DELIMITER\s+(\S+)\s*$/i', $trimmed, $matches) === 1) {
                $currentDelimiter = $matches[1];
                continue; // Strip the DELIMITER line itself
            }

            // If using a custom delimiter, check if this line ends with it
            if ($currentDelimiter !== ';' && str_ends_with($trimmed, $currentDelimiter)) {
                // Replace the custom delimiter at the end with a semicolon
                $pos = strrpos($line, $currentDelimiter);
                if ($pos !== false) {
                    $line = substr($line, 0, $pos) . ';';
                }
            }

            $result[] = $line;
        }

        return implode("\n", $result);
    }

    /**
     * @see MigrationRepositoryInterface::hasSeededMigrations()
     */
    public function hasSeededMigrations(): bool
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS cnt FROM migrations WHERE batch = 0',
        );

        if ($row === null) {
            return false;
        }

        $count = $row['cnt'];

        return is_int($count) && $count > 0;
    }

    /**
     * @see MigrationRepositoryInterface::truncate()
     */
    public function truncate(): void
    {
        $this->execute('TRUNCATE TABLE migrations');
    }
}
