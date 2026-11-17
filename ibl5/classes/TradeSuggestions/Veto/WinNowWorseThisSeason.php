<?php

declare(strict_types=1);

namespace TradeSuggestions\Veto;

use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\Engine\TeamState;
use TradeSuggestions\SlotMapper;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Veto\Contracts\VetoInterface;

/**
 * A win-now side ends the deal worse this season. It fires when any slot's
 * starter rank or best bench rank worsens, or when the injury-adjusted count
 * of rotation players drops by more than `ROTATION_EPSILON`. A missing rank
 * counts as the worst place, as in `SideGain`. Rebuild and Middle sides never
 * fire: they may give up this season.
 */
final class WinNowWorseThisSeason implements VetoInterface
{
    /** Float noise, not a tolerance: any real drop is at least 0.1 of a player. */
    public const float ROTATION_EPSILON = 1e-9;

    public function id(): string
    {
        return 'WIN_NOW_WORSE_THIS_SEASON';
    }

    public function fires(DealView $deal): bool
    {
        foreach ($deal->sides() as $side) {
            if ($side->mode !== TeamMode::WinNow) {
                continue;
            }
            $before = $side->before[0];
            $after = $side->after[0];
            if (self::ranksWorsen($before->starterRank, $after->starterRank)
                || self::ranksWorsen($before->bestBenchRank, $after->bestBenchRank)
                || self::rotation($after, $side->availabilityByPid) < self::rotation($before, $side->availabilityByPid) - self::ROTATION_EPSILON
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, ?int> $before
     * @param array<string, ?int> $after
     */
    private static function ranksWorsen(array $before, array $after): bool
    {
        foreach (SlotMapper::SLOTS as $slot) {
            if (($after[$slot] ?? SideGain::EMPTY_SLOT_RANK) > ($before[$slot] ?? SideGain::EMPTY_SLOT_RANK)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Rotation players across all slots, each counted once, weighted by
     * injury availability.
     *
     * @param array<int, float> $availability
     */
    private static function rotation(TeamState $state, array $availability): float
    {
        $pids = [];
        foreach ($state->lineup->rotation as $slotPids) {
            foreach ($slotPids as $pid) {
                $pids[$pid] = $pid;
            }
        }
        $sum = 0.0;
        foreach ($pids as $pid) {
            $sum += $availability[$pid] ?? 1.0;
        }

        return $sum;
    }
}
