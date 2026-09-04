<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_ as IntLiteral;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\VerbosityLevel;

/**
 * Enforces meaningful assertions in PHPUnit test methods. Flags:
 *   1. Empty test method bodies (`public function testFoo(): void {}`)
 *   2. Trivially-true assertions: `assertTrue(true)`, `assertFalse(false)`,
 *      `assertNull(null)`, `assertEquals($x, $x)` with identical literal args.
 *   3. `assertNotNull($x)` where PHPStan statically knows `$x` is non-nullable
 *      (its type's `isNull()` is `no`), so the assertion always passes.
 *   4. Empty `catch` blocks for a broad type (`\Throwable`, `\Exception`, `\Error`),
 *      which swallow every failure — including a fatal on the first line of the
 *      system under test — leaving a test that cannot fail.
 *
 * Only applies to files under tests/ whose class methods start with `test`.
 *
 * @implements Rule<InClassMethodNode>
 */
final class RequireMeaningfulAssertionsRule implements Rule
{
    private const TRIVIAL_SINGLE_ARG_ASSERTIONS = [
        'assertTrue' => 'true',
        'assertFalse' => 'false',
        'assertNull' => 'null',
    ];

    private const EQUALITY_ASSERTIONS = [
        'assertEquals',
        'assertSame',
        'assertNotEquals',
        'assertNotSame',
    ];

    /**
     * Fully-qualified catch types broad enough that an empty handler hides a fatal
     * in the system under test, not just the narrow failure the test anticipated.
     * Lower-cased for case-insensitive comparison.
     */
    private const BROAD_CAUGHT_TYPES = [
        'throwable',
        'exception',
        'error',
    ];

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    /**
     * @param InClassMethodNode $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Keyed on the virtual InClassMethodNode, not ClassMethod: PHPStan hands a
        // ClassMethod rule the *class* scope, where parameters are unresolved
        // (`mixed`), so the assertNotNull type check below could never fire.
        // InClassMethodNode carries the in-method scope; the raw AST node the other
        // sub-checks walk is reached via getOriginalNode().
        $method = $node->getOriginalNode();
        $file = $scope->getFile();

        // Only enforce in tests/ directory
        if (!str_contains($file, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        // Only test* methods
        if (!str_starts_with($method->name->name, 'test')) {
            return [];
        }

        $errors = [];

        // Sub-check 1: empty test body
        if ($method->stmts === null || count($method->stmts) === 0) {
            $errors[] = RuleErrorBuilder::message(
                'Test method `' . $method->name->name . '()` has an empty body. '
                . 'Add meaningful assertions or delete the test.'
            )
                ->identifier('ibl.meaninglessAssertion')
                ->line($method->getStartLine())
                ->build();
            return $errors;
        }

        // Sub-check 2: trivial assertions inside the method
        $nodeFinder = new NodeFinder();
        $methodCalls = $nodeFinder->findInstanceOf($method->stmts, MethodCall::class);

        foreach ($methodCalls as $call) {
            if (!$call instanceof MethodCall) {
                continue;
            }
            if (!$call->name instanceof Identifier) {
                continue;
            }

            $methodName = $call->name->name;

            // assertTrue(true), assertFalse(false), assertNull(null)
            if (isset(self::TRIVIAL_SINGLE_ARG_ASSERTIONS[$methodName])) {
                $expected = self::TRIVIAL_SINGLE_ARG_ASSERTIONS[$methodName];
                $firstArg = $call->args[0] ?? null;
                if ($firstArg instanceof Arg && $this->isConstFetchWithName($firstArg, $expected)) {
                    $errors[] = RuleErrorBuilder::message(
                        'Trivial assertion `' . $methodName . '(' . $expected . ')` '
                        . 'always passes and does not test anything. Delete the call '
                        . 'or replace it with an assertion against actual behavior.'
                    )
                        ->identifier('ibl.meaninglessAssertion')
                        ->line($call->getStartLine())
                        ->build();
                }
            }

            // assertEquals/Same/NotEquals/NotSame with identical literal arguments
            if (in_array($methodName, self::EQUALITY_ASSERTIONS, true)) {
                $arg0 = $call->args[0] ?? null;
                $arg1 = $call->args[1] ?? null;
                if ($arg0 instanceof Arg
                    && $arg1 instanceof Arg
                    && $this->argsAreIdenticalLiterals($arg0, $arg1)
                ) {
                    $errors[] = RuleErrorBuilder::message(
                        'Equality assertion `' . $methodName . '()` is called with '
                        . 'two identical literal arguments. This assertion is trivially '
                        . 'true and does not test anything. Compare against actual '
                        . 'behavior instead.'
                    )
                        ->identifier('ibl.meaninglessAssertion')
                        ->line($call->getStartLine())
                        ->build();
                }
            }

            // assertNotNull($x) where PHPStan statically knows $x is non-null
            if ($methodName === 'assertNotNull') {
                $firstArg = $call->args[0] ?? null;
                if ($firstArg instanceof Arg && !$firstArg->unpack) {
                    $resolvedType = $scope->getType($firstArg->value);
                    if ($resolvedType->isNull()->no()) {
                        $errors[] = RuleErrorBuilder::message(
                            '`assertNotNull()` is called on a value PHPStan already knows is '
                            . 'non-null (type `' . $resolvedType->describe(VerbosityLevel::typeOnly()) . '`); '
                            . 'the assertion always passes. Assert against actual behavior instead.'
                        )
                            ->identifier('ibl.meaninglessAssertion')
                            ->line($call->getStartLine())
                            ->build();
                    }
                }
            }
        }

        // Sub-check 4: empty catch block for a broad type. Swallows every failure the
        // system under test can produce, so the test passes even when it fatals.
        foreach ($nodeFinder->findInstanceOf($method->stmts, TryCatch::class) as $tryCatch) {
            if (!$tryCatch instanceof TryCatch) {
                continue;
            }
            foreach ($tryCatch->catches as $catch) {
                if (!$this->hasNoEffectiveStatements($catch)) {
                    continue;
                }
                $broadType = $this->firstBroadCaughtType($catch);
                if ($broadType === null) {
                    continue;
                }
                $errors[] = RuleErrorBuilder::message(
                    'Empty `catch (' . $broadType . ')` in test method `' . $method->name->name . '()` '
                    . 'swallows every failure the code under test can produce — including a fatal on '
                    . 'its first line — so the test cannot fail. Narrow the caught type to the specific '
                    . 'exception the test expects, use `expectException()`, or assert on post-state '
                    . 'inside the catch.'
                )
                    ->identifier('ibl.meaninglessAssertion')
                    ->line($catch->getStartLine())
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * A catch body holding nothing but a comment parses as a single `Nop`, so counting
     * statements alone would miss the `catch (\Throwable $e) { // ignored }` shape —
     * which is the one this check exists for.
     */
    private function hasNoEffectiveStatements(Catch_ $catch): bool
    {
        foreach ($catch->stmts as $stmt) {
            if (!$stmt instanceof Nop) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns the first broad type this catch clause names (as written, with a leading
     * backslash for readability), or null when every caught type is narrow.
     */
    private function firstBroadCaughtType(Catch_ $catch): ?string
    {
        foreach ($catch->types as $type) {
            $name = ltrim($type->toString(), '\\');
            if (in_array(strtolower($name), self::BROAD_CAUGHT_TYPES, true)) {
                return '\\' . $name;
            }
        }

        return null;
    }

    private function isConstFetchWithName(Arg $arg, string $name): bool
    {
        if (!$arg->value instanceof ConstFetch) {
            return false;
        }
        return strtolower($arg->value->name->toString()) === strtolower($name);
    }

    private function argsAreIdenticalLiterals(Arg $a, Arg $b): bool
    {
        $valueA = $a->value;
        $valueB = $b->value;

        if ($valueA instanceof IntLiteral && $valueB instanceof IntLiteral) {
            return $valueA->value === $valueB->value;
        }

        if ($valueA instanceof String_ && $valueB instanceof String_) {
            return $valueA->value === $valueB->value;
        }

        return false;
    }
}
