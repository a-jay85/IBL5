<?php

declare(strict_types=1);

namespace Tests\League;

use Eris\Generators;
use League\ContractRules;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Support\PinnedErisSeed;

#[CoversClass(ContractRules::class)]
final class ContractRulesPropertyTest extends TestCase
{
    use PinnedErisSeed;

    public function testMaxRaiseIsLargestWholeAmountWithinRulePercentage(): void
    {
        $this->pinErisSeed(200);
        $this->forAll(Generators::choose(0, 1451), Generators::choose(0, 6))
            ->then(function (int $yearOneOffer, int $birdYears): void {
                // LEAGUE_RULES.md: 10% of year 1, or 12.5% (= 1/8) with 3+ Bird years.
                $divisor = $birdYears >= 3 ? 8 : 10;
                $raise = ContractRules::calculateMaxRaise($yearOneOffer, $birdYears);
                self::assertLessThanOrEqual($yearOneOffer, $raise * $divisor, "raise {$raise} exceeds 1/{$divisor} of {$yearOneOffer}");
                self::assertGreaterThan($yearOneOffer, ($raise + 1) * $divisor, "raise {$raise} is not the largest whole amount for {$yearOneOffer}");
            });
    }

    public function testSalaryScalesAreBoundedAndNondecreasingInExperience(): void
    {
        $this->pinErisSeed(200);
        $this->forAll(Generators::choose(0, 25), Generators::choose(0, 25))
            ->then(function (int $a, int $b): void {
                $low = min($a, $b);
                $high = max($a, $b);
                $minLow = ContractRules::getVeteranMinimumSalary($low);
                $minHigh = ContractRules::getVeteranMinimumSalary($high);
                self::assertGreaterThanOrEqual(35, $minLow);
                self::assertLessThanOrEqual(103, $minHigh);
                self::assertLessThanOrEqual($minHigh, $minLow, "veteran minimum drops from exp {$low} to {$high}");
                $maxLow = ContractRules::getMaxContractSalary($low);
                $maxHigh = ContractRules::getMaxContractSalary($high);
                self::assertContains($maxLow, [1063, 1275, 1451]);
                self::assertContains($maxHigh, [1063, 1275, 1451]);
                self::assertLessThanOrEqual($maxHigh, $maxLow, "max contract drops from exp {$low} to {$high}");
            });
    }
}
