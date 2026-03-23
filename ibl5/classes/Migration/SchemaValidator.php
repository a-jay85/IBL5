<?php

declare(strict_types=1);

namespace Migration;

/**
 * Validates that the actual database schema matches expected column assertions.
 *
 * Uses a single batched INFORMATION_SCHEMA query to check all assertions at once.
 * Designed to run after migrations to catch silent no-ops (e.g., IF EXISTS guards
 * that swallowed a failed column rename).
 */
class SchemaValidator extends \BaseMysqliRepository
{
    /**
     * Validate that all asserted columns exist in the database.
     *
     * @param list<SchemaAssertion> $assertions
     */
    public function validate(array $assertions): SchemaValidationResult
    {
        if ($assertions === []) {
            return new SchemaValidationResult(passed: true, missing: []);
        }

        $expectedKeys = [];
        $tuples = [];

        foreach ($assertions as $assertion) {
            $expectedKeys[] = $assertion->toKey();
            $tuples[] = '(' . $this->quote($assertion->table) . ', ' . $this->quote($assertion->column) . ')';
        }

        $tupleList = implode(', ', $tuples);

        /** @var list<array{TABLE_NAME: string, COLUMN_NAME: string}> $rows */
        $rows = $this->fetchAll(
            "SELECT TABLE_NAME, COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND (TABLE_NAME, COLUMN_NAME) IN ({$tupleList})"
        );

        $foundKeys = [];
        foreach ($rows as $row) {
            $foundKeys[] = $row['TABLE_NAME'] . '.' . $row['COLUMN_NAME'];
        }

        $missing = array_values(array_diff($expectedKeys, $foundKeys));

        return new SchemaValidationResult(
            passed: $missing === [],
            missing: $missing,
        );
    }

    private function quote(string $value): string
    {
        return "'" . $this->db->real_escape_string($value) . "'";
    }
}
