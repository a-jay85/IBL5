<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<Property>
 */
final class BanMysqliInViewClassesRule implements Rule
{
    public function getNodeType(): string
    {
        return Property::class;
    }

    /**
     * @param Property $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!ViewFileFilter::isViewFile($scope->getFile())) {
            return [];
        }

        $type = $node->type;
        if ($type === null) {
            return [];
        }

        if ($type instanceof Name && ltrim($type->toString(), '\\') === 'mysqli') {
            return [
                RuleErrorBuilder::message(
                    'View classes must not hold a \mysqli property. '
                    . 'Inject pre-built domain objects via render parameters instead.'
                )
                    ->identifier('ibl.dbInView')
                    ->build(),
            ];
        }

        return [];
    }
}
