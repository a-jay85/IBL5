<?php

declare(strict_types=1);

namespace Services;

/**
 * QueryConditions - Lightweight builder for dynamic WHERE clauses
 *
 * Replaces the repeated pattern of `$conditions = []; $types = ''; $params = [];`
 * found in repositories with dynamic filtering.
 */
class QueryConditions
{
    /** @var list<string> */
    private array $conditions = [];

    private string $types = '';

    /** @var list<string|int|float> */
    private array $params = [];

    /**
     * Create with optional base conditions (no bind params)
     *
     * @param list<string> $baseConditions SQL conditions that don't need bound params
     */
    public function __construct(array $baseConditions = [])
    {
        $this->conditions = $baseConditions;
    }

    /**
     * Add a condition with a single bound parameter
     *
     * @param string $condition SQL condition with ? placeholder
     * @param string $type mysqli bind type character ('i', 's', 'd')
     * @param string|int|float $value Parameter value
     */
    public function add(string $condition, string $type, string|int|float $value): void
    {
        $this->conditions[] = $condition;
        $this->types .= $type;
        $this->params[] = $value;
    }

    /**
     * Add a condition only if the value is not null
     *
     * @param string $condition SQL condition with ? placeholder
     * @param string $type mysqli bind type character
     * @param string|int|float|null $value Parameter value (skipped if null)
     */
    public function addIfNotNull(string $condition, string $type, string|int|float|null $value): void
    {
        if ($value !== null) {
            $this->add($condition, $type, $value);
        }
    }

    /**
     * Get the WHERE clause (conditions joined by AND)
     *
     * @return string WHERE clause without the 'WHERE' keyword, or '1=1' if empty
     */
    public function toWhereClause(): string
    {
        return $this->conditions !== [] ? implode(' AND ', $this->conditions) : '1=1';
    }

    public function getTypes(): string
    {
        return $this->types;
    }

    /**
     * @return list<string|int|float>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function hasParams(): bool
    {
        return $this->params !== [];
    }
}
