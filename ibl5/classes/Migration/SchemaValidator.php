<?php

declare(strict_types=1);

namespace Migration;

/**
 * Validates that the actual database schema matches expected column assertions.
 *
 * Runs one bound INFORMATION_SCHEMA query per assertion.
 * Designed to run after migrations to catch silent no-ops (e.g., IF EXISTS guards
 * that swallowed a failed column rename).
 */
class SchemaValidator extends \Database\BaseMysqliRepository
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
        $foundKeys = [];

        foreach ($assertions as $assertion) {
            $expectedKeys[] = $assertion->toKey();

            /** @var list<array{TABLE_NAME: string, COLUMN_NAME: string}> $rows */
            $rows = $this->fetchAll(
                'SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                'ss',
                $assertion->table,
                $assertion->column,
            );
            foreach ($rows as $row) {
                $foundKeys[] = $row['TABLE_NAME'] . '.' . $row['COLUMN_NAME'];
            }
        }

        $missing = array_values(array_diff($expectedKeys, $foundKeys));

        return new SchemaValidationResult(
            passed: $missing === [],
            missing: $missing,
        );
    }
}
