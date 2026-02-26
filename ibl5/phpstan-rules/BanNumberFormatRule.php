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
 * Bans direct number_format() calls outside of StatsFormatter.
 * Use BasketballStats\StatsFormatter methods instead.
 *
 * @implements Rule<FuncCall>
 */
final class BanNumberFormatRule implements Rule
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @param FuncCall $node
     * @return list<\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        if ($node->name->toLowerString() !== 'number_format') {
            return [];
        }

        // Allow number_format() inside StatsFormatter (it's the approved wrapper)
        $file = $scope->getFile();
        if (str_contains($file, 'StatsFormatter.php')) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                'Direct number_format() calls are banned. Use BasketballStats\StatsFormatter methods instead '
                . '(formatPercentage, formatPerGameAverage, formatTotal, formatWithDecimals, etc.).'
            )
                ->identifier('ibl.bannedNumberFormat')
                ->build(),
        ];
    }
}
