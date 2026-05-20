<?php

declare(strict_types=1);

namespace Tests\PHPStanRules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanRules\TradingPrefixConventionRule;

/**
 * @extends RuleTestCase<TradingPrefixConventionRule>
 */
final class TradingPrefixConventionRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new TradingPrefixConventionRule();
    }

    public function testFlagsTradingPrefixedDomainClass(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Trading/TradingDealMaker.php'],
            [
                [
                    'Class TradingDealMaker uses the "Trading*" prefix, which is reserved for '
                    . 'module-level entry points (Service, View, Controller). '
                    . 'Domain objects should use the "Trade*" prefix or a concept-bearing name.',
                    7,
                ],
            ],
        );
    }

    public function testAllowsTradingServiceImplementingInterface(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Trading/ProperTradingService.php'],
            [],
        );
    }

    public function testAllowsTradePrefixedClass(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Trading/TradeAssetRepo.php'],
            [],
        );
    }

    public function testIgnoresInterfaceInContracts(): void
    {
        $this->analyse(
            [__DIR__ . '/Fixtures/classes/Trading/Contracts/TradingFooInterface.php'],
            [],
        );
    }
}
