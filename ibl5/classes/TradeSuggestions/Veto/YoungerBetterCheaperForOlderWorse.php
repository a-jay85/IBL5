<?php

declare(strict_types=1);

namespace TradeSuggestions\Veto;

use TradeSuggestions\Projection\Dominance;
use TradeSuggestions\Projection\DominanceComparator;
use TradeSuggestions\Valuation\ContractValue;
use TradeSuggestions\Veto\Contracts\VetoInterface;

/**
 * A side gives up a younger player who is better at his main slot and no more
 * expensive, for an older player who is not. No GM would do it.
 *
 * Fires when, for any pair the side gives and gets: the given player is
 * strictly younger, `DominanceComparator` calls him `Better` at his first
 * slot, and his average remaining salary is at most the other's. Equal age
 * does not fire. Equal average salary does fire: the `<=` is the definition.
 */
final class YoungerBetterCheaperForOlderWorse implements VetoInterface
{
    public function id(): string
    {
        return 'YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE';
    }

    public function fires(DealView $deal): bool
    {
        foreach ($deal->sides() as $side) {
            foreach ($deal->pairs($side) as [$given, $got]) {
                $slot = $given->slots()[0] ?? null;
                if ($slot === null || $given->age >= $got->age) {
                    continue;
                }
                if (DominanceComparator::compare($given->ratings, $got->ratings, $slot) !== Dominance::Better) {
                    continue;
                }
                if (ContractValue::averageRemainingSalary($given) <= ContractValue::averageRemainingSalary($got)) {
                    return true;
                }
            }
        }

        return false;
    }
}
