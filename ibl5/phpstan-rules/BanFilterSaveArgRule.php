<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<FuncCall>
 */
final class BanFilterSaveArgRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @param FuncCall $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        if ($node->name->toLowerString() !== 'filter') {
            return [];
        }

        if (count($node->getArgs()) > 2) {
            return [
                RuleErrorBuilder::message(
                    'filter() must not be called with more than 2 arguments. '
                    . 'The legacy $save parameter has been removed.'
                )
                    ->identifier('ibl.bannedFilterSaveArg')
                    ->build(),
            ];
        }

        return [];
    }
}
