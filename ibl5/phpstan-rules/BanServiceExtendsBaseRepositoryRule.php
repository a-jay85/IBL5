<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Class_>
 */
final class BanServiceExtendsBaseRepositoryRule implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    /**
     * @param Class_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->name === null) {
            return [];
        }

        $className = $node->name->toString();
        if (!str_ends_with($className, 'Service')) {
            return [];
        }

        if ($node->extends === null) {
            return [];
        }

        $file = $scope->getFile();
        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }

        $parentName = $node->extends->toString();
        if ($parentName === 'BaseMysqliRepository' || str_ends_with($parentName, '\\BaseMysqliRepository')) {
            return [
                RuleErrorBuilder::message(
                    'Class ' . $className . ' extends BaseMysqliRepository. '
                    . 'Per ADR-0001, Service classes compose Repository classes — they do not extend them. '
                    . 'Either rename to *Repository or introduce a separate Repository collaborator and inject it.'
                )
                    ->identifier('ibl.serviceExtendsRepository')
                    ->build(),
            ];
        }

        return [];
    }
}
