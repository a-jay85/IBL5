<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<New_>
 */
final class BanDbTouchingNewInViewRule implements Rule
{
    /** @var list<string> */
    private const BANNED_NEW = [
        'Season\Season',
    ];

    public function getNodeType(): string
    {
        return New_::class;
    }

    /**
     * @param New_ $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!ViewFileFilter::isViewFile($scope->getFile())) {
            return [];
        }

        if (!$node->class instanceof Name) {
            return [];
        }

        $className = ltrim($node->class->toString(), '\\');

        if (!in_array($className, self::BANNED_NEW, true)) {
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
