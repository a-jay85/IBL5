<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Flags CLI scripts that read flags from $argv without an unknown-flag guard.
 *
 * Sibling of BanUnknownCliOptionRule, which covers getopt(). A file is reported once, at the
 * first argv read, when it sits outside classes/, holds a flag-shaped string literal, and has
 * neither a Cli\CliFlagGuard::assertKnown() call nor a getopt() call.
 *
 * @implements Rule<FileNode>
 */
final class BanUnguardedArgvFlagRule implements Rule
{
    public const MESSAGE = 'Script reads CLI flags from $argv without an unknown-flag guard. '
        . 'Call Cli\CliFlagGuard::assertKnown() with the accepted flags before the first lookup, '
        . 'catch InvalidArgumentException, and exit(2). '
        . 'Suppress with @phpstan-ignore ibl.unguardedArgvFlag only when the script already rejects unknown flags by hand.';

    private const FLAG_LITERAL = '/^--?[A-Za-z][A-Za-z0-9-]*=?$/';

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    /**
     * @param FileNode $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (str_contains(str_replace('\\', '/', $scope->getFile()), '/classes/')) {
            return [];
        }

        $finder = new NodeFinder();
        $nodes = $node->getNodes();

        $argvReads = $finder->find($nodes, static fn (Node $n): bool => self::isArgvRead($n));
        if ($argvReads === []) {
            return [];
        }

        $hasFlagLiteral = $finder->findFirst(
            $nodes,
            static fn (Node $n): bool => $n instanceof String_ && preg_match(self::FLAG_LITERAL, $n->value) === 1
        ) !== null;
        if (!$hasFlagLiteral) {
            return [];
        }

        $hasGuardOrGetopt = $finder->findFirst(
            $nodes,
            static fn (Node $n): bool => self::isGuardCall($n) || self::isGetoptCall($n)
        ) !== null;
        if ($hasGuardOrGetopt) {
            return [];
        }

        return [
            RuleErrorBuilder::message(self::MESSAGE)
                ->identifier('ibl.unguardedArgvFlag')
                ->line($argvReads[0]->getStartLine())
                ->build(),
        ];
    }

    private static function isArgvRead(Node $n): bool
    {
        if ($n instanceof Variable) {
            return $n->name === 'argv';
        }
        if ($n instanceof ArrayDimFetch) {
            return $n->var instanceof Variable
                && $n->var->name === '_SERVER'
                && $n->dim instanceof String_
                && $n->dim->value === 'argv';
        }
        return false;
    }

    private static function isGuardCall(Node $n): bool
    {
        return $n instanceof StaticCall
            && $n->class instanceof Name
            && ltrim($n->class->toString(), '\\') === 'Cli\CliFlagGuard'
            && $n->name instanceof Node\Identifier
            && $n->name->toString() === 'assertKnown';
    }

    private static function isGetoptCall(Node $n): bool
    {
        return $n instanceof FuncCall
            && $n->name instanceof Name
            && $n->name->toString() === 'getopt';
    }
}
