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
final class TradingPrefixConventionRule implements Rule
{
    private const ALLOWED_INTERFACES = [
        'Trading\\Contracts\\TradingServiceInterface',
        'Trading\\Contracts\\TradingViewInterface',
        'Trading\\Contracts\\TradingControllerInterface',
    ];

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
        $namespace = $scope->getNamespace();
        if ($namespace !== 'Trading') {
            return [];
        }

        if ($node->name === null) {
            return [];
        }

        $className = $node->name->toString();
        if (!str_starts_with($className, 'Trading')) {
            return [];
        }

        foreach ($node->implements as $implemented) {
            $fullName = $implemented->toString();
            if (in_array($fullName, self::ALLOWED_INTERFACES, true)) {
                return [];
            }
        }

        return [
            RuleErrorBuilder::message(
                'Class ' . $className . ' uses the "Trading*" prefix, which is reserved for '
                . 'module-level entry points (Service, View, Controller). '
                . 'Domain objects should use the "Trade*" prefix or a concept-bearing name.'
            )
                ->identifier('ibl.tradingPrefixConvention')
                ->build(),
        ];
    }
}
