<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<StaticCall>
 */
final class BanDbTouchingCallInViewRule implements Rule
{
    /** @var array<string, list<string>> */
    private const BANNED_STATIC_CALLS = [
        'Player\Player' => ['withPlrRow', 'withPlayerID'],
        'Player\Views\TeamColorHelper' => ['getTeamColors'],
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
        if (!ViewFileFilter::isViewFile($scope->getFile())) {
            return [];
        }

        if (!$node->class instanceof Name || !$node->name instanceof Node\Identifier) {
            return [];
        }

        $className = ltrim($node->class->toString(), '\\');
        $methodName = $node->name->name;

        if (!isset(self::BANNED_STATIC_CALLS[$className])) {
            return [];
        }

        if (!in_array($methodName, self::BANNED_STATIC_CALLS[$className], true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'DB-touching construction inside a View class. '
                . 'Move to the corresponding Service and pass the pre-built object as a render parameter.'
            )
                ->identifier('ibl.dbInView')
                ->build(),
        ];
    }
}
