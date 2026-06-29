<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;

/**
 * Bans `.`-concatenation of non-constant values into SQL string literals.
 *
 * This rule is the direct sibling of BanSqlStringInterpolationRule, which bans
 * `"...$var..."` interpolation. That rule explicitly excludes `.`-concatenation
 * in its own docblock — this rule closes that gap.
 *
 * Type-aware predicate: an operand is injection-inert (not flagged) iff PHPStan
 * can prove its type is int, float, or a constant string. Everything else —
 * general `string`, `mixed`, nullable, `int|string` union, a string-returning
 * method call — is flagged as a risk.
 *
 * Chain-nesting / per-`->right` traversal: PHPStan visits every Concat node.
 * In a left-associative chain `"SELECT" . $a . $b`, parsed as
 * `Concat(Concat("SELECT", $a), $b)`, each operand is the `->right` of exactly
 * one Concat node, and the chain's leftmost leaf is never anyone's `->right`.
 * The rule type-checks only `$node->right` at each visited Concat, so every
 * operand is visited exactly once — no `getAttribute('parent')` and no duplicate
 * errors.
 *
 * The `classes/` path guard prevents the rule from analyzing itself or test
 * fixtures, avoiding a bootstrap/self-gating hazard.
 *
 * Unsound residual NOT covered by this rule (human-at-merge remains the control):
 * (a) Dynamic identifier injection — table/column/ORDER-BY names cannot be bound
 *     by prepared statements; safety depends on a match()/allowlist being correct,
 *     a judgment call this rule cannot make.
 * (b) Second-order / stored injection — tainted data stored safely then
 *     concatenated into a different query whose sink is outside the analyzed file.
 * (c) Parameterized-but-wrong authz-in-query (IDOR) — a perfectly bound query
 *     with a WHERE clause that trusts a client-supplied id.
 *
 * @implements Rule<Concat>
 */
final class BanSqlStringConcatenationRule implements Rule
{
    /**
     * Matches a literal that *begins* (after leading whitespace) with a SQL DML verb
     * or a SQL clause keyword. Anchoring at the start is what distinguishes a real
     * query/fragment from prose that merely mentions a keyword.
     */
    private const SQL_STATEMENT_PATTERN = '/^\s*(SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM|REPLACE\s+INTO|WHERE|ORDER\s+BY|GROUP\s+BY|HAVING|LEFT\s+JOIN|RIGHT\s+JOIN|INNER\s+JOIN)\b/i';

    public function getNodeType(): string
    {
        return Concat::class;
    }

    /**
     * @param Concat $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        // Walk to the leftmost leaf of the Concat subtree.
        $left = $node->left;
        while ($left instanceof Concat) {
            $left = $left->left;
        }

        // Only flag chains whose leftmost leaf is a plain SQL keyword string literal.
        // If the leftmost leaf is an InterpolatedString, BanSqlStringInterpolationRule owns it.
        if (!$left instanceof String_) {
            return [];
        }

        if (preg_match(self::SQL_STATEMENT_PATTERN, $left->value) !== 1) {
            return [];
        }

        // Check only the ->right operand of this Concat node (see chain-nesting note above).
        $right = $node->right;

        // A plain String_ literal on the right is always inert (constant author-controlled text).
        if ($right instanceof String_) {
            return [];
        }

        if ($this->isInjectionInert($scope->getType($right))) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'SQL string concatenates a non-constant value. Concatenating a runtime '
                . 'value into query text risks SQL injection. Use bound parameters (?) for values, '
                . 'and a validated allowlist/match() for identifiers (table/column names).'
            )
                ->identifier('ibl.sqlStringConcatenation')
                ->line($right->getStartLine())
                ->build(),
        ];
    }

    private function isInjectionInert(Type $type): bool
    {
        return $type->isInteger()->yes()
            || $type->isFloat()->yes()
            || $type->getConstantStrings() !== [];
    }
}
