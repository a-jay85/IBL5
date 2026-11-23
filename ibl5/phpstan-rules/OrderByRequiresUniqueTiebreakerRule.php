<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Flags ORDER BY clauses on rendered/LIMIT-cut SQL whose final sort term is not
 * a recognized unique/PK column. Prevents non-deterministic row ordering for
 * user-visible output (motivating incident: CI #1329 visual-regression flake).
 *
 * This rule is a deliberate proxy, not a proof — true totality of an arbitrary
 * ORDER BY is undecidable from a string literal. It enforces the checkable proxy:
 * "final term bare column name is on the curated UNIQUE_COLUMNS allowlist". It
 * favors false-negatives over false-positives; existing violations are captured in
 * the PHPStan baseline as tracked debt. See ADR-0083.
 *
 * Scope: static String_ literals only. Concatenated/interpolated SQL is separately
 * banned by BanSqlStringConcatenationRule / BanSqlStringInterpolationRule, so ORDER
 * BY clauses reliably appear in static literals; walking Concat/Encapsed is out of scope.
 *
 * Carve-outs (do NOT flag):
 *   (a) LIMIT 1   — single-row result is deterministic regardless of tie order
 *   (b) GROUP BY  — grouping key governs row uniqueness; proving totality through
 *                   GROUP BY is beyond the string proxy (favors false-negatives)
 *   (c) no ORDER BY at all
 *   (d) final bare column is on the fallback allowlist, or the ORDER BY plus
 *       WHERE-bound columns cover a PRIMARY KEY or NOT NULL UNIQUE key of the
 *       single FROM table, per ibl5/docs/schema/current-schema.sql
 *
 * The key check in (d) is set containment. When every column of one usable key
 * appears among the ORDER BY columns or the `col = ?` columns of an OR-free AND
 * chain, two rows that tie on the whole ORDER BY share that key, so they are the
 * same row. OrderBySqlShape reads the query and SchemaKeyMap reads the dump.
 *
 * A table missing from the dump (a view, or a table whose migration landed before
 * CI refreshed the dump) gets no key-map acceptance. Run bin/regen-schema-dump in
 * that PR, or keep an inline ignore. See ADR-0083 and ADR-0274.
 *
 * @implements Rule<String_>
 */
final class OrderByRequiresUniqueTiebreakerRule implements Rule
{
    /**
     * Fallback allowlist for queries the table-aware check cannot prove: a JOIN,
     * a view, a sprintf placeholder, or any other shape OrderBySqlShape returns
     * null for. A final term on this list passes by bare column name.
     * Case-insensitive match after stripping table/alias qualifier + backticks.
     *
     * 'sid' left the list because the key map proves it per table: it is the
     * PRIMARY KEY of nuke_stories, but nuke_comments.sid is a non-unique FK, which
     * a bare-name match could not tell apart.
     */
    private const UNIQUE_COLUMNS = ['id', 'pid', 'uuid', 'box_id', 'schedid', 'teamid'];

    private SchemaKeyMap $keyMap;

    public function __construct(?string $schemaDumpPath = null)
    {
        $this->keyMap = SchemaKeyMap::fromSchemaDump(
            $schemaDumpPath ?? dirname(__DIR__) . '/docs/schema/current-schema.sql'
        );
    }

    public function getNodeType(): string
    {
        return String_::class;
    }

    /**
     * @param String_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Enforce in classes/ and html/ only — tests, migrations, and scripts
        // are exempt so fixtures and historical SQL don't trip it.
        $isClassesFile = str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR);
        $isHtmlFile = str_contains($file, DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR);

        if (!$isClassesFile && !$isHtmlFile) {
            return [];
        }

        $value = $node->value;

        // Normalize internal whitespace/newlines to single spaces for matching.
        $normalized = (string) preg_replace('/\s+/', ' ', $value);

        // Carve-out: no ORDER BY.
        if (preg_match('/\border\s+by\b/i', $normalized) === 0) {
            return [];
        }

        // Carve-out: GROUP BY — skip grouped queries (grouping key governs uniqueness).
        if (preg_match('/\bgroup\s+by\b/i', $normalized) !== 0) {
            return [];
        }

        // Carve-out: LIMIT 1 — single-row result is deterministic.
        if (preg_match('/\blimit\s+1\b/i', $normalized) !== 0) {
            return [];
        }

        // Extract the ORDER BY clause: everything after the LAST "ORDER BY" up to
        // the first following LIMIT/OFFSET or end-of-string.
        $matchCount = preg_match_all('/order\s+by\s+(.+?)(?:\blimit\b|\boffset\b|$)/i', $normalized, $matches);
        if ($matchCount === 0 || $matchCount === false) {
            return [];
        }
        $orderByClause = trim(end($matches[1]), " \t\n\r\0\x0B;");

        // Extract the FINAL sort term by splitting on commas.
        $terms = array_filter(
            array_map('trim', explode(',', $orderByClause)),
            static fn (string $s): bool => $s !== '',
        );
        if ($terms === []) {
            return [];
        }
        $finalTerm = (string) end($terms);

        // Reduce to bare column name: strip trailing direction, qualifier, backticks.
        $finalTerm = (string) preg_replace('/\s+(asc|desc)\s*$/i', '', $finalTerm);
        $finalTerm = trim($finalTerm);
        // Take the first whitespace-delimited token (column ref before any alias).
        $parts = (array) preg_split('/\s+/', $finalTerm);
        $colRef = (string) ($parts[0] ?? $finalTerm);
        // Strip backticks.
        $colRef = str_replace('`', '', $colRef);
        // Strip table/alias qualifier (everything up to and including the last dot).
        if (str_contains($colRef, '.')) {
            $colRef = substr($colRef, (int) strrpos($colRef, '.') + 1);
        }
        $bareColumn = strtolower($colRef);

        if (in_array($bareColumn, self::UNIQUE_COLUMNS, true)) {
            return [];
        }

        if ($this->tableAwareAccepts($normalized, $orderByClause)) {
            return [];
        }

        $error = RuleErrorBuilder::message(
            'ORDER BY on rendered/LIMIT-cut output must end in a unique tiebreaker column '
            . '(e.g. append ", pid ASC"); final sort term "' . $bareColumn . '" is not a '
            . 'recognized-unique column. See ADR-0083. Acknowledge a genuine exception with '
            . '// @phpstan-ignore ibl.orderByMissingTiebreaker.'
        )->identifier('ibl.orderByMissingTiebreaker')->build();

        return [$error];
    }

    private function tableAwareAccepts(string $normalized, string $orderByClause): bool
    {
        $shape = OrderBySqlShape::resolve($normalized, $orderByClause);
        if ($shape === null) {
            return false;
        }
        $keys = $this->keyMap->keysFor($shape->table());
        if ($keys === null) {
            return false;
        }
        $fixed = array_flip([...$shape->orderColumns(), ...$shape->boundColumns()]);
        foreach ($keys as $key) {
            if (array_diff_key(array_flip($key), $fixed) === []) {
                return true;
            }
        }
        return false;
    }
}
