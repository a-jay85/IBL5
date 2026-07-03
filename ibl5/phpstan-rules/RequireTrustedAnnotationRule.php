<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Cast\Bool_ as BoolCast;
use PhpParser\Node\Expr\Cast\Double as DoubleCast;
use PhpParser\Node\Expr\Cast\Int_ as IntCast;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_ as IntLiteral;
use PhpParser\Node\Scalar\Float_ as FloatLiteral;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Guards the `HtmlSanitizer::trusted()` XSS escape hatch (ADR-0002).
 *
 * `RequireEscapedOutputRule` whitelists `trusted()`'s *output* as safe HTML, but
 * nothing checks its *input*. This rule fires when `trusted()` receives an argument
 * that is not provably safe HTML, forcing either a refactor to a safe form or an
 * explicit, reviewed suppression (`// @phpstan-ignore ibl.trustedVariable`).
 *
 * A `trusted()` argument is safe iff it is one of:
 *   - a string or numeric literal
 *   - an `(int)`, `(float)`, or `(bool)` cast (always HTML-safe)
 *   - a `$this->...()` method call (a trusted-by-construction render helper)
 *
 * Everything else is unsafe by default (plain `Variable`, `(string)` cast,
 * `PropertyFetch`, `MethodCall` on anything other than `$this`, `StaticCall`,
 * `Concat`, etc.) — same default-deny philosophy as `RequireEscapedOutputRule`.
 *
 * @implements Rule<StaticCall>
 */
final class RequireTrustedAnnotationRule implements Rule
{
    private const MESSAGE = 'HtmlSanitizer::trusted() received an argument that is not a '
        . 'string/numeric literal, an (int)/(float)/(bool) cast, or a $this->...() render '
        . 'call. trusted() bypasses XSS escaping (ADR-0002), so the argument must be '
        . 'provably safe HTML. If it is genuinely pre-sanitized, suppress with '
        . '// @phpstan-ignore ibl.trustedVariable and a comment justifying why.';

    /**
     * Class::method pairs guarded by this rule.
     *
     * @var list<string>
     */
    private const TRUSTED_CALLS = [
        'HtmlSanitizer::trusted',
        'Security\HtmlSanitizer::trusted',
    ];

    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param StaticCall $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->class instanceof Name) {
            return [];
        }
        if (!$node->name instanceof Identifier) {
            return [];
        }

        $className = $node->class->toString();
        $methodName = $node->name->name;
        $key = ltrim($className . '::' . $methodName, '\\');

        if (!in_array($key, self::TRUSTED_CALLS, true)) {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) === 0) {
            return [];
        }

        $firstArg = $args[0]->value;
        if ($this->isSafeArgument($firstArg)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::MESSAGE)
                ->identifier('ibl.trustedVariable')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function isSafeArgument(Expr $expr): bool
    {
        // Literals are always safe
        if ($expr instanceof String_) {
            return true;
        }
        if ($expr instanceof IntLiteral || $expr instanceof FloatLiteral) {
            return true;
        }

        // (int), (float), (bool) casts always produce HTML-safe scalars
        if ($expr instanceof IntCast || $expr instanceof DoubleCast || $expr instanceof BoolCast) {
            return true;
        }

        // $this->...() calls are trusted-by-construction render helpers
        if ($expr instanceof MethodCall) {
            if ($expr->var instanceof Variable && $expr->var->name === 'this') {
                return true;
            }
            return false;
        }

        // Everything else is unsafe by default (Variable, (string) cast,
        // PropertyFetch, MethodCall on non-$this, StaticCall, Concat, etc.)
        return false;
    }
}
