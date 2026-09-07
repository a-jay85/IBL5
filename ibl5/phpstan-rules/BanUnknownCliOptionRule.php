<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/** @implements Rule<FuncCall> */
final class BanUnknownCliOptionRule implements Rule
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
        if (!$node->name instanceof Node\Name) {
            return [];
        }
        if ((string) $node->name !== 'getopt') {
            return [];
        }
        return [
            RuleErrorBuilder::message(
                'getopt() call lacks an unknown-option guard. '
                . 'After calling getopt(), compare $argv against the accepted option list '
                . 'and call usage_error() / exit(1) on any unrecognized argument. '
                . 'Suppress with @phpstan-ignore ibl.unknownCliOption only after the guard is in place.'
            )
                ->identifier('ibl.unknownCliOption')
                ->build(),
        ];
    }
}
