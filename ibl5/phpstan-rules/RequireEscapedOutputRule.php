<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\Cast\Bool_ as BoolCast;
use PhpParser\Node\Expr\Cast\Double as DoubleCast;
use PhpParser\Node\Expr\Cast\Int_ as IntCast;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_ as IntLiteral;
use PhpParser\Node\Scalar\Float_ as FloatLiteral;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Echo_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces XSS escaping of every echoed expression in View classes.
 *
 * Fires in files under classes/ whose basename ends with `View.php` (the project's
 * View-class convention). Every expression passed to `echo` / `<?= ?>` must be
 * one of:
 *   - `HtmlSanitizer::e()` or `HtmlSanitizer::safeHtmlOutput()` call
 *   - a whitelisted safe-HTML helper (see SAFE_STATIC_CALLS below)
 *   - an `(int)`, `(float)`, or `(bool)` cast (always HTML-safe)
 *   - a string literal or numeric literal
 *   - a constant (`true`, `false`, `null`, class constant)
 *   - a ternary / null-coalesce / concatenation where every operand is safe
 *
 * Intentionally does NOT walk into variables or function calls — those are unsafe
 * unless explicitly whitelisted. Add new helpers to SAFE_STATIC_CALLS as the
 * whitelist grows; avoid blanket allow-lists.
 *
 * @implements Rule<Echo_>
 */
final class RequireEscapedOutputRule implements Rule
{
    /**
     * Class::method pairs whose return values are known HTML-safe.
     *
     * @var list<string>
     */
    private const SAFE_STATIC_CALLS = [
        'HtmlSanitizer::e',
        'HtmlSanitizer::safeHtmlOutput',
        'Utilities\HtmlSanitizer::e',
        'Utilities\HtmlSanitizer::safeHtmlOutput',
        'PlayerImageHelper::renderFlexiblePlayerCell',
        'PlayerImageHelper::renderPlayerCell',
    ];

    /**
     * Function names whose return values are known HTML-safe in echo contexts.
     *
     * @var list<string>
     */
    private const SAFE_FUNCTION_CALLS = [
        'json_encode',
    ];

    public function getNodeType(): string
    {
        return Echo_::class;
    }

    /**
     * @param Echo_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Only enforce in View classes under classes/
        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        if (!str_ends_with($file, 'View.php')) {
            return [];
        }

        $errors = [];
        foreach ($node->exprs as $expr) {
            if ($this->isSafeExpression($expr)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(
                'Unescaped output in View. Wrap the expression in '
                . 'HtmlSanitizer::e() (or another whitelisted safe helper), '
                . 'or cast it to (int)/(float)/(bool) if it is numeric.'
            )
                ->identifier('ibl.unescapedOutput')
                ->line($expr->getStartLine())
                ->build();
        }

        return $errors;
    }

    private function isSafeExpression(Expr $expr): bool
    {
        // Literals are always safe
        if ($expr instanceof String_) {
            return true;
        }
        if ($expr instanceof IntLiteral || $expr instanceof FloatLiteral) {
            return true;
        }

        // true / false / null / constant references
        if ($expr instanceof ConstFetch) {
            return true;
        }

        // Class::CONST — constants are compile-time, safe
        if ($expr instanceof ClassConstFetch) {
            return true;
        }

        // (int), (float), (bool) casts always produce HTML-safe scalars
        if ($expr instanceof IntCast || $expr instanceof DoubleCast || $expr instanceof BoolCast) {
            return true;
        }

        // Ternary: safe iff both branches are safe. PhpParser ternary has `if`, `else`.
        // For `cond ?: else`, `if` is null and `cond` doubles as the true branch.
        if ($expr instanceof Ternary) {
            $trueBranch = $expr->if ?? $expr->cond;
            return $this->isSafeExpression($trueBranch) && $this->isSafeExpression($expr->else);
        }

        // $a ?? $b: safe iff both sides are safe
        if ($expr instanceof Coalesce) {
            return $this->isSafeExpression($expr->left) && $this->isSafeExpression($expr->right);
        }

        // String concatenation: safe iff both sides are safe
        if ($expr instanceof Concat) {
            return $this->isSafeExpression($expr->left) && $this->isSafeExpression($expr->right);
        }

        // Static method call: safe iff Class::method is in SAFE_STATIC_CALLS
        if ($expr instanceof StaticCall) {
            if (!$expr->class instanceof Name) {
                return false;
            }
            if (!$expr->name instanceof Identifier) {
                return false;
            }

            $className = $expr->class->toString();
            $methodName = $expr->name->name;
            $key = $className . '::' . $methodName;

            // Strip leading backslash for matching
            $keyStripped = ltrim($key, '\\');

            foreach (self::SAFE_STATIC_CALLS as $safeCall) {
                if ($keyStripped === $safeCall) {
                    return true;
                }
            }

            return false;
        }

        // Function call: safe iff name is in SAFE_FUNCTION_CALLS
        if ($expr instanceof FuncCall) {
            if (!$expr->name instanceof Name) {
                return false;
            }
            $functionName = ltrim($expr->name->toString(), '\\');
            return in_array($functionName, self::SAFE_FUNCTION_CALLS, true);
        }

        // Everything else is unsafe by default (Variable, PropertyFetch,
        // MethodCall on instances, InterpolatedString, array accesses, etc.)
        return false;
    }
}
