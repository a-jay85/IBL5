<?php

declare(strict_types=1);

namespace PHPStanRules;

/**
 * The table, ORDER BY columns, and WHERE-bound columns of a single-table SELECT.
 *
 * resolve() returns null for any shape it cannot read safely: JOIN, UNION,
 * DISTINCT, HAVING, a subquery, a comma FROM list, a qualified db.table, or a
 * sprintf directive. The caller then gets no table-aware acceptance, so a shape
 * this class cannot read is judged by the fallback allowlist alone.
 *
 * A WHERE column counts as bound only through a `col = ?` conjunct of an AND
 * chain whose text holds no OR, NOT, XOR, BETWEEN, CASE, `||` or `!`. Every such
 * conjunct must hold for a row to be returned, so all returned rows share the
 * bound value. A literal value never binds a column.
 *
 * Used by OrderByRequiresUniqueTiebreakerRule.
 */
final class OrderBySqlShape
{
    private const UNRESOLVABLE   = '/\b(?:join|union|distinct|having)\b|%(?:\d+\$)?[sdu]\b/i';
    private const FROM           = '/\bfrom\s+`?(\w+)`?(?:\s+(?:as\s+)?`?(?!where\b|order\b|limit\b)(\w+)`?)?\s+(?=where\b|order\s+by\b)/i';
    private const WHERE          = '/\bwhere\s+(.+?)\s+order\s+by\b/i';
    private const WHERE_BLOCKERS = '/\b(?:or|not|xor|between|case)\b|\|\||!/i';
    private const BOUND_CONJUNCT = '/^\(*\s*`?(?:(\w+)`?\.`?)?(\w+)`?\s*=\s*\?\s*\)*$/';
    private const ORDER_TERM     = '/^`?(?:(\w+)`?\.`?)?(\w+)`?(?:\s+(?:asc|desc))?$/i';

    /**
     * @param list<string> $orderColumns
     * @param list<string> $boundColumns
     */
    private function __construct(
        private readonly string $table,
        private readonly array $orderColumns,
        private readonly array $boundColumns,
    ) {
    }

    /**
     * null = unresolvable; the caller then gives no table-aware acceptance (fail closed).
     */
    public static function resolve(string $normalizedSql, string $orderByClause): ?self
    {
        if (preg_match(self::UNRESOLVABLE, $normalizedSql) !== 0) {
            return null;
        }
        if (preg_match_all('/\bselect\b/i', $normalizedSql) !== 1) {
            return null;
        }
        if (preg_match_all('/\bfrom\b/i', $normalizedSql) !== 1) {
            return null;
        }
        if (preg_match(self::FROM, $normalizedSql, $from) !== 1) {
            return null;
        }

        $table = strtolower($from[1]);
        $alias = isset($from[2]) ? strtolower($from[2]) : null;

        // An ORDER BY name can refer to a SELECT-list alias instead of a column,
        // so a name the SELECT list defines with AS never counts as a column.
        $selectList = '';
        if (preg_match('/\bselect\b(.*?)\bfrom\b/i', $normalizedSql, $select) === 1) {
            $selectList = $select[1];
        }

        $orderColumns = [];
        foreach (explode(',', $orderByClause) as $term) {
            $term = trim($term);
            if (preg_match(self::ORDER_TERM, $term, $m) !== 1) {
                continue;
            }
            if (!self::qualifierMatches($m[1], $table, $alias)) {
                continue;
            }
            $column = strtolower($m[2]);
            if (preg_match('/\bas\s+`?' . preg_quote($column, '/') . '`?(?!\w)/i', $selectList) === 1) {
                continue;
            }
            $orderColumns[] = $column;
        }

        return new self($table, $orderColumns, self::boundColumnsOf($normalizedSql, $table, $alias));
    }

    public function table(): string
    {
        return $this->table;
    }

    /**
     * @return list<string>
     */
    public function orderColumns(): array
    {
        return $this->orderColumns;
    }

    /**
     * @return list<string>
     */
    public function boundColumns(): array
    {
        return $this->boundColumns;
    }

    /**
     * @return list<string>
     */
    private static function boundColumnsOf(string $normalizedSql, string $table, ?string $alias): array
    {
        if (preg_match(self::WHERE, $normalizedSql, $where) !== 1) {
            return [];
        }
        $whereText = $where[1];
        if (preg_match(self::WHERE_BLOCKERS, $whereText) !== 0) {
            return [];
        }

        $bound = [];
        foreach ((array) preg_split('/\s+and\s+/i', $whereText) as $conjunct) {
            if (preg_match(self::BOUND_CONJUNCT, trim((string) $conjunct), $m) !== 1) {
                continue;
            }
            if (!self::qualifierMatches($m[1], $table, $alias)) {
                continue;
            }
            $bound[] = strtolower($m[2]);
        }

        return $bound;
    }

    private static function qualifierMatches(string $qualifier, string $table, ?string $alias): bool
    {
        if ($qualifier === '') {
            return true;
        }
        $qualifier = strtolower($qualifier);

        return $qualifier === $table || $qualifier === $alias;
    }
}
