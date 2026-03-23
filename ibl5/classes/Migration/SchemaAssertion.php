<?php

declare(strict_types=1);

namespace Migration;

/**
 * A single schema assertion: verifies that a column exists in a table.
 */
final class SchemaAssertion
{
    public function __construct(
        public readonly string $table,
        public readonly string $column,
    ) {
    }

    public function toKey(): string
    {
        return $this->table . '.' . $this->column;
    }
}
