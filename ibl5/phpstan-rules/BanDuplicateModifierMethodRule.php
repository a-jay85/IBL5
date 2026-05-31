<?php

declare(strict_types=1);

namespace PHPStanRules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Bans `calculate*Modifier()` methods outside ContractRules.
 *
 * Contract-modifier formulas must live in ContractRules so the league's salary
 * logic is centralized. A `calculate<Something>Modifier()` method anywhere else
 * is a parallel re-implementation that will drift from the canonical formula.
 *
 * Only concrete implementations are flagged — interface/abstract declarations
 * (null body) are skipped. A suffixless `calculateModifier()` is a private helper,
 * not a duplicate, and does not match the pattern.
 *
 * @implements Rule<ClassMethod>
 */
final class BanDuplicateModifierMethodRule implements Rule
{
    private const METHOD_PATTERN = '/^calculate.+Modifier$/';
    private const CANONICAL_FILE = 'ContractRules.php';

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param ClassMethod $node
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        // Concrete implementations only — interface/abstract declarations have no body.
        if ($node->stmts === null) {
            return [];
        }

        $methodName = $node->name->toString();
        if (preg_match(self::METHOD_PATTERN, $methodName) !== 1) {
            return [];
        }

        $file = $scope->getFile();
        if (!str_contains($file, DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR)) {
            return [];
        }
        if (str_ends_with($file, DIRECTORY_SEPARATOR . self::CANONICAL_FILE)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Method %s() duplicates a contract-modifier formula. Modifier calculations must '
                . 'live in ContractRules to keep salary-formula logic centralized — delegate to '
                . 'ContractRules::%s() instead of re-implementing it here.',
                $methodName,
                $methodName,
            ))
                ->identifier('ibl.duplicateModifierMethod')
                ->build(),
        ];
    }
}
