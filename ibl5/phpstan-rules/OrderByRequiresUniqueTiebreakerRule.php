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
 *   (d) final bare column ∈ UNIQUE_COLUMNS
 *
 * @implements Rule<String_>
 */
final class OrderByRequiresUniqueTiebreakerRule implements Rule
{
    /**
     * Column names accepted as a unique/PK-ish final ORDER BY tiebreaker.
     * Case-insensitive match after stripping table/alias qualifier + backticks.
     * Extensible: add a genuinely-unique column name here (one line) when a new
     * total-order site legitimately ends on a PK the rule doesn't yet recognize.
     */
    private const UNIQUE_COLUMNS = ['id', 'pid', 'uuid', 'box_id', 'schedid', 'teamid'];

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

        $error = RuleErrorBuilder::message(
            'ORDER BY on rendered/LIMIT-cut output must end in a unique tiebreaker column '
            . '(e.g. append ", pid ASC"); final sort term "' . $bareColumn . '" is not a '
            . 'recognized-unique column. See ADR-0083. Acknowledge a genuine exception with '
            . '// @phpstan-ignore ibl.orderByMissingTiebreaker.'
        )->identifier('ibl.orderByMissingTiebreaker')->build();

        return [$error];
    }
}
