<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans direct begin_transaction() calls in BaseMysqliRepository subclasses.
 * Use $this->transactional() instead, which handles savepoints when nested.
 *
 * @implements Rule<MethodCall>
 */
final class BanBeginTransactionInRepositoryRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Identifier) {
            return [];
        }

        if ($node->name->name !== 'begin_transaction') {
            return [];
        }

        // Allow inside BaseMysqliRepository itself (where transactional() is defined)
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        if ($classReflection->getName() === 'BaseMysqliRepository') {
            return [];
        }

        // Only flag in BaseMysqliRepository subclasses. Walk the parent chain
        // manually rather than calling the deprecated isSubclassOf(string) API.
        $parent = $classReflection->getParentClass();
        $isSubclass = false;
        while ($parent !== null) {
            if ($parent->getName() === 'BaseMysqliRepository') {
                $isSubclass = true;
                break;
            }
            $parent = $parent->getParentClass();
        }
        if (!$isSubclass) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Direct begin_transaction() calls are banned in repositories. '
                . 'Use $this->transactional(function () { ... }) instead — '
                . 'it handles savepoints when nested inside DatabaseTestCase or service-level transactions.'
            )
                ->identifier('ibl.bannedBeginTransaction')
                ->build(),
        ];
    }
}
