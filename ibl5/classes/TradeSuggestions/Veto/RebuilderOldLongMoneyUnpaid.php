<?php

declare(strict_types=1);

namespace TradeSuggestions\Veto;

use TradeSuggestions\Engine\MoneyPlaces;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Valuation\ContractValue;
use TradeSuggestions\Veto\Contracts\VetoInterface;

/**
 * A rebuilding side takes an old player on long money and gets no picks to
 * pay for it. An old player is `OLD_AGE` or more. Long money is a salary above
 * zero in at least `MIN_FUTURE_SEASONS` seasons at offset 1 or later. The
 * received picks must be worth at least his negative surplus beyond this
 * season, converted by `MoneyPlaces::fromSalary`; exactly equal does not fire.
 */
final class RebuilderOldLongMoneyUnpaid implements VetoInterface
{
    public const int OLD_AGE = 29;
    public const int MIN_FUTURE_SEASONS = 2;

    public function id(): string
    {
        return 'REBUILDER_OLD_LONG_MONEY_UNPAID';
    }

    public function fires(DealView $deal): bool
    {
        foreach ($deal->sides() as $side) {
            if ($side->mode !== TeamMode::Rebuild) {
                continue;
            }
            foreach ($side->receivedPlayersAged(self::OLD_AGE) as $p) {
                $future = 0;
                foreach ([1, 2, 3] as $offset) {
                    if ($p->salaryAt($offset) > 0) {
                        $future++;
                    }
                }
                if ($future < self::MIN_FUTURE_SEASONS) {
                    continue;
                }
                $burden = ContractValue::negativeSurplusBeyondCurrent($side->surplusByPid[$p->pid] ?? []);
                if ($side->receivedPickWorth() < MoneyPlaces::fromSalary($burden)) {
                    return true;
                }
            }
        }

        return false;
    }
}
