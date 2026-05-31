<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\BanDuplicateModifierMethodRule;

/**
 * @extends RuleTestCase<BanDuplicateModifierMethodRule>
 */
final class BanDuplicateModifierMethodRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new BanDuplicateModifierMethodRule();
    }

    public function testFlagsCalculateModifierMethodsOutsideContractRules(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/DuplicateModifierMethod.php'],
            [
                [
                    'Method calculateWinnerModifier() duplicates a contract-modifier formula. '
                    . 'Modifier calculations must live in ContractRules to keep salary-formula '
                    . 'logic centralized — delegate to ContractRules::calculateWinnerModifier() '
                    . 'instead of re-implementing it here.',
                    7,
                ],
                [
                    'Method calculateLoyaltyModifier() duplicates a contract-modifier formula. '
                    . 'Modifier calculations must live in ContractRules to keep salary-formula '
                    . 'logic centralized — delegate to ContractRules::calculateLoyaltyModifier() '
                    . 'instead of re-implementing it here.',
                    12,
                ],
            ],
        );
    }

    public function testAllowsModifierMethodsInsideContractRules(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/ContractRules.php'],
            [],
        );
    }

    public function testIgnoresInterfaceDeclarations(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/ModifierInterface.php'],
            [],
        );
    }
}
