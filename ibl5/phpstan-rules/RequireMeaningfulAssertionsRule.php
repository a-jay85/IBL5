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
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Enforces meaningful assertions in PHPUnit test methods. Flags:
 *   1. Empty test method bodies (`public function testFoo(): void {}`)
 *   2. Trivially-true assertions: `assertTrue(true)`, `assertFalse(false)`,
 *      `assertNull(null)`, `assertEquals($x, $x)` with identical literal args.
 *
 * Only applies to files under tests/ whose class methods start with `test`.
 *
 * @implements Rule<ClassMethod>
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

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Only enforce in tests/ directory
        if (!str_contains($file, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        // Only test* methods
        if (!str_starts_with($node->name->name, 'test')) {
            return [];
        }

        $errors = [];

        // Sub-check 1: empty test body
        if ($node->stmts === null || count($node->stmts) === 0) {
            $errors[] = RuleErrorBuilder::message(
                'Test method `' . $node->name->name . '()` has an empty body. '
                . 'Add meaningful assertions or delete the test.'
            )
                ->identifier('ibl.meaninglessAssertion')
                ->line($node->getStartLine())
                ->build();
            return $errors;
        }

        // Sub-check 2: trivial assertions inside the method
        $nodeFinder = new NodeFinder();
        $methodCalls = $nodeFinder->findInstanceOf($node->stmts, MethodCall::class);

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
        }

        return $errors;
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
