<?php

declare(strict_types=1);

namespace Migration;

/**
 * Result of schema validation: pass/fail with list of missing columns.
 */
final class SchemaValidationResult
{
    /**
     * @param list<string> $missing Missing columns as "table.column" strings
     */
    public function __construct(
        public readonly bool $passed,
        public readonly array $missing,
    ) {
    }
}
