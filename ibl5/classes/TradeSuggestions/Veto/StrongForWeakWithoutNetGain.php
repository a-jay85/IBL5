<?php

declare(strict_types=1);

namespace TradeSuggestions\Veto;

use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\Profile\SlotStrength;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\Valuation\GainWeights;
use TradeSuggestions\Veto\Contracts\VetoInterface;

/**
 * A side trades a strong spot for a patch at a weak one with nothing to show
 * for it. A strong loss is a slot that began in the top `SlotStrength::STRONG_MAX_RANK`
 * and either drops `STRONG_LOSS_PLACES` or more or leaves the top group. A weak
 * patch is a slot that began at `SlotStrength::WEAK_MIN_RANK` or worse and
 * improves. It fires when both happen, the side's total gain is no more than
 * `GainWeights::CLEAR_NET_GAIN`, and the places summed over all slots do not
 * improve.
 */
final class StrongForWeakWithoutNetGain implements VetoInterface
{
    public const int STRONG_LOSS_PLACES = 3;

    public function id(): string
    {
        return 'STRONG_FOR_WEAK_WITHOUT_NET_GAIN';
    }

    public function fires(DealView $deal): bool
    {
        foreach ($deal->sides() as $side) {
            $before = $side->before[0]->starterRank;
            $after = $side->after[0]->starterRank;
            $strongLoss = false;
            $weakPatch = false;
            $slotSum = 0;
            foreach (SlotMapper::SLOTS as $slot) {
                $b = $before[$slot] ?? SideGain::EMPTY_SLOT_RANK;
                $a = $after[$slot] ?? SideGain::EMPTY_SLOT_RANK;
                $slotSum += $b - $a;
                if ($b <= SlotStrength::STRONG_MAX_RANK && ($a - $b >= self::STRONG_LOSS_PLACES || $a > SlotStrength::STRONG_MAX_RANK)) {
                    $strongLoss = true;
                }
                if ($b >= SlotStrength::WEAK_MIN_RANK && $a < $b) {
                    $weakPatch = true;
                }
            }
            if ($strongLoss && $weakPatch && $side->gain->total() <= GainWeights::CLEAR_NET_GAIN && $slotSum <= 0) {
                return true;
            }
        }

        return false;
    }
}
