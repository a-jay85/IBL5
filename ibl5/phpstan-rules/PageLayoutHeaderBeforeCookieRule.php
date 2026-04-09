<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Flags `$cookie[...]` array reads that happen before `PageLayout::header(...)`
 * in the same method body.
 *
 * Background: `PageLayout::header()` populates `$cookie` with auth/CSRF state.
 * Reading `$cookie` before calling header() produces stale or missing values.
 * The CsrfGuard MAX_TOKENS=10 incident hit this exact ordering bug — this rule
 * catches it mechanically instead of relying on memory prompts.
 *
 * Strategy: walk the method body in statement order. Track whether
 * PageLayout::header has been called. If a `$cookie[` read appears before
 * the header call, flag it.
 *
 * @implements Rule<ClassMethod>
 */
final class PageLayoutHeaderBeforeCookieRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return list<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $file = $scope->getFile();

        // Only enforce in classes/
        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        if ($node->stmts === null) {
            return [];
        }

        $nodeFinder = new NodeFinder();
        $errors = [];
        $sawHeaderCall = false;

        foreach ($node->stmts as $stmt) {
            // Check if this statement contains a PageLayout::header() call
            if (!$sawHeaderCall) {
                $headerCalls = $nodeFinder->find($stmt, static function (Node $inner): bool {
                    if (!$inner instanceof StaticCall) {
                        return false;
                    }
                    if (!$inner->class instanceof Name) {
                        return false;
                    }
                    if (!$inner->name instanceof Identifier) {
                        return false;
                    }
                    return $inner->class->toString() === 'PageLayout'
                        && $inner->name->name === 'header';
                });
                if (count($headerCalls) > 0) {
                    $sawHeaderCall = true;
                    continue;
                }
            }

            // Check if this statement contains $cookie[...] array access
            if (!$sawHeaderCall) {
                $cookieReads = $nodeFinder->find($stmt, static function (Node $inner): bool {
                    if (!$inner instanceof ArrayDimFetch) {
                        return false;
                    }
                    $var = $inner->var;
                    return $var instanceof Variable
                        && is_string($var->name)
                        && $var->name === 'cookie';
                });
                foreach ($cookieReads as $cookieRead) {
                    $errors[] = RuleErrorBuilder::message(
                        '$cookie[...] is read before PageLayout::header() in the same '
                        . 'method. PageLayout::header() populates $cookie with auth and '
                        . 'CSRF state — call it first, otherwise you will read stale or '
                        . 'missing values (see CsrfGuard MAX_TOKENS incident).'
                    )
                        ->identifier('ibl.cookieBeforeHeader')
                        ->line($cookieRead->getStartLine())
                        ->build();
                }
            }
        }

        return $errors;
    }
}
